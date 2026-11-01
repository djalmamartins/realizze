<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use PDOException;
use RuntimeException;

/** Regras e persistência do Módulo 01: Contas a Receber. */
final class Receivables
{
    public const PAYMENT_METHODS = ['pix', 'transfer', 'cash', 'boleto', 'card', 'other'];
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        if ($contextId <= 0) {
            throw new InvalidArgumentException('Contexto financeiro inválido.');
        }
        $this->pdo = Connection::get();
    }

    /**
     * Cadastro com as opções do formulário: repetição, tags, anexos e baixa imediata.
     * Cada ocorrência tem chave própria, derivada da chave do envio, então reenviar não duplica.
     *
     * @param list<array<string,mixed>> $files
     * @return array{id:int,duplicate:bool,count:int}
     */
    public function createWithOptions(array $data, int $userId, array $files = []): array
    {
        $fields = $this->validatedFields($data, null);
        $key = $this->idempotencyKey($data['idempotency_key'] ?? null);
        $tags = Finance::parseTags($data['tags'] ?? '');
        FinanceAttachments::validate($files);
        $occurrences = Finance::occurrences($fields['description'], $fields['amount_cents'], $fields['due_date'], $data);
        $total = count($occurrences);
        $finance = new Finance($this->contextId);
        $extras = $finance->supportsEntryExtras();
        if (!$extras && ($tags !== [] || $files !== [] || $total > 1)) {
            throw new InvalidArgumentException('Tags, anexos e repetição ainda não estão disponíveis.');
        }
        $seriesId = $total > 1 ? bin2hex(random_bytes(16)) : null;
        $money = static fn (int $cents): string => number_format($cents / 100, 2, ',', '');
        $firstId = 0;
        foreach ($occurrences as $index => $occurrence) {
            $result = $this->create([
                'description' => $occurrence['description'], 'amount' => $money($occurrence['amount']), 'due_date' => $occurrence['due'],
                'competence' => $index === 0 ? $data['competence'] : substr($occurrence['due'], 0, 7),
                'idempotency_key' => $index === 0 ? $key : hash('sha256', $key . '-' . $index),
            ] + $data, $userId);
            if ($result['duplicate']) {
                if ($index === 0) {
                    return ['id' => $result['id'], 'duplicate' => true, 'count' => $total];
                }
                continue;
            }
            $firstId = $firstId ?: $result['id'];
            if ($extras) {
                $finance->saveEntryExtras($result['id'], $seriesId, $seriesId === null ? null : (string) $data['repeat'], $index + 1, $total, $tags);
            }
        }
        $finance->addAttachments($firstId, $files, $userId);
        if (!empty($data['paid'])) {
            $this->settle($firstId, [
                'amount' => $money($occurrences[0]['amount']), 'received_at' => min($fields['due_date'], date('Y-m-d')),
                'account_id' => $fields['account_id'], 'payment_method' => $fields['payment_method'],
                'idempotency_key' => hash('sha256', $key . '-baixa'),
            ], $userId);
        }
        return ['id' => $firstId, 'duplicate' => false, 'count' => $total];
    }

    /** @return array{id:int,duplicate:bool} */
    public function create(array $data, int $userId): array
    {
        $fields = $this->validatedFields($data, null);
        $key = $this->idempotencyKey($data['idempotency_key'] ?? null);
        $existing = $this->entryByKey($key);
        if ($existing !== null) {
            return ['id' => $existing, 'duplicate' => true];
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                "INSERT INTO finance_entries
                 (financial_context_id, kind, description, amount_cents, due_date, competency_date,
                  counterparty_name, contact_id, notes, document_number, payment_method, status, account_id,
                  category_id, created_by, idempotency_key)
                 VALUES (?, 'income', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)"
            );
            $statement->execute([
                $this->contextId, $fields['description'], $fields['amount_cents'], $fields['due_date'],
                $fields['competency_date'], $fields['counterparty_name'], $fields['contact_id'], $fields['notes'],
                $fields['document_number'], $fields['payment_method'], $fields['account_id'],
                $fields['category_id'], $userId, $key,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->audit($id, 'created', $userId, ['amount_cents' => $fields['amount_cents']]);
            $this->pdo->commit();
            return ['id' => $id, 'duplicate' => false];
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            if ($exception->getCode() === '23000' && ($existing = $this->entryByKey($key)) !== null) {
                return ['id' => $existing, 'duplicate' => true];
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $entryId, array $data, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $entry = $this->lockedEntry($entryId);
            $fields = $this->validatedFields($data, $entry['contact_id'] === null ? null : (int) $entry['contact_id']);
            if ($entry['status'] === 'cancelled') {
                throw new InvalidArgumentException('Um recebimento cancelado não pode ser editado.');
            }
            $received = $this->receivedTotal($entryId);
            if ($fields['amount_cents'] < $received) {
                throw new InvalidArgumentException('O valor original não pode ser menor que o total já recebido.');
            }
            $status = $this->statusFor($fields['amount_cents'], $received);
            $paidAt = $status === 'received' ? $this->lastSettlementDate($entryId) : null;
            $statement = $this->pdo->prepare(
                'UPDATE finance_entries SET description = ?, amount_cents = ?, due_date = ?, competency_date = ?,
                 counterparty_name = ?, contact_id = ?, notes = ?, document_number = ?, payment_method = ?, account_id = ?,
                 category_id = ?, status = ?, paid_at = ?
                 WHERE id = ? AND financial_context_id = ? AND kind = \'income\''
            );
            $statement->execute([
                $fields['description'], $fields['amount_cents'], $fields['due_date'], $fields['competency_date'],
                $fields['counterparty_name'], $fields['contact_id'], $fields['notes'], $fields['document_number'],
                $fields['payment_method'], $fields['account_id'], $fields['category_id'], $status, $paidAt,
                $entryId, $this->contextId,
            ]);
            $this->audit($entryId, 'updated', $userId, [
                'amount_cents' => $fields['amount_cents'],
                'received_cents' => $received,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return array{id:int,duplicate:bool,status:string,balance_cents:int} */
    public function settle(int $entryId, array $data, int $userId): array
    {
        $key = $this->idempotencyKey($data['idempotency_key'] ?? null);
        $duplicate = $this->settlementByKey($key);
        if ($duplicate !== null) {
            return $duplicate + ['duplicate' => true];
        }
        $amount = Finance::parseCents($data['amount'] ?? null);
        $date = $this->date($data['received_at'] ?? null, 'Informe uma data de recebimento válida.');
        $accountId = $this->ownedId('finance_accounts', $data['account_id'] ?? null, true);
        $method = $this->paymentMethod($data['payment_method'] ?? null);
        $notes = $this->optionalText($data['notes'] ?? null, 500, 'A observação da baixa');

        $this->pdo->beginTransaction();
        try {
            $entry = $this->lockedEntry($entryId);
            if ($entry['status'] === 'cancelled') {
                throw new InvalidArgumentException('Não é possível baixar um recebimento cancelado.');
            }
            $received = $this->receivedTotal($entryId);
            $balance = (int) $entry['amount_cents'] - $received;
            if ($amount > $balance) {
                throw new InvalidArgumentException('O valor recebido não pode superar o saldo em aberto.');
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO finance_entry_settlements
                 (financial_context_id, entry_id, account_id, amount_cents, received_at, payment_method,
                  notes, idempotency_key, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$this->contextId, $entryId, $accountId, $amount, $date, $method, $notes, $key, $userId]);
            $settlementId = (int) $this->pdo->lastInsertId();
            $newReceived = $received + $amount;
            $status = $this->statusFor((int) $entry['amount_cents'], $newReceived);
            $this->pdo->prepare(
                'UPDATE finance_entries SET status = ?, paid_at = ? WHERE id = ? AND financial_context_id = ?'
            )->execute([$status, $status === 'received' ? $date : null, $entryId, $this->contextId]);
            $balance = (int) $entry['amount_cents'] - $newReceived;
            $this->audit($entryId, 'settled', $userId, [
                'settlement_id' => $settlementId,
                'amount_cents' => $amount,
                'balance_cents' => $balance,
            ]);
            $this->pdo->commit();
            return ['id' => $settlementId, 'duplicate' => false, 'status' => $status, 'balance_cents' => $balance];
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            if ($exception->getCode() === '23000' && ($duplicate = $this->settlementByKey($key)) !== null) {
                return $duplicate + ['duplicate' => true];
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function cancel(int $entryId, string $reason, int $userId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Informe uma justificativa de cancelamento entre 5 e 500 caracteres.');
        }
        $this->pdo->beginTransaction();
        try {
            $entry = $this->lockedEntry($entryId);
            if ($entry['status'] === 'cancelled') {
                throw new InvalidArgumentException('Este recebimento já foi cancelado.');
            }
            if ($this->receivedTotal($entryId) > 0) {
                throw new InvalidArgumentException('Recebimentos com baixas devem ser estornados antes do cancelamento.');
            }
            $this->pdo->prepare(
                "UPDATE finance_entries SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW(), paid_at = NULL
                 WHERE id = ? AND financial_context_id = ? AND kind = 'income'"
            )->execute([$reason, $entryId, $this->contextId]);
            $this->audit($entryId, 'cancelled', $userId, ['reason' => $reason]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return array{rows:array,total:int,page:int,pages:int,per_page:int} */
    public function listing(array $filters): array
    {
        $start = $this->date($filters['start'] ?? date('Y-m-01'), 'Período inicial inválido.');
        $end = $this->date($filters['end'] ?? date('Y-m-t'), 'Período final inválido.');
        if ($end < $start) {
            throw new InvalidArgumentException('O fim do período deve ser posterior ao início.');
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($filters['per_page'] ?? 15)));
        $where = ['e.financial_context_id = ?', "e.kind = 'income'", 'e.due_date BETWEEN ? AND ?'];
        $params = [$this->contextId, $start, $end];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(e.description LIKE ? OR COALESCE(ct.trade_name, ct.social_name, ct.legal_name, e.counterparty_name) LIKE ? OR e.document_number LIKE ?)';
            $like = '%' . mb_substr($search, 0, 120) . '%';
            array_push($params, $like, $like, $like);
        }
        $counterparty = trim((string) ($filters['counterparty'] ?? ''));
        if ($counterparty !== '') {
            $where[] = 'COALESCE(ct.trade_name, ct.social_name, ct.legal_name, e.counterparty_name) LIKE ?';
            $params[] = '%' . mb_substr($counterparty, 0, 160) . '%';
        }
        $categoryId = filter_var($filters['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId !== false) {
            $where[] = 'e.category_id = ?';
            $params[] = $categoryId;
        }
        $contactId = filter_var($filters['contact_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($contactId !== false) { $where[] = 'e.contact_id = ?'; $params[] = $contactId; }
        $status = in_array($filters['status'] ?? null, ['pending', 'partially_received', 'received', 'cancelled', 'overdue'], true)
            ? (string) $filters['status'] : '';

        $statusExpression = "CASE
            WHEN e.status = 'cancelled' THEN 'cancelled'
            WHEN COALESCE(s.received_cents, 0) >= e.amount_cents THEN 'received'
            WHEN COALESCE(s.received_cents, 0) > 0 THEN 'partially_received'
            WHEN e.due_date < CURDATE() THEN 'overdue'
            ELSE 'pending' END";
        $inner = 'SELECT e.id, e.description, e.amount_cents, e.due_date, e.competency_date,
                    COALESCE(ct.trade_name, ct.social_name, ct.legal_name, e.counterparty_name) AS counterparty_name,
                    e.contact_id, e.notes, e.document_number, e.payment_method, e.status AS stored_status,
                    e.cancellation_reason, e.cancelled_at, e.account_id, e.category_id,
                    a.name AS account, c.name AS category, c.color AS category_color,
                    COALESCE(s.received_cents, 0) AS received_cents,
                    GREATEST(e.amount_cents - COALESCE(s.received_cents, 0), 0) AS balance_cents,
                    ' . $statusExpression . ' AS display_status
             FROM finance_entries e
             INNER JOIN finance_accounts a ON a.id = e.account_id AND a.financial_context_id = e.financial_context_id
             LEFT JOIN finance_categories c ON c.id = e.category_id AND c.financial_context_id = e.financial_context_id
             LEFT JOIN finance_contacts ct ON ct.id = e.contact_id AND ct.financial_context_id = e.financial_context_id
             LEFT JOIN (SELECT entry_id, SUM(amount_cents) AS received_cents FROM finance_entry_settlements
                        WHERE financial_context_id = ? GROUP BY entry_id) s ON s.entry_id = e.id
             WHERE ' . implode(' AND ', $where);
        array_unshift($params, $this->contextId);
        $outerWhere = $status === '' ? '' : ' WHERE q.display_status = ?';
        if ($status !== '') {
            $params[] = $status;
        }
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT q.*, COUNT(*) OVER() AS total_rows FROM ({$inner}) q{$outerWhere}
                ORDER BY q.due_date DESC, q.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $total = $rows === [] ? 0 : (int) $rows[0]['total_rows'];
        foreach ($rows as &$row) {
            unset($row['total_rows']);
            foreach (['id', 'amount_cents', 'received_cents', 'balance_cents', 'account_id', 'category_id', 'contact_id'] as $field) {
                $row[$field] = $row[$field] === null ? null : (int) $row[$field];
            }
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'per_page' => $perPage];
    }

    /** @return array{to_receive:int,received:int,overdue:int,forecast:int} */
    public function summary(string $start, string $end): array
    {
        $start = $this->date($start, 'Período inicial inválido.');
        $end = $this->date($end, 'Período final inválido.');
        $statement = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN q.status <> 'cancelled' THEN GREATEST(q.amount_cents - q.received_cents, 0) ELSE 0 END), 0) AS to_receive,
                COALESCE(SUM(CASE WHEN q.status <> 'cancelled' THEN q.received_cents ELSE 0 END), 0) AS received,
                COALESCE(SUM(CASE WHEN q.status <> 'cancelled' AND q.due_date < CURDATE()
                                  THEN GREATEST(q.amount_cents - q.received_cents, 0) ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN q.status <> 'cancelled' THEN q.amount_cents ELSE 0 END), 0) AS forecast
             FROM (
                SELECT e.amount_cents, e.due_date, e.status, COALESCE(SUM(s.amount_cents), 0) AS received_cents
                FROM finance_entries e
                LEFT JOIN finance_entry_settlements s ON s.entry_id = e.id AND s.financial_context_id = e.financial_context_id
                WHERE e.financial_context_id = ? AND e.kind = 'income' AND e.due_date BETWEEN ? AND ?
                GROUP BY e.id
             ) q"
        );
        $statement->execute([$this->contextId, $start, $end]);
        return array_map('intval', $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function history(int $entryId): array
    {
        $this->assertOwnedEntry($entryId);
        $settlements = $this->pdo->prepare(
            'SELECT s.id, s.amount_cents, s.received_at, s.payment_method, s.notes, s.created_at,
                    a.name AS account FROM finance_entry_settlements s
             INNER JOIN finance_accounts a ON a.id = s.account_id
             WHERE s.entry_id = ? AND s.financial_context_id = ? ORDER BY s.received_at DESC, s.id DESC'
        );
        $settlements->execute([$entryId, $this->contextId]);
        $audit = $this->pdo->prepare(
            'SELECT action, metadata_json, created_at FROM finance_entry_audit_logs
             WHERE entry_id = ? AND financial_context_id = ? ORDER BY id DESC'
        );
        $audit->execute([$entryId, $this->contextId]);
        return ['settlements' => $settlements->fetchAll(PDO::FETCH_ASSOC), 'audit' => $audit->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function validatedFields(array $data, ?int $existingContactId): array
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 160) {
            throw new InvalidArgumentException('Informe uma descrição de até 160 caracteres.');
        }
        $competence = is_string($data['competence'] ?? null) ? $data['competence'] : '';
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competence) !== 1) {
            throw new InvalidArgumentException('Informe a competência no formato mês/ano.');
        }
        return [
            'description' => $description,
            'amount_cents' => Finance::parseCents($data['amount'] ?? null),
            'due_date' => $this->date($data['due_date'] ?? null, 'Informe uma data de vencimento válida.'),
            'competency_date' => $competence . '-01',
            'counterparty_name' => $this->optionalText($data['counterparty_name'] ?? null, 160, 'O cliente ou origem'),
            'contact_id' => $this->contactId($data['contact_id'] ?? null, $existingContactId),
            'notes' => $this->optionalText($data['notes'] ?? null, 5000, 'As observações'),
            'document_number' => $this->optionalText($data['document_number'] ?? null, 80, 'O número do documento'),
            'payment_method' => $this->paymentMethod($data['payment_method'] ?? null),
            'account_id' => $this->ownedId('finance_accounts', $data['account_id'] ?? null, true),
            'category_id' => $this->ownedId('finance_categories', $data['category_id'] ?? null, true, 'income'),
        ];
    }

    private function lockedEntry(int $entryId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, amount_cents, status, contact_id FROM finance_entries
             WHERE id = ? AND financial_context_id = ? AND kind = 'income' FOR UPDATE"
        );
        $statement->execute([$entryId, $this->contextId]);
        $entry = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            throw new InvalidArgumentException('Recebimento não encontrado.');
        }
        return $entry;
    }

    private function assertOwnedEntry(int $entryId): void
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM finance_entries WHERE id = ? AND financial_context_id = ? AND kind = 'income'"
        );
        $statement->execute([$entryId, $this->contextId]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Recebimento não encontrado.');
        }
    }

    private function receivedTotal(int $entryId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount_cents), 0) FROM finance_entry_settlements
             WHERE entry_id = ? AND financial_context_id = ?'
        );
        $statement->execute([$entryId, $this->contextId]);
        return (int) $statement->fetchColumn();
    }

    private function lastSettlementDate(int $entryId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT MAX(received_at) FROM finance_entry_settlements WHERE entry_id = ? AND financial_context_id = ?'
        );
        $statement->execute([$entryId, $this->contextId]);
        $date = $statement->fetchColumn();
        return is_string($date) ? $date : null;
    }

    private function entryByKey(string $key): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM finance_entries WHERE financial_context_id = ? AND kind = 'income' AND idempotency_key = ?"
        );
        $statement->execute([$this->contextId, $key]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function settlementByKey(string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id, e.status, GREATEST(e.amount_cents - totals.received_cents, 0) AS balance_cents
             FROM finance_entry_settlements s
             INNER JOIN finance_entries e ON e.id = s.entry_id AND e.financial_context_id = s.financial_context_id
             INNER JOIN (SELECT entry_id, SUM(amount_cents) AS received_cents FROM finance_entry_settlements
                         WHERE financial_context_id = ? GROUP BY entry_id) totals ON totals.entry_id = e.id
             WHERE s.financial_context_id = ? AND s.idempotency_key = ? LIMIT 1'
        );
        $statement->execute([$this->contextId, $this->contextId, $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ['id' => (int) $row['id'], 'status' => $row['status'], 'balance_cents' => (int) $row['balance_cents']] : null;
    }

    private function audit(int $entryId, string $action, int $userId, array $metadata): void
    {
        $this->pdo->prepare(
            'INSERT INTO finance_entry_audit_logs
             (financial_context_id, entry_id, action, actor_user_id, metadata_json) VALUES (?, ?, ?, ?, ?)'
        )->execute([$this->contextId, $entryId, $action, $userId, json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }

    private function ownedId(string $table, mixed $value, bool $required, ?string $kind = null): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            if ($required) {
                throw new InvalidArgumentException($table === 'finance_accounts' ? 'Escolha uma conta de destino.' : 'Escolha uma categoria.');
            }
            return null;
        }
        $kindFilter = $kind === null ? '' : ' AND kind = ?';
        $statement = $this->pdo->prepare("SELECT id FROM {$table} WHERE id = ? AND financial_context_id = ?{$kindFilter}");
        $statement->execute(array_values(array_filter([$id, $this->contextId, $kind], static fn ($item): bool => $item !== null)));
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException($table === 'finance_accounts' ? 'Conta de destino não autorizada.' : 'Categoria não autorizada.');
        }
        return (int) $id;
    }

    private function contactId(mixed $value, ?int $existingContactId): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) return null;
        $statement = $this->pdo->prepare(
            'SELECT status, is_customer FROM finance_contacts WHERE id = ? AND financial_context_id = ?'
        );
        $statement->execute([$id, $this->contextId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || !(int) $row['is_customer']) {
            throw new InvalidArgumentException('Cliente não autorizado para este contexto.');
        }
        if ($row['status'] !== 'active' && (int) $id !== $existingContactId) {
            throw new InvalidArgumentException('Contatos inativos não podem ser vinculados a novos recebimentos.');
        }
        return (int) $id;
    }

    private function paymentMethod(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Escolha uma forma de recebimento válida.');
        }
        return $value;
    }

    private function idempotencyKey(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Identificador da requisição inválido. Atualize a página e tente novamente.');
        }
        return $value;
    }

    private function date(mixed $value, string $message): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }

    private function optionalText(mixed $value, int $max, string $label): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw new InvalidArgumentException("{$label} deve ter no máximo {$max} caracteres.");
        }
        return $text;
    }

    private function statusFor(int $amount, int $received): string
    {
        return $received <= 0 ? 'pending' : ($received >= $amount ? 'received' : 'partially_received');
    }
}
