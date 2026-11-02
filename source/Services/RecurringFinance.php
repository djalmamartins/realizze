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

    private function occurrencesReady(): bool
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
            $this->advanceNextDue($id, $due, $item['frequency']);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
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
        if (mb_strlen($value) > $limit) throw new InvalidArgumentException('Texto acima do limite permitido.');
        return $value === '' ? null : $value;
    }
}
