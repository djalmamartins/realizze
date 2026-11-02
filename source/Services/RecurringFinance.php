<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

final class RecurringFinance
{
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        $this->pdo = Connection::get();
    }

    public function listing(string $status = 'active'): array
    {
        $allowed = ['active', 'paused', 'canceled'];
        $status = in_array($status, $allowed, true) ? $status : 'active';
        $statement = $this->pdo->prepare(
            'SELECT r.*, a.name account_name, c.name category_name, c.color category_color
             FROM finance_recurring_items r
             JOIN finance_accounts a ON a.id=r.account_id AND a.financial_context_id=r.financial_context_id
             LEFT JOIN finance_categories c ON c.id=r.category_id AND c.financial_context_id=r.financial_context_id
             WHERE r.financial_context_id=? AND r.status=? ORDER BY r.next_due_date, r.name'
        );
        $statement->execute([$this->contextId, $status]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cobranças previstas no período que ainda não viraram lançamento. Só leitura:
     * next_due_date já avança quando a ocorrência é gerada, então nada aparece em dobro.
     */
    public function planned(string $kind, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.id, r.item_type, r.name, r.provider, r.amount_cents, r.frequency, r.next_due_date, a.name account_name
             FROM finance_recurring_items r
             JOIN finance_accounts a ON a.id=r.account_id AND a.financial_context_id=r.financial_context_id
             WHERE r.financial_context_id=? AND r.status='active' AND r.kind=? AND r.next_due_date<=?"
        );
        $statement->execute([$this->contextId, $kind, $to]);
        $planned = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $anchor = $this->anchorDay((int) $item['id'], $item['next_due_date']);
            for ($due = $item['next_due_date']; $due <= $to; $due = $this->nextDue($due, $item['frequency'], $anchor)) {
                if ($due >= $from) $planned[] = ['due_date' => $due] + $item;
            }
        }
        usort($planned, static fn (array $a, array $b): int => [$a['due_date'], $a['name']] <=> [$b['due_date'], $b['name']]);
        return $planned;
    }

    public function counts(): array
    {
        $result = ['active' => 0, 'paused' => 0, 'canceled' => 0];
        $statement = $this->pdo->prepare('SELECT status,COUNT(*) total FROM finance_recurring_items WHERE financial_context_id=? GROUP BY status');
        $statement->execute([$this->contextId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) $result[$row['status']] = (int) $row['total'];
        return $result;
    }

    public function create(array $data, int $userId): int
    {
        $type = in_array($data['item_type'] ?? null, ['subscription', 'recurring'], true) ? $data['item_type'] : 'subscription';
        $kind = in_array($data['kind'] ?? null, ['income', 'expense'], true) ? $data['kind'] : 'expense';
        $frequency = in_array($data['frequency'] ?? null, ['weekly', 'monthly', 'quarterly', 'yearly'], true) ? $data['frequency'] : 'monthly';
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) throw new InvalidArgumentException('Informe um nome com até 160 caracteres.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['next_due_date'] ?? ''));
        if (!$date || $date->format('Y-m-d') !== ($data['next_due_date'] ?? '')) throw new InvalidArgumentException('Informe a próxima cobrança.');
        $account = $this->ownedId('finance_accounts', $data['account_id'] ?? null, false);
        $category = $this->ownedId('finance_categories', $data['category_id'] ?? null, true);
        if (isset($data['auto_create']) && !$this->occurrencesReady()) {
            throw new InvalidArgumentException('Execute a migration 011 antes de habilitar lançamentos automáticos.');
        }
        $statement = $this->pdo->prepare('INSERT INTO finance_recurring_items (financial_context_id,item_type,kind,name,provider,amount_cents,frequency,next_due_date,account_id,category_id,auto_create,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $statement->execute([$this->contextId,$type,$kind,$name,$this->optional($data['provider']??'',120),Finance::parseCents($data['amount']??''),$frequency,$date->format('Y-m-d'),$account,$category,isset($data['auto_create'])?1:0,$this->optional($data['notes']??'',500),$userId]);
        $recurringId = (int) $this->pdo->lastInsertId();
        if (isset($data['auto_create']) && $this->occurrencesReady()) {
            $this->generateFirstOccurrence($recurringId);
        }
        return $recurringId;
    }

    /** Altera a recorrência. Lançamentos já gerados ficam como estão; o controle de ocorrências evita duplicar. */
    public function update(int $id, array $data): void
    {
        $type = in_array($data['item_type'] ?? null, ['subscription', 'recurring'], true) ? $data['item_type'] : 'subscription';
        $frequency = in_array($data['frequency'] ?? null, ['weekly', 'monthly', 'quarterly', 'yearly'], true) ? $data['frequency'] : 'monthly';
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) throw new InvalidArgumentException('Informe um nome com até 160 caracteres.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['next_due_date'] ?? ''));
        if (!$date || $date->format('Y-m-d') !== ($data['next_due_date'] ?? '')) throw new InvalidArgumentException('Informe a próxima cobrança.');
        $account = $this->ownedId('finance_accounts', $data['account_id'] ?? null, false);
        $category = $this->ownedId('finance_categories', $data['category_id'] ?? null, true);
        $statement = $this->pdo->prepare('SELECT id FROM finance_recurring_items WHERE id=? AND financial_context_id=?');
        $statement->execute([$id, $this->contextId]);
        if ($statement->fetchColumn() === false) throw new InvalidArgumentException('Recorrência não encontrada.');
        $this->pdo->prepare('UPDATE finance_recurring_items SET item_type=?,name=?,provider=?,amount_cents=?,frequency=?,next_due_date=?,account_id=?,category_id=?,auto_create=?,notes=? WHERE id=? AND financial_context_id=?')
            ->execute([$type,$name,$this->optional($data['provider']??'',120),Finance::parseCents($data['amount']??''),$frequency,$date->format('Y-m-d'),$account,$category,isset($data['auto_create'])&&$this->occurrencesReady()?1:0,$this->optional($data['notes']??'',500),$id,$this->contextId]);
    }

    /** Lançamento gerado na primeira cobrança, para receber tags, anexos e baixa informados na modal. */
    public function firstEntryId(int $id): ?int
    {
        if (!$this->occurrencesReady()) return null;
        $statement = $this->pdo->prepare('SELECT entry_id FROM finance_recurring_occurrences WHERE recurring_item_id=? AND financial_context_id=? ORDER BY due_date LIMIT 1');
        $statement->execute([$id, $this->contextId]);
        $entry = $statement->fetchColumn();
        return $entry === false ? null : (int) $entry;
    }

    public function occurrencesReady(): bool
    {
        return $this->pdo->query("SHOW TABLES LIKE 'finance_recurring_occurrences'")->fetchColumn() !== false;
    }

    private function generateFirstOccurrence(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare('SELECT * FROM finance_recurring_items WHERE id=? AND financial_context_id=? FOR UPDATE');
            $select->execute([$id, $this->contextId]);
            $item = $select->fetch(PDO::FETCH_ASSOC);
            if (!$item || $item['status'] !== 'active') {
                $this->pdo->commit();
                return;
            }
            $due = $item['next_due_date'];
            $check = $this->pdo->prepare('SELECT id FROM finance_recurring_occurrences WHERE recurring_item_id=? AND due_date=?');
            $check->execute([$id, $due]);
            if ($check->fetchColumn() === false) {
                $insert = $this->pdo->prepare('INSERT INTO finance_entries (financial_context_id,kind,description,amount_cents,due_date,paid_at,account_id,category_id,created_by,notes) VALUES (?,?,?,?,?,NULL,?,?,?,?)');
                $insert->execute([$this->contextId,$item['kind'],$item['name'],$item['amount_cents'],$due,$item['account_id'],$item['category_id'],$item['created_by'],$item['notes']]);
                $entryId = (int) $this->pdo->lastInsertId();
                $link = $this->pdo->prepare('INSERT INTO finance_recurring_occurrences (financial_context_id,recurring_item_id,due_date,entry_id) VALUES (?,?,?,?)');
                $link->execute([$this->contextId,$id,$due,$entryId]);
            }
            $this->advanceNextDue($id, $due, $item['frequency'], $this->anchorDay($id, $due));
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function advanceNextDue(int $id, string $due, string $frequency, int $anchor): void
    {
        $update = $this->pdo->prepare('UPDATE finance_recurring_items SET next_due_date=? WHERE id=? AND financial_context_id=?');
        $update->execute([$this->nextDue($due, $frequency, $anchor), $id, $this->contextId]);
    }

    /** Dia original da cobrança: quem vence dia 31 passa por 28 em fevereiro e volta a 31 em março. */
    private function anchorDay(int $id, string $due): int
    {
        $date = new DateTimeImmutable($due);
        $day = (int) $date->format('j');
        if ($day !== (int) $date->format('t') || !$this->occurrencesReady()) return $day;
        $statement = $this->pdo->prepare('SELECT DAY(due_date) FROM finance_recurring_occurrences WHERE recurring_item_id=? AND financial_context_id=? ORDER BY due_date LIMIT 1');
        $statement->execute([$id, $this->contextId]);
        return max($day, (int) $statement->fetchColumn());
    }

    private function nextDue(string $due, string $frequency, int $anchor): string
    {
        $date = new DateTimeImmutable($due);
        if ($frequency === 'weekly') {
            $next = $date->modify('+7 days');
        } else {
            $months = match ($frequency) {
                'quarterly' => 3,
                'yearly' => 12,
                default => 1,
            };
            $first = $date->modify('first day of this month')->modify('+' . $months . ' months');
            $day = min($anchor, (int) $first->format('t'));
            $next = $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
        }
        return $next->format('Y-m-d');
    }

    public function generateDue(?DateTimeImmutable $through = null, int $limit = 30): int
    {
        if (!$this->occurrencesReady()) return 0;
        $through ??= new DateTimeImmutable('today');
        $limit = max(1, min(100, $limit));
        $query = $this->pdo->prepare("SELECT id FROM finance_recurring_items WHERE financial_context_id=? AND status='active' AND auto_create=1 AND next_due_date<=? ORDER BY next_due_date,id LIMIT 1");
        $count = 0;
        while ($count < $limit) {
            $query->execute([$this->contextId, $through->format('Y-m-d')]);
            $id = $query->fetchColumn();
            $query->closeCursor();
            if ($id === false) break;
            $this->generateFirstOccurrence((int) $id);
            $count++;
        }
        return $count;
    }

    public function changeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'paused', 'canceled'], true)) throw new InvalidArgumentException('Situação inválida.');
        $statement = $this->pdo->prepare("UPDATE finance_recurring_items SET status=?,canceled_at=IF(?='canceled',NOW(),NULL) WHERE id=? AND financial_context_id=?");
        $statement->execute([$status,$status,$id,$this->contextId]);
        return $statement->rowCount() === 1;
    }

    private function ownedId(string $table, mixed $value, bool $optional): ?int
    {
        if ($optional && ($value === null || $value === '')) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($id === false) throw new InvalidArgumentException('Seleção inválida.');
        $statement = $this->pdo->prepare("SELECT id FROM {$table} WHERE id=? AND financial_context_id=?");
        $statement->execute([$id,$this->contextId]);
        if ($statement->fetchColumn() === false) throw new InvalidArgumentException('Item não encontrado neste perfil.');
        return (int) $id;
    }

    private function optional(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) > $limit) throw new InvalidArgumentException('Use até ' . $limit . ' caracteres nos textos da recorrência.');
        return $value === '' ? null : $value;
    }
}
