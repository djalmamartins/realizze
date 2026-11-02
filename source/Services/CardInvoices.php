<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Cartões: compras viram despesas em aberto; pagar fatura gera somente a saída da conta de origem. */
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
        $q = $this->pdo->prepare("SELECT e.id,e.description,e.amount_cents,e.due_date,e.paid_at,c.name category FROM finance_entries e LEFT JOIN finance_categories c ON c.id=e.category_id WHERE e.financial_context_id=? AND e.account_id=? AND e.kind='expense' AND e.due_date BETWEEN ? AND ? AND e.paid_at IS NULL ORDER BY e.due_date,e.id");
        $q->execute([$this->contextId, $cardId, $start, $end]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        return ['start' => $start, 'end' => $end, 'due_on' => $due, 'rows' => $rows, 'total_cents' => array_sum(array_map(static fn($r) => (int) $r['amount_cents'], $rows))];
    }

    /** Quita as despesas de cartão da fatura e cria uma única saída na conta pagadora. */
    public function pay(array $data, int $userId): int
    {
        $card = $this->ownedCard($data['card_account_id'] ?? null);
        $source = $this->ownedCashAccount($data['source_account_id'] ?? null);
        $month = (string) ($data['month'] ?? '');
        $invoice = $this->invoice($card, $month);
        if ($invoice['total_cents'] <= 0) throw new InvalidArgumentException('Esta fatura não possui compras em aberto.');
        $key = $this->key($data['idempotency_key'] ?? null);
        $old = $this->pdo->prepare('SELECT id FROM finance_card_invoice_payments WHERE financial_context_id=? AND idempotency_key=?');
        $old->execute([$this->contextId, $key]);
        if (($id = $old->fetchColumn()) !== false) return (int) $id;
        $paidOn = $this->date($data['paid_on'] ?? date('Y-m-d'));
        $this->pdo->beginTransaction();
        try {
            $entry = $this->pdo->prepare("INSERT INTO finance_entries (financial_context_id,kind,description,amount_cents,due_date,paid_at,account_id,created_by,idempotency_key) VALUES (?,'expense',?,?,?,?,?,?,?)");
            $entry->execute([$this->contextId, 'Pagamento de fatura', $invoice['total_cents'], $paidOn, $paidOn, $source, $userId, hash('sha256', $key . ':cash')]);
            $cashEntry = (int) $this->pdo->lastInsertId();
            $ids = array_map(static fn(array $r): int => (int) $r['id'], $invoice['rows']);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->prepare("UPDATE finance_entries SET paid_at=? WHERE financial_context_id=? AND account_id=? AND id IN ({$marks}) AND paid_at IS NULL")->execute([$paidOn, $this->contextId, $card, ...$ids]);
            $save = $this->pdo->prepare('INSERT INTO finance_card_invoice_payments (financial_context_id,card_account_id,source_account_id,entry_id,period_start,period_end,amount_cents,idempotency_key,paid_on,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $save->execute([$this->contextId, $card, $source, $cashEntry, $invoice['start'], $invoice['end'], $invoice['total_cents'], $key, $paidOn, $userId]);
            $id = (int) $this->pdo->lastInsertId(); $this->pdo->commit(); return $id;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function installments(string $date, int $amount, int $count, array $rules): array { $list=[]; for($i=0;$i<$count;$i++){ $base=(new DateTimeImmutable($date))->modify('first day of +' . $i . ' month'); $list[]=['amount'=>intdiv($amount,$count)+($i===0?$amount%$count:0),'due'=>$this->cycleDate($base,$rules)->format('Y-m-d')]; } return $list; }
    private function cardRules(int $id): array { $q=$this->pdo->prepare('SELECT closing_day,due_day FROM finance_accounts WHERE id=? AND financial_context_id=?');$q->execute([$id,$this->contextId]);return $q->fetch(PDO::FETCH_ASSOC) ?: []; }
    private function period(int $card, string $month): array { $r=$this->cardRules($card); $base=new DateTimeImmutable($month.'-01'); $close=max(1,min(31,(int)($r['closing_day'] ?: 1))); $due=max(1,min(31,(int)($r['due_day'] ?: 10))); $end=$base->setDate((int)$base->format('Y'),(int)$base->format('n'),min($close,(int)$base->format('t'))); $start=$end->modify('-1 month +1 day'); $dueBase=$end->modify('+1 month'); $dueDate=$dueBase->setDate((int)$dueBase->format('Y'),(int)$dueBase->format('n'),min($due,(int)$dueBase->format('t'))); return [$start->format('Y-m-d'),$end->format('Y-m-d'),$dueDate->format('Y-m-d')]; }
    private function cycleDate(DateTimeImmutable $date,array $rules): DateTimeImmutable { $close=(int)($rules['closing_day']?:1); $due=(int)($rules['due_day']?:10); if((int)$date->format('j')>$close)$date=$date->modify('+1 month'); $base=$date->modify('first day of this month'); return $base->setDate((int)$base->format('Y'),(int)$base->format('n'),min($due,(int)$base->format('t'))); }
    private function ownedCard(mixed $id): int { return $this->owned($id, "AND type='credit_card'"); }
    private function ownedCashAccount(mixed $id): int { return $this->owned($id, "AND type <> 'credit_card'"); }
    private function owned(mixed $id,string $extra=''): int { $id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare("SELECT id FROM finance_accounts WHERE id=? AND financial_context_id=? AND archived_at IS NULL AND status='active' {$extra}");$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Conta inválida para esta área.');return (int)$id; }
    private function ownedCategory(mixed $id): ?int { if($id===''||$id===null)return null;$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare('SELECT id FROM finance_categories WHERE id=? AND financial_context_id=?');$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Categoria inválida.');return (int)$id; }
    private function ownedCostCenter(mixed $id): ?int { if($id===''||$id===null)return null;$id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$this->pdo->prepare("SELECT id FROM finance_cost_centers WHERE id=? AND financial_context_id=? AND status='active'");$q->execute([$id,$this->contextId]);if($id===false||$q->fetchColumn()===false)throw new InvalidArgumentException('Centro de custo inválido.');return (int)$id; }
    private function key(mixed $key): string { if(!is_string($key)||preg_match('/^[a-f0-9]{64}$/',$key)!==1)throw new InvalidArgumentException('Envio inválido. Atualize a página e tente novamente.');return $key; }
    private function date(mixed $v): string { $d=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if($d===false||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Data inválida.');return $v; }
}
