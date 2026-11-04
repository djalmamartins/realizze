<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/**
 * Cartões: compras viram despesas do cartão, com vencimento no dia da fatura em que caem.
 * Pagar a fatura é uma transferência da conta de origem para o cartão: a despesa conta uma
 * vez só (pela compra), o banco sai uma vez e o cartão volta a zero.
 */
final class CardInvoices
{
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        $this->pdo = Connection::get();
    }

    public function cards(): array
    {
        $q = $this->pdo->prepare("SELECT id,name,institution,credit_limit_cents,closing_day,due_day FROM finance_accounts WHERE financial_context_id=? AND type='credit_card' AND archived_at IS NULL AND status='active' ORDER BY name");
        $q->execute([$this->contextId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function purchase(array $data, int $userId): int
    {
        $cardId = $this->ownedCard($data['card_account_id'] ?? null);
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 160) throw new InvalidArgumentException('Informe uma descrição de até 160 caracteres.');
        $date = $this->date($data['purchased_on'] ?? null);
        $amount = Finance::parseCents($data['amount'] ?? null);
        $installments = filter_var($data['installments'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
        if ($installments === false || $amount < $installments) throw new InvalidArgumentException('Informe parcelas compatíveis com o valor.');
        $category = $this->ownedCategory($data['category_id'] ?? null);
        $costCenter = $this->ownedCostCenter($data['cost_center_id'] ?? null);
        $key = $this->key($data['idempotency_key'] ?? null);
        $existing = $this->pdo->prepare('SELECT id FROM finance_card_purchases WHERE financial_context_id=? AND idempotency_key=?');
        $existing->execute([$this->contextId, $key]);
        if (($id = $existing->fetchColumn()) !== false) return (int) $id;
        $this->pdo->beginTransaction();
        try {
            $p = $this->pdo->prepare('INSERT INTO finance_card_purchases (financial_context_id,card_account_id,description,purchased_on,total_cents,installments,idempotency_key,created_by) VALUES (?,?,?,?,?,?,?,?)');
            $p->execute([$this->contextId, $cardId, $description, $date, $amount, $installments, $key, $userId]);
            $purchaseId = (int) $this->pdo->lastInsertId();
            $entry = $this->pdo->prepare('INSERT INTO finance_entries (financial_context_id,kind,description,amount_cents,due_date,account_id,category_id,cost_center_id,created_by,idempotency_key) VALUES (?,\'expense\',?,?,?,?,?,?,?,?)');
            $link = $this->pdo->prepare('INSERT INTO finance_card_installments (financial_context_id,purchase_id,entry_id,installment_number,due_on,amount_cents) VALUES (?,?,?,?,?,?)');
            foreach ($this->installments($date, $amount, $installments, $this->cardRules($cardId)) as $index => $part) {
                $label = $installments === 1 ? $description : mb_substr($description, 0, 150) . ' (' . ($index + 1) . '/' . $installments . ')';
                $entry->execute([$this->contextId, $label, $part['amount'], $part['due'], $cardId, $category, $costCenter, $userId, hash('sha256', $key . ':' . $index)]);
                $entryId = (int) $this->pdo->lastInsertId();
                $link->execute([$this->contextId, $purchaseId, $entryId, $index + 1, $part['due'], $part['amount']]);
            }
            $this->pdo->commit();
            return $purchaseId;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    /** Fatura é derivada de compras em aberto; não há tabela de saldo mutável. */
    public function invoice(int $cardId, string $reference): array
    {
        $this->ownedCard($cardId);
        if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $reference)) throw new InvalidArgumentException('Mês inválido.');
        [$start, $end, $due] = $this->period($cardId, $reference);
        // A parcela guarda o vencimento da sua fatura; a fatura reúne o que vence depois da anterior e até o seu dia.
        $previousDue = $this->period($cardId, (new DateTimeImmutable($reference . '-01'))->modify('-1 month')->format('Y-m'))[2];
        $q = $this->pdo->prepare("SELECT e.id,e.description,e.amount_cents,e.due_date,e.paid_at,c.name category,
            COALESCE(SUM(CASE WHEN p.reversed_at IS NULL THEN a.amount_cents ELSE 0 END),0) AS paid_cents
            FROM finance_entries e LEFT JOIN finance_categories c ON c.id=e.category_id
            LEFT JOIN finance_card_payment_allocations a ON a.card_entry_id=e.id AND a.financial_context_id=e.financial_context_id
            LEFT JOIN finance_card_invoice_payments p ON p.id=a.payment_id
            WHERE e.financial_context_id=? AND e.account_id=? AND e.kind='expense' AND e.due_date > ? AND e.due_date <= ? AND e.paid_at IS NULL
            GROUP BY e.id HAVING e.amount_cents > paid_cents ORDER BY e.due_date,e.id");
        $q->execute([$this->contextId, $cardId, $previousDue, $due]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['open_cents'] = (int) $row['amount_cents'] - (int) $row['paid_cents'];
        unset($row);
        return ['start' => $start, 'end' => $end, 'due_on' => $due, 'rows' => $rows, 'total_cents' => array_sum(array_map(static fn($r) => (int) $r['open_cents'], $rows))];
    }

    /** Quita as despesas de cartão da fatura e cria uma única transferência da conta pagadora para o cartão. */
    public function pay(array $data, int $userId): int
    {
        $card = $this->ownedCard($data['card_account_id'] ?? null);
        $source = $this->ownedCashAccount($data['source_account_id'] ?? null);
        $month = (string) ($data['month'] ?? '');
        // O reenvio do mesmo formulário devolve o pagamento já feito, antes de olhar a fatura (que já está paga).
        $key = $this->key($data['idempotency_key'] ?? null);
        $old = $this->pdo->prepare('SELECT id FROM finance_card_invoice_payments WHERE financial_context_id=? AND idempotency_key=?');
        $old->execute([$this->contextId, $key]);
        if (($id = $old->fetchColumn()) !== false) return (int) $id;
        $invoice = $this->invoice($card, $month);
        if ($invoice['total_cents'] <= 0) throw new InvalidArgumentException('Esta fatura não possui compras em aberto.');
        $amount = ($data['amount'] ?? '') === '' ? $invoice['total_cents'] : Finance::parseCents($data['amount']);
        if ($amount > $invoice['total_cents']) throw new InvalidArgumentException('O pagamento não pode superar o saldo aberto da fatura.');
        $paidOn = $this->date($data['paid_on'] ?? date('Y-m-d'));
        $this->pdo->beginTransaction();
        try {
            $entry = $this->pdo->prepare("INSERT INTO finance_entries (financial_context_id,kind,description,amount_cents,due_date,paid_at,account_id,target_account_id,created_by,idempotency_key) VALUES (?,'transfer',?,?,?,?,?,?,?,?)");
            $entry->execute([$this->contextId, 'Pagamento de fatura', $amount, $paidOn, $paidOn, $source, $card, $userId, hash('sha256', $key . ':cash')]);
            $cashEntry = (int) $this->pdo->lastInsertId();
            $save = $this->pdo->prepare('INSERT INTO finance_card_invoice_payments (financial_context_id,card_account_id,source_account_id,entry_id,period_start,period_end,amount_cents,idempotency_key,paid_on,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $save->execute([$this->contextId, $card, $source, $cashEntry, $invoice['start'], $invoice['end'], $amount, $key, $paidOn, $userId]);
            $id = (int) $this->pdo->lastInsertId();
            $allocation = $this->pdo->prepare('INSERT INTO finance_card_payment_allocations (financial_context_id,payment_id,card_entry_id,amount_cents) VALUES (?,?,?,?)');
            $remaining=$amount;
            foreach($invoice['rows'] as $row){ if($remaining<=0) break; $part=min($remaining,(int)$row['open_cents']); $allocation->execute([$this->contextId,$id,(int)$row['id'],$part]); $remaining-=$part; if($part===(int)$row['open_cents']) $this->pdo->prepare('UPDATE finance_entries SET paid_at=? WHERE id=? AND financial_context_id=?')->execute([$paidOn,(int)$row['id'],$this->contextId]); }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    /**
     * Estorno preserva o histórico: o pagamento fica marcado como estornado e a transferência dele,
     * cancelada e sem data de pagamento, sai dos saldos. Apagar a transferência apagaria o pagamento (FK em cascata).
     */
    public function reversePayment(int $paymentId, int $userId): bool
    {
        $this->pdo->beginTransaction(); try {
            $q=$this->pdo->prepare('SELECT * FROM finance_card_invoice_payments WHERE id=? AND financial_context_id=? AND reversed_at IS NULL FOR UPDATE');$q->execute([$paymentId,$this->contextId]);$payment=$q->fetch(PDO::FETCH_ASSOC);if(!$payment){$this->pdo->rollBack();return false;}
            $a=$this->pdo->prepare('SELECT card_entry_id FROM finance_card_payment_allocations WHERE payment_id=?');$a->execute([$paymentId]);$ids=$a->fetchAll(PDO::FETCH_COLUMN);
            $this->pdo->prepare('UPDATE finance_card_invoice_payments SET reversed_at=NOW(),reversed_by=? WHERE id=?')->execute([$userId,$paymentId]);
            $this->pdo->prepare("UPDATE finance_entries SET paid_at=NULL,status='cancelled' WHERE id=? AND financial_context_id=?")->execute([(int)$payment['entry_id'],$this->contextId]);
            foreach($ids as $entryId)$this->pdo->prepare('UPDATE finance_entries SET paid_at=NULL WHERE id=? AND financial_context_id=?')->execute([(int)$entryId,$this->contextId]);
            $this->pdo->commit();return true;
        } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Pagamentos de um cartão, com os estornados, do mais recente para o mais antigo. */
    public function payments(int $cardId, int $limit = 20): array
    {
        $this->ownedCard($cardId);
        $q = $this->pdo->prepare('SELECT p.id, p.amount_cents, p.paid_on, p.period_start, p.period_end, p.reversed_at, a.name AS source_account
            FROM finance_card_invoice_payments p INNER JOIN finance_accounts a ON a.id = p.source_account_id
            WHERE p.financial_context_id = ? AND p.card_account_id = ? ORDER BY p.id DESC LIMIT ' . max(1, min(100, $limit)));
        $q->execute([$this->contextId, $cardId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A primeira parcela cai na fatura da data da compra; cada seguinte, na fatura do mês seguinte. */
    private function installments(string $date, int $amount, int $count, array $rules): array
    {
        $first = $this->cycleDate(new DateTimeImmutable($date), $rules);
        $close = $this->closingDate($first->modify('first day of this month'), $rules);
        // O vencimento pode cair no mês do fechamento ou no seguinte; a fatura é a do fechamento anterior ao vencimento.
        if ($close >= $first) $close = $this->closingDate($first->modify('first day of previous month'), $rules);
        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $closing = $this->closingDate($close->modify('first day of this month')->modify('+' . $i . ' month'), $rules);
            $list[] = ['amount' => intdiv($amount, $count) + ($i === 0 ? $amount % $count : 0), 'due' => $this->dueAfter($closing, $rules)->format('Y-m-d')];
        }
        return $list;
    }
    private function cardRules(int $id): array { $q=$this->pdo->prepare('SELECT closing_day,due_day FROM finance_accounts WHERE id=? AND financial_context_id=?');$q->execute([$id,$this->contextId]);return $q->fetch(PDO::FETCH_ASSOC) ?: []; }
    /** Fechamento da fatura do mês e vencimento: o primeiro dia de vencimento depois do fechamento. */
    private function period(int $card, string $month): array
    {
        $rules = $this->cardRules($card);
        $close = $this->closingDate(new DateTimeImmutable($month . '-01'), $rules);
        $previous = $this->closingDate((new DateTimeImmutable($month . '-01'))->modify('-1 month'), $rules);
        return [$previous->modify('+1 day')->format('Y-m-d'), $close->format('Y-m-d'), $this->dueAfter($close, $rules)->format('Y-m-d')];
    }

    private function closingDate(DateTimeImmutable $month, array $rules): DateTimeImmutable
    {
        $close = max(1, min(31, (int) ($rules['closing_day'] ?: 1)));
        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min($close, (int) $month->format('t')));
    }

    private function dueAfter(DateTimeImmutable $close, array $rules): DateTimeImmutable
    {
        $due = max(1, min(31, (int) ($rules['due_day'] ?: 10)));
        $base = $due > (int) $close->format('j') ? $close : $close->modify('first day of next month');
        return $base->setDate((int) $base->format('Y'), (int) $base->format('n'), min($due, (int) $base->format('t')));
    }

    /** Vencimento da fatura em que cai uma compra (ou parcela) feita nesta data. */
    private function cycleDate(DateTimeImmutable $date, array $rules): DateTimeImmutable
    {
        $month = $date->modify('first day of this month');
        if ($date > $this->closingDate($month, $rules)) $month = $month->modify('+1 month');
        return $this->dueAfter($this->closingDate($month, $rules), $rules);
    }

    private function ownedCard(mixed $id): int { return $this->owned($id, "AND type='credit_card'"); }
    private function ownedCashAccount(mixed $id): int { return $this->owned($id, "AND type <> 'credit_card'"); }
    private function owned(mixed $id,string $extra=''): int { $id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare("SELECT id FROM finance_accounts WHERE id=? AND financial_context_id=? AND archived_at IS NULL AND status='active' {$extra}");$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Conta inválida para esta área.');return (int)$id; }
    private function ownedCategory(mixed $id): ?int { if($id===''||$id===null)return null;$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare('SELECT id FROM finance_categories WHERE id=? AND financial_context_id=?');$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Categoria inválida.');return (int)$id; }
    private function ownedCostCenter(mixed $id): ?int { if($id===''||$id===null)return null;$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare("SELECT id FROM finance_cost_centers WHERE id=? AND financial_context_id=? AND status='active'");$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Centro de custo inválido.');return (int)$id; }
    private function key(mixed $key): string { if(!is_string($key)||preg_match('/^[a-f0-9]{64}$/',$key)!==1)throw new InvalidArgumentException('Envio inválido. Atualize a página e tente novamente.');return $key; }
    private function date(mixed $v): string { $d=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if($d===false||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Data inválida.');return $v; }
}
