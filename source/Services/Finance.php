<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/**
 * Lançamentos e contas de um contexto financeiro. Toda consulta e alteração é
 * limitada a $contextId, recebido apenas de FinanceContext.
 */
final class Finance
{
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        $this->pdo = Connection::get();
    }

    private static function optionalDay(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $day = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
        if ($day === false) throw new InvalidArgumentException('Informe um dia entre 1 e 31.');
        return $day;
    }

    /** Converte "1.234,56" em centavos. */
    public static function parseCents(mixed $value): int
    {
        $text = is_string($value) ? trim($value) : '';
        if (!preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/', $text)) {
            throw new InvalidArgumentException('Informe um valor válido, como 1.250,00.');
        }
        [$whole, $decimal] = array_pad(explode(',', str_replace('.', '', $text)), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($decimal, 2, '0');
        if ($cents <= 0) {
            throw new InvalidArgumentException('O valor deve ser maior que zero.');
        }
        return $cents;
    }

    public static function money(int $cents): string
    {
        return ($cents < 0 ? '- ' : '') . 'R$ ' . number_format(abs($cents) / 100, 2, ',', '.');
    }

    public function accounts(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, type, institution, opening_balance_cents, status, card_kind, credit_limit_cents, closing_day, due_day FROM finance_accounts
             WHERE financial_context_id = ? AND archived_at IS NULL AND status = \'active\' ORDER BY name'
        );
        $statement->execute([$this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(string $kind): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, color FROM finance_categories
             WHERE financial_context_id = ? AND kind = ? ORDER BY name'
        );
        $statement->execute([$this->contextId, $kind]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param 'all'|'open'|'paid'|'overdue' $status */
    public function entries(string $kind, string $start, string $end, string $status = 'all', array $filters = []): array
    {
        $filter = match ($status) {
            'open' => ' AND e.paid_at IS NULL',
            'paid' => ' AND e.paid_at IS NOT NULL',
            'overdue' => ' AND e.paid_at IS NULL AND e.due_date < CURDATE()',
            default => '',
        };
        $params = [$this->contextId, $kind, $start, $end];
        if (($filters['search'] ?? '') !== '') {
            $filter .= ' AND e.description LIKE ?';
            $params[] = '%' . addcslashes($filters['search'], '%_\\') . '%';
        }
        foreach (['category_id' => 'e.category_id', 'account_id' => 'e.account_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $filter .= " AND {$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (($filters['tag'] ?? '') !== '' && $this->supportsEntryExtras()) {
            $filter .= ' AND EXISTS (SELECT 1 FROM finance_entry_tags AS l INNER JOIN finance_tags AS t ON t.id = l.tag_id WHERE l.entry_id = e.id AND t.name = ?)';
            $params[] = $filters['tag'];
        }
        $statement = $this->pdo->prepare(
            "SELECT e.id, e.description, e.amount_cents, e.due_date, e.paid_at,
                    c.name AS category, c.color AS category_color, a.name AS account
             FROM finance_entries AS e
             INNER JOIN finance_accounts AS a ON a.id = e.account_id
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             WHERE e.financial_context_id = ? AND e.kind = ? AND e.due_date BETWEEN ? AND ?{$filter}
             ORDER BY e.due_date, e.id"
        );
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{expected:int,paid:int,overdue:int,count:int} */
    public function summary(string $kind, string $start, string $end): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN paid_at IS NULL THEN amount_cents END), 0) AS expected,
                    COALESCE(SUM(CASE WHEN paid_at IS NOT NULL THEN amount_cents END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN paid_at IS NULL AND due_date < CURDATE() THEN amount_cents END), 0) AS overdue,
                    COUNT(*) AS count
             FROM finance_entries
             WHERE financial_context_id = ? AND kind = ? AND due_date BETWEEN ? AND ?
               AND (status IS NULL OR status <> \'cancelled\')'
        );
        $statement->execute([$this->contextId, $kind, $start, $end]);
        return array_map('intval', $statement->fetch(PDO::FETCH_ASSOC));
    }

    /** Observação, tags, anexos e repetição dependem da migration 008. */
    public function supportsEntryExtras(): bool
    {
        return $this->pdo->query("SHOW TABLES LIKE 'finance_entry_attachments'")->fetchColumn() !== false;
    }

    /** @return list<string> */
    public function tags(): array
    {
        if (!$this->supportsEntryExtras()) {
            return [];
        }
        $statement = $this->pdo->prepare('SELECT name FROM finance_tags WHERE financial_context_id = ? ORDER BY name');
        $statement->execute([$this->contextId]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Observação, repetição, tags e anexos de cada lançamento informado.
     *
     * @param list<int> $entryIds
     * @return array<int,array{notes:?string,series_type:?string,series_index:?int,series_total:?int,tags:list<string>,attachments:list<array{id:int,name:string}>}>
     */
    public function entryDetails(array $entryIds): array
    {
        if ($entryIds === [] || !$this->supportsEntryExtras()) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($entryIds), '?'));
        $params = [$this->contextId, ...$entryIds];
        $statement = $this->pdo->prepare("SELECT id, notes, series_type, series_index, series_total FROM finance_entries WHERE financial_context_id = ? AND id IN ({$marks})");
        $statement->execute($params);
        $details = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $details[(int) $row['id']] = [
                'notes' => $row['notes'], 'series_type' => $row['series_type'],
                'series_index' => $row['series_index'] === null ? null : (int) $row['series_index'],
                'series_total' => $row['series_total'] === null ? null : (int) $row['series_total'],
                'tags' => [], 'attachments' => [],
            ];
        }
        $statement = $this->pdo->prepare(
            "SELECT l.entry_id, t.name FROM finance_entry_tags AS l INNER JOIN finance_tags AS t ON t.id = l.tag_id
             WHERE t.financial_context_id = ? AND l.entry_id IN ({$marks}) ORDER BY t.name"
        );
        $statement->execute($params);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $details[(int) $row['entry_id']]['tags'][] = $row['name'];
        }
        $statement = $this->pdo->prepare(
            "SELECT id, entry_id, original_name FROM finance_entry_attachments WHERE financial_context_id = ? AND entry_id IN ({$marks}) ORDER BY id"
        );
        $statement->execute($params);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $details[(int) $row['entry_id']]['attachments'][] = ['id' => (int) $row['id'], 'name' => $row['original_name']];
        }
        return $details;
    }

    /** @return array{stored_path:string,original_name:string,mime_type:string}|null */
    public function attachment(int $id): ?array
    {
        if (!$this->supportsEntryExtras()) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT stored_path, original_name, mime_type FROM finance_entry_attachments WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$id, $this->contextId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cria o lançamento e, se houver repetição, as demais ocorrências. Devolve o id do primeiro.
     *
     * @param list<array<string,mixed>> $files arquivos enviados; ficam na primeira ocorrência
     */
    public function createEntry(string $kind, array $data, ?int $userId, array $files = []): int
    {
        if (!in_array($kind, ['income', 'expense'], true)) {
            throw new InvalidArgumentException('Tipo de lançamento inválido.');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 160) {
            throw new InvalidArgumentException('Informe uma descrição de até 160 caracteres.');
        }
        $due = self::date($data['due_date'] ?? null);
        $accountId = $this->ownedId('finance_accounts', $data['account_id'] ?? null);
        if ($accountId === null) {
            throw new InvalidArgumentException('Escolha uma conta.');
        }
        $categoryId = $this->ownedId('finance_categories', $data['category_id'] ?? null);
        // Data futura paga agora conta como paga hoje; data passada, no próprio dia informado.
        $paidAt = !empty($data['paid']) ? min($due, date('Y-m-d')) : null;
        $amount = self::parseCents($data['amount'] ?? null);

        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 5000) {
            throw new InvalidArgumentException('Use até 5000 caracteres na observação.');
        }
        $tags = self::parseTags($data['tags'] ?? '');
        $occurrences = self::occurrences($description, $amount, $due, $data);
        $total = count($occurrences);
        FinanceAttachments::validate($files);
        $extras = $this->supportsEntryExtras();
        if (!$extras && ($tags !== [] || $files !== [] || $total > 1)) {
            throw new InvalidArgumentException('Tags, anexos e repetição ainda não estão disponíveis.');
        }

        // A chave do envio impede que um duplo clique registre o mesmo lançamento duas vezes.
        $key = is_string($data['idempotency_key'] ?? null) && preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $data['idempotency_key']) === 1 ? $data['idempotency_key'] : null;
        if ($key !== null && ($existing = $this->entryByKey($key)) !== null) {
            return $existing;
        }
        $seriesId = $total > 1 ? bin2hex(random_bytes(16)) : null;
        $insert = $this->pdo->prepare(
            'INSERT INTO finance_entries
             (financial_context_id, kind, description, amount_cents, due_date, paid_at, account_id, category_id, created_by, notes, idempotency_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stored = [];
        $firstId = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($occurrences as $index => $occurrence) {
                $insert->execute([
                    $this->contextId, $kind, $occurrence['description'], $occurrence['amount'], $occurrence['due'],
                    $index === 0 ? $paidAt : null, $accountId, $categoryId, $userId, $notes === '' ? null : $notes,
                    $key === null ? null : ($index === 0 ? $key : hash('sha256', $key . '-' . $index)),
                ]);
                $entryId = (int) $this->pdo->lastInsertId();
                $firstId = $firstId ?: $entryId;
                if ($extras) {
                    $this->saveEntryExtras($entryId, $seriesId, $seriesId === null ? null : (string) $data['repeat'], $index + 1, $total, $tags);
                }
            }
            if ($kind === 'income' && $paidAt !== null) {
                $this->recordReceipt($firstId, $occurrences[0]['amount'], $paidAt, $accountId, $userId);
            }
            $stored = $this->addAttachments($firstId, $files, $userId);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            array_walk($stored, static fn (string $path) => FinanceAttachments::remove($path));
            throw $error;
        }
        return $firstId;
    }

    private function entryByKey(string $key): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM finance_entries WHERE financial_context_id = ? AND idempotency_key = ?');
        $statement->execute([$this->contextId, $key]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Edita uma despesa, só desta ocorrência. A baixa fica como está: o saldo sai do próprio lançamento.
     *
     * @param list<array<string,mixed>> $files
     */
    public function updateExpense(int $entryId, array $data, ?int $userId, array $files = []): void
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 160) {
            throw new InvalidArgumentException('Informe uma descrição de até 160 caracteres.');
        }
        $due = self::date($data['due_date'] ?? null);
        $accountId = $this->ownedId('finance_accounts', $data['account_id'] ?? null);
        if ($accountId === null) {
            throw new InvalidArgumentException('Escolha uma conta.');
        }
        $categoryId = $this->ownedId('finance_categories', $data['category_id'] ?? null);
        $amount = self::parseCents($data['amount'] ?? null);
        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 5000) {
            throw new InvalidArgumentException('Use até 5000 caracteres na observação.');
        }
        self::parseTags($data['tags'] ?? '');
        FinanceAttachments::validate($files);
        $statement = $this->pdo->prepare("SELECT id FROM finance_entries WHERE id = ? AND financial_context_id = ? AND kind = 'expense'");
        $statement->execute([$entryId, $this->contextId]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Lançamento não encontrado.');
        }
        if ($this->pdo->query("SHOW TABLES LIKE 'finance_debt_payments'")->fetchColumn() !== false) {
            $statement = $this->pdo->prepare('SELECT 1 FROM finance_debt_payments WHERE entry_id = ? AND financial_context_id = ?');
            $statement->execute([$entryId, $this->contextId]);
            if ($statement->fetchColumn() !== false) {
                throw new InvalidArgumentException('Este lançamento é o pagamento de uma dívida. Altere-o em Sair das dívidas.');
            }
        }
        if (GoalWallets::ready() && (new GoalWallets($this->contextId))->operationForEntry($entryId) !== null) {
            throw new InvalidArgumentException('Esta despesa foi paga com uma meta. Para mudar, exclua e lance novamente.');
        }
        $this->pdo->prepare('UPDATE finance_entries SET description = ?, amount_cents = ?, due_date = ?, account_id = ?, category_id = ?, notes = ? WHERE id = ? AND financial_context_id = ?')
            ->execute([$description, $amount, $due, $accountId, $categoryId, $notes === '' ? null : $notes, $entryId, $this->contextId]);
        $this->updateEntryExtras($entryId, $data, $userId, $files);
    }

    /**
     * Grava tags e anexos editados de um lançamento, sempre dentro do contexto atual.
     *
     * @param list<array<string,mixed>> $files
     */
    public function updateEntryExtras(int $entryId, array $data, ?int $userId, array $files = []): void
    {
        if (!$this->supportsEntryExtras()) {
            return;
        }
        $tags = self::parseTags($data['tags'] ?? '');
        $remove = array_values(array_filter(array_map('intval', is_array($data['remove_attachments'] ?? null) ? $data['remove_attachments'] : []), static fn (int $id): bool => $id > 0));
        $statement = $this->pdo->prepare('SELECT id, stored_path FROM finance_entry_attachments WHERE entry_id = ? AND financial_context_id = ?');
        $statement->execute([$entryId, $this->contextId]);
        $current = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        $gone = array_intersect_key($current, array_flip($remove));
        FinanceAttachments::validate($files);
        if (count($current) - count($gone) + count($files) > 5) {
            throw new InvalidArgumentException('Mantenha no máximo 5 anexos por lançamento.');
        }
        $this->pdo->prepare('DELETE FROM finance_entry_tags WHERE entry_id = ?')->execute([$entryId]);
        $this->attachTags($entryId, $tags);
        foreach ($gone as $id => $path) {
            $this->pdo->prepare('DELETE FROM finance_entry_attachments WHERE id = ? AND financial_context_id = ?')->execute([$id, $this->contextId]);
            FinanceAttachments::remove($path);
        }
        $this->addAttachments($entryId, $files, $userId);
    }

    /**
     * Dados de cada lançamento para abrir a modal em modo de edição.
     *
     * @param list<int> $entryIds
     * @return array<int,array<string,mixed>>
     */
    public function entryEditData(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($entryIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id, kind, description, amount_cents, due_date, competency_date, counterparty_name, contact_id, notes, document_number, payment_method, account_id, category_id
             FROM finance_entries WHERE financial_context_id = ? AND kind IN ('income','expense') AND id IN ({$marks})"
        );
        $statement->execute([$this->contextId, ...$entryIds]);
        $details = $this->entryDetails($entryIds);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            $detail = $details[$id] ?? null;
            $result[$id] = [
                'id' => $id, 'source' => 'entry', 'kind' => $row['kind'], 'description' => $row['description'],
                'amount' => number_format((int) $row['amount_cents'] / 100, 2, ',', '.'), 'due_date' => $row['due_date'],
                'account_id' => (int) $row['account_id'], 'category_id' => (int) $row['category_id'], 'notes' => (string) $row['notes'],
                'competence' => substr((string) ($row['competency_date'] ?? $row['due_date']), 0, 7), 'contact_id' => (int) $row['contact_id'],
                'counterparty_name' => (string) $row['counterparty_name'], 'document_number' => (string) $row['document_number'],
                'payment_method' => (string) ($row['payment_method'] ?: 'other'),
                'tags' => $detail['tags'] ?? [], 'attachments' => $detail['attachments'] ?? [],
                'series' => $detail !== null && $detail['series_total'] !== null ? $detail['series_index'] . '/' . $detail['series_total'] : '',
            ];
        }
        return $result;
    }

    /** Contas a receber calcula o saldo pelas baixas, então a receita já recebida precisa de uma. */
    private function recordReceipt(int $entryId, int $amount, string $date, int $accountId, ?int $userId): void
    {
        $this->pdo->prepare(
            "INSERT INTO finance_entry_settlements (financial_context_id, entry_id, account_id, amount_cents, received_at, payment_method, idempotency_key, created_by)
             VALUES (?, ?, ?, ?, ?, 'other', ?, ?)"
        )->execute([$this->contextId, $entryId, $accountId, $amount, $date, bin2hex(random_bytes(32)), $userId]);
        $this->pdo->prepare("UPDATE finance_entries SET status = 'received' WHERE id = ?")->execute([$entryId]);
    }

    /** @param list<string> $tags */
    public function saveEntryExtras(int $entryId, ?string $seriesId, ?string $seriesType, int $index, int $total, array $tags): void
    {
        if ($seriesId !== null) {
            $this->pdo->prepare('UPDATE finance_entries SET series_id = ?, series_type = ?, series_index = ?, series_total = ? WHERE id = ? AND financial_context_id = ?')
                ->execute([$seriesId, $seriesType, $index, $total, $entryId, $this->contextId]);
        }
        $this->attachTags($entryId, $tags);
    }

    /**
     * @param list<array<string,mixed>> $files
     * @return list<string> caminhos gravados
     */
    public function addAttachments(int $entryId, array $files, ?int $userId): array
    {
        $stored = [];
        foreach ($files as $file) {
            $saved = FinanceAttachments::store($file);
            $stored[] = $saved['path'];
            $this->pdo->prepare(
                'INSERT INTO finance_entry_attachments (financial_context_id, entry_id, uploaded_by, stored_path, original_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$this->contextId, $entryId, $userId, $saved['path'], $saved['name'], $saved['mime'], $saved['size']]);
        }
        return $stored;
    }

    /** @return list<string> */
    public static function parseTags(mixed $value): array
    {
        $tags = [];
        foreach (explode(',', is_string($value) ? $value : '') as $tag) {
            $tag = trim((string) preg_replace('/\s+/u', ' ', $tag));
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > 40) {
                throw new InvalidArgumentException('Use até 40 caracteres em cada tag.');
            }
            $tags[mb_strtolower($tag)] ??= $tag;
        }
        if (count($tags) > 10) {
            throw new InvalidArgumentException('Use no máximo 10 tags por lançamento.');
        }
        return array_values($tags);
    }

    /** @param list<string> $tags */
    private function attachTags(int $entryId, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->pdo->prepare(
                'INSERT INTO finance_tags (financial_context_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            )->execute([$this->contextId, $tag]);
            $this->pdo->prepare('INSERT IGNORE INTO finance_entry_tags (entry_id, tag_id) VALUES (?, ?)')
                ->execute([$entryId, (int) $this->pdo->lastInsertId()]);
        }
    }

    /**
     * Ocorrências de um lançamento. "fixed" repete o valor; "installment" divide o total,
     * com os centavos que sobram na primeira parcela.
     *
     * @return list<array{description:string,amount:int,due:string}>
     */
    public static function occurrences(string $description, int $amount, string $due, array $data): array
    {
        $type = $data['repeat'] ?? '';
        if (!in_array($type, ['fixed', 'installment'], true)) {
            return [['description' => $description, 'amount' => $amount, 'due' => $due]];
        }
        $total = filter_var($data['repeat_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 120]]);
        if ($total === false) {
            throw new InvalidArgumentException('Informe de 2 a 120 repetições.');
        }
        $steps = ['weekly' => [0, 7], 'biweekly' => [0, 15], 'monthly' => [1, 0], 'bimonthly' => [2, 0], 'quarterly' => [3, 0], 'semiannual' => [6, 0], 'yearly' => [12, 0]];
        $step = $steps[$data['repeat_every'] ?? ''] ?? null;
        if ($step === null) {
            throw new InvalidArgumentException('Escolha a frequência da repetição.');
        }
        if ($type === 'installment' && $amount < $total) {
            throw new InvalidArgumentException('O valor é pequeno demais para esse número de parcelas.');
        }
        $start = new DateTimeImmutable($due);
        $list = [];
        for ($i = 0; $i < $total; $i++) {
            // Conta sempre a partir da data inicial: dia 31 cai no último dia dos meses curtos e volta a 31 depois.
            $first = $start->modify('first day of this month')->modify('+' . ($step[0] * $i) . ' months');
            $date = $step[0] > 0
                ? $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min((int) $start->format('j'), (int) $first->format('t')))
                : $start->modify('+' . ($step[1] * $i) . ' days');
            $list[] = [
                'description' => $type === 'installment' ? mb_substr($description, 0, 150) . ' (' . ($i + 1) . '/' . $total . ')' : $description,
                'amount' => $type === 'installment' ? intdiv($amount, $total) + ($i === 0 ? $amount % $total : 0) : $amount,
                'due' => $date->format('Y-m-d'),
            ];
        }
        return $list;
    }

    public function settle(int $entryId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE finance_entries SET paid_at = CURDATE()
             WHERE id = ? AND financial_context_id = ? AND paid_at IS NULL AND kind IN ('income','expense')"
        );
        $statement->execute([$entryId, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    public function delete(int $entryId, ?int $userId = null): bool
    {
        $files = [];
        if ($this->supportsEntryExtras()) {
            $statement = $this->pdo->prepare('SELECT stored_path FROM finance_entry_attachments WHERE entry_id = ? AND financial_context_id = ?');
            $statement->execute([$entryId, $this->contextId]);
            $files = $statement->fetchAll(PDO::FETCH_COLUMN);
        }
        // Despesa paga com uma meta: o estorno devolve a reserva junto com a exclusão.
        $wallets = GoalWallets::ready() ? new GoalWallets($this->contextId) : null;
        $operation = $wallets?->operationForEntry($entryId);
        if ($operation !== null) {
            $wallets->reverse($operation, $userId);
            array_walk($files, static fn (string $path) => FinanceAttachments::remove($path));
            return true;
        }
        $statement = $this->pdo->prepare('DELETE FROM finance_entries WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$entryId, $this->contextId]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        array_walk($files, static fn (string $path) => FinanceAttachments::remove($path));
        return true;
    }

    /**
     * Contas com saldo: inicial + entradas pagas - saídas pagas +/- transferências.
     * Disponível = saldo - reservado em metas. Cartões não guardam reservas.
     */
    public function accountsWithBalance(): array
    {
        // Sem a migration 006, as colunas do catálogo vêm nulas e não há JOIN.
        $catalogReady = FinancialInstitutions::catalogReady();
        $institutionColumns = $catalogReady
            ? 'a.institution_id, fi.trade_name AS institution_name, fi.slug AS institution_slug, fi.logo_file AS institution_logo'
            : 'NULL AS institution_id, NULL AS institution_name, NULL AS institution_slug, NULL AS institution_logo';
        $institutionJoin = $catalogReady ? 'LEFT JOIN financial_institutions AS fi ON fi.id = a.institution_id' : '';
        $statement = $this->pdo->prepare(
            "SELECT a.id, a.name, a.type, a.institution, {$institutionColumns},
                    a.status, a.card_kind, a.credit_limit_cents, a.closing_day, a.due_day,
                    a.opening_balance_cents + COALESCE(SUM(CASE
                        WHEN e.paid_at IS NULL THEN 0
                        WHEN e.kind = 'income' AND e.account_id = a.id THEN e.amount_cents
                        WHEN e.kind = 'expense' AND e.account_id = a.id THEN -CAST(e.amount_cents AS SIGNED)
                        WHEN e.kind = 'transfer' AND e.account_id = a.id THEN -CAST(e.amount_cents AS SIGNED)
                        WHEN e.kind = 'transfer' AND e.target_account_id = a.id THEN e.amount_cents
                        ELSE 0 END), 0) AS balance_cents
             FROM finance_accounts AS a
             {$institutionJoin}
             LEFT JOIN finance_entries AS e ON e.financial_context_id = a.financial_context_id
                 AND (e.account_id = a.id OR e.target_account_id = a.id)
             WHERE a.financial_context_id = ? AND a.archived_at IS NULL AND a.status = 'active'
             GROUP BY a.id ORDER BY a.type = 'credit_card', a.name"
        );
        $statement->execute([$this->contextId]);
        $reserved = GoalWallets::ready() ? (new GoalWallets($this->contextId))->reservedByAccount() : [];
        return array_map(static function (array $row) use ($reserved): array {
            $row['balance_cents'] = (int) $row['balance_cents'];
            $row['reserved_cents'] = $reserved[(int) $row['id']] ?? 0;
            $row['available_cents'] = $row['balance_cents'] - $row['reserved_cents'];
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Fonte única dos indicadores de saldo, sem cartões de crédito.
     *
     * @param list<array<string,mixed>>|null $accounts
     * @return array{total_cents:int,reserved_cents:int,available_cents:int,shortfalls:list<array{name:string,cents:int}>}
     */
    public function balanceSummary(?array $accounts = null): array
    {
        $cash = array_filter($accounts ?? $this->accountsWithBalance(), static fn (array $account): bool => $account['type'] !== 'credit_card');
        $total = array_sum(array_column($cash, 'balance_cents'));
        $reserved = array_sum(array_column($cash, 'reserved_cents'));
        $short = array_filter($cash, static fn (array $account): bool => $account['reserved_cents'] > 0 && $account['available_cents'] < 0);
        return [
            'total_cents' => $total, 'reserved_cents' => $reserved, 'available_cents' => $total - $reserved,
            'shortfalls' => array_values(array_map(static fn (array $account): array => ['name' => (string) $account['name'], 'cents' => -$account['available_cents']], $short)),
        ];
    }

    /** Saldo disponível: saldo das contas menos o reservado em metas. */
    public function availableBalance(): int
    {
        return $this->balanceSummary()['available_cents'];
    }

    public function createAccount(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Informe um nome de até 120 caracteres.');
        }
        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, ['checking', 'savings', 'wallet', 'credit_card'], true)) {
            throw new InvalidArgumentException('Escolha o tipo da conta.');
        }
        $opening = trim((string) ($data['opening_balance'] ?? ''));
        $cardKind = $type === 'credit_card' ? 'credit' : (($data['card_kind'] ?? '') === 'debit' ? 'debit' : null);
        $limit = trim((string) ($data['credit_limit'] ?? ''));
        $closingDay = self::optionalDay($data['closing_day'] ?? null);
        $dueDay = self::optionalDay($data['due_day'] ?? null);
        // Banco do catálogo (opcional). Sem catálogo, vale o texto livre.
        $catalogReady = FinancialInstitutions::catalogReady();
        $institutionId = !$catalogReady || ($data['institution_id'] ?? '') === '' ? null : FinancialInstitutions::activeId($data['institution_id']);
        if ($catalogReady && ($data['institution_id'] ?? '') !== '' && $institutionId === null) {
            throw new InvalidArgumentException('Escolha uma instituição da lista.');
        }
        $institution = trim((string) ($data['institution'] ?? ''));
        if ($institution === '' && $institutionId !== null) {
            $institution = (string) (FinancialInstitutions::find($institutionId)['trade_name'] ?? '');
        }
        if (mb_strlen($institution) > 120) {
            throw new InvalidArgumentException('Use até 120 caracteres na instituição.');
        }
        $values = [
            in_array($opening, ['', '0', '0,00'], true) ? 0 : self::parseCents($opening),
            $cardKind, $type === 'credit_card' && $limit !== '' ? self::parseCents($limit) : null,
            $type === 'credit_card' ? $closingDay : null, $type === 'credit_card' ? $dueDay : null,
        ];
        if ($catalogReady) {
            $this->pdo->prepare(
                'INSERT INTO finance_accounts (financial_context_id, name, type, institution, institution_id, opening_balance_cents, card_kind, credit_limit_cents, closing_day, due_day) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$this->contextId, $name, $type, $institution ?: null, $institutionId, ...$values]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO finance_accounts (financial_context_id, name, type, institution, opening_balance_cents, card_kind, credit_limit_cents, closing_day, due_day) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$this->contextId, $name, $type, $institution ?: null, ...$values]);
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function transfer(array $data, ?int $userId): int
    {
        $from = $this->ownedId('finance_accounts', $data['account_id'] ?? null);
        $to = $this->ownedId('finance_accounts', $data['target_account_id'] ?? null);
        if ($from === null || $to === null || $from === $to) {
            throw new InvalidArgumentException('Escolha duas contas diferentes.');
        }
        $date = self::date($data['date'] ?? null);
        $description = trim((string) ($data['description'] ?? '')) ?: 'Transferência entre contas';
        $amount = self::parseCents($data['amount'] ?? null);
        $idempotency = is_string($data['idempotency_key'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $data['idempotency_key'])
            ? $data['idempotency_key'] : bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            // A mesma submissão não cria uma segunda transferência. O saldo continua derivado do extrato.
            $this->pdo->prepare(
                "INSERT INTO finance_entries
                 (financial_context_id, kind, description, amount_cents, due_date, paid_at, account_id, target_account_id, created_by, idempotency_key)
                 VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
            )->execute([$this->contextId, mb_substr($description, 0, 160), $amount, $date, $date, $from, $to, $userId, $idempotency]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Extrato do período: somente o que já foi pago, recebido ou transferido. */
    public function movements(string $start, string $end, string $kind = 'all'): array
    {
        $filter = in_array($kind, ['income', 'expense', 'transfer'], true) ? ' AND e.kind = ?' : '';
        $statement = $this->pdo->prepare(
            "SELECT e.id, e.kind, e.description, e.amount_cents, e.paid_at,
                    a.name AS account, t.name AS target_account, c.name AS category
             FROM finance_entries AS e
             INNER JOIN finance_accounts AS a ON a.id = e.account_id
             LEFT JOIN finance_accounts AS t ON t.id = e.target_account_id
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             WHERE e.financial_context_id = ? AND e.paid_at BETWEEN ? AND ?{$filter}
             ORDER BY e.paid_at DESC, e.id DESC"
        );
        $statement->execute(array_merge([$this->contextId, $start, $end], $filter === '' ? [] : [$kind]));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Período da query string: mês (?mes=AAAA-MM) ou ano (?visao=anual&ano=AAAA). */
    public static function period(array $query, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $names = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        if (($query['visao'] ?? '') === 'anual') {
            $year = (int) (is_string($query['ano'] ?? null) && preg_match('/^20\d{2}$/', $query['ano']) ? $query['ano'] : $today->format('Y'));
            $start = $today->setDate($year, 1, 1);
            $end = $today->setDate($year, 12, 31);
            return [
                'view' => 'anual', 'start' => $start, 'end' => $end, 'label' => (string) $year, 'long' => (string) $year,
                'short' => (string) $year, 'previous' => ['visao' => 'anual', 'ano' => $year - 1], 'next' => ['visao' => 'anual', 'ano' => $year + 1],
                'previousLabel' => (string) ($year - 1), 'nextLabel' => (string) ($year + 1),
                'other' => ['mes' => $year === (int) $today->format('Y') ? $today->format('Y-m') : $year . '-01'],
                'compareStart' => $start->modify('-1 year'), 'compareEnd' => $end->modify('-1 year'),
            ];
        }
        $month = is_string($query['mes'] ?? null) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $query['mes']) ? $query['mes'] : $today->format('Y-m');
        $start = new DateTimeImmutable($month . '-01');
        $previous = $start->modify('-1 month');
        $next = $start->modify('+1 month');
        $name = $names[(int) $start->format('n')];
        return [
            'view' => 'mensal', 'start' => $start, 'end' => $start->modify('last day of this month'),
            'label' => $name . ' ' . $start->format('Y'), 'long' => $name . ' de ' . $start->format('Y'), 'short' => $name,
            'previous' => ['mes' => $previous->format('Y-m')], 'next' => ['mes' => $next->format('Y-m')],
            'previousLabel' => $names[(int) $previous->format('n')], 'nextLabel' => $names[(int) $next->format('n')],
            'other' => ['visao' => 'anual', 'ano' => $start->format('Y')],
            'compareStart' => $previous, 'compareEnd' => $previous->modify('last day of this month'),
        ];
    }

    /** Indicadores da visão geral. Transferências não contam como receita nem despesa. */
    public function overview(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $compareStart, DateTimeImmutable $compareEnd): array
    {
        $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        $income = $this->summary('income', ...$range);
        $expense = $this->summary('expense', ...$range);
        $accounts = $this->accountsWithBalance();
        $balances = $this->balanceSummary($accounts);
        $balance = $balances['total_cents'];

        $due = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN due_date <= CURDATE() THEN amount_cents END), 0) AS today_total,
                    COUNT(CASE WHEN due_date <= CURDATE() THEN 1 END) AS today_count,
                    COALESCE(SUM(CASE WHEN due_date = CURDATE() + INTERVAL 1 DAY THEN amount_cents END), 0) AS tomorrow_total,
                    COUNT(CASE WHEN due_date = CURDATE() + INTERVAL 1 DAY THEN 1 END) AS tomorrow_count,
                    COALESCE(SUM(CASE WHEN due_date BETWEEN CURDATE() + INTERVAL 2 DAY AND CURDATE() + INTERVAL 7 DAY THEN amount_cents END), 0) AS week_total,
                    COUNT(CASE WHEN due_date BETWEEN CURDATE() + INTERVAL 2 DAY AND CURDATE() + INTERVAL 7 DAY THEN 1 END) AS week_count,
                    COALESCE(SUM(CASE WHEN due_date > CURDATE() + INTERVAL 7 DAY THEN amount_cents END), 0) AS later_total,
                    COUNT(CASE WHEN due_date > CURDATE() + INTERVAL 7 DAY THEN 1 END) AS later_count
             FROM finance_entries
             WHERE financial_context_id = ? AND kind = 'expense' AND paid_at IS NULL AND (status IS NULL OR status <> 'cancelled') AND due_date <= LAST_DAY(CURDATE() + INTERVAL 1 MONTH)"
        );
        $due->execute([$this->contextId]);

        return [
            'balance' => $balance,
            'reserved' => $balances['reserved_cents'],
            'available' => $balances['available_cents'],
            'shortfalls' => $balances['shortfalls'],
            'income_paid' => $income['paid'],
            'expense_paid' => $expense['paid'],
            'result' => $income['paid'] - $expense['paid'],
            'receivable' => $income['expected'],
            'receivable_count' => $this->openCount('income', ...$range),
            'payable' => $expense['expected'],
            'payable_count' => $this->openCount('expense', ...$range),
            'forecast' => $balance + $income['expected'] - $expense['expected'],
            'due' => array_map('intval', $due->fetch(PDO::FETCH_ASSOC)),
            'recent' => array_slice($this->movements('2000-01-01', date('Y-m-d')), 0, 5),
            'categories' => $this->expenseByCategory(...$range),
            'previous_categories' => $this->expenseByCategory($compareStart->format('Y-m-d'), $compareEnd->format('Y-m-d')),
            'accounts' => $accounts,
        ];
    }

    /** Receitas e despesas pagas por mês, dos últimos $months meses até $end. */
    public function monthlySeries(DateTimeImmutable $end, int $months = 6): array
    {
        $first = $end->modify('first day of this month')->modify('-' . ($months - 1) . ' month');
        $statement = $this->pdo->prepare(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, kind, SUM(amount_cents) AS total
             FROM finance_entries
             WHERE financial_context_id = ? AND kind IN ('income','expense') AND paid_at BETWEEN ? AND ?
             GROUP BY month, kind"
        );
        $statement->execute([$this->contextId, $first->format('Y-m-d'), $end->format('Y-m-t')]);
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $series[$first->modify("+{$i} month")->format('Y-m')] = ['income' => 0, 'expense' => 0];
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $series[$row['month']][$row['kind']] = (int) $row['total'];
        }
        return $series;
    }

    /** Lançamentos do período para exportação. */
    public function exportRows(string $start, string $end): array
    {
        $statement = $this->pdo->prepare(
            "SELECT e.due_date, e.paid_at, e.kind, e.description, c.name AS category, a.name AS account, t.name AS target_account, e.amount_cents
             FROM finance_entries AS e
             INNER JOIN finance_accounts AS a ON a.id = e.account_id
             LEFT JOIN finance_accounts AS t ON t.id = e.target_account_id
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             WHERE e.financial_context_id = ? AND e.due_date BETWEEN ? AND ?
             ORDER BY e.due_date, e.id"
        );
        $statement->execute([$this->contextId, $start, $end]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Relatório do período, por data de vencimento. */
    public function report(string $start, string $end): array
    {
        $statement = $this->pdo->prepare(
            "SELECT e.id, e.kind, e.description, e.counterparty_name, e.amount_cents, e.due_date, e.paid_at, e.status,
                    c.name AS category, c.color AS category_color, a.name AS account, t.name AS target_account,
                    COALESCE(s.settled_cents, 0) AS settled_cents
             FROM finance_entries AS e
             INNER JOIN finance_accounts AS a ON a.id = e.account_id
             LEFT JOIN finance_accounts AS t ON t.id = e.target_account_id
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             LEFT JOIN (SELECT entry_id, SUM(amount_cents) AS settled_cents FROM finance_entry_settlements WHERE financial_context_id = ? GROUP BY entry_id) AS s ON s.entry_id = e.id
             WHERE e.financial_context_id = ? AND e.due_date BETWEEN ? AND ?
             ORDER BY e.due_date, FIELD(e.kind, 'income', 'expense', 'transfer'), e.id"
        );
        $statement->execute([$this->contextId, $this->contextId, $start, $end]);
        return self::buildReport($statement->fetchAll(PDO::FETCH_ASSOC), date('Y-m-d'));
    }

    /** Agrupa os lançamentos por dia. Cancelados e transferências aparecem, mas não somam. */
    public static function buildReport(array $rows, string $today): array
    {
        $totals = ['income' => 0, 'income_paid' => 0, 'income_overdue' => 0, 'expense' => 0, 'expense_paid' => 0, 'expense_overdue' => 0];
        $days = [];
        $categories = [];
        $accumulated = 0;
        foreach ($rows as $row) {
            $amount = (int) $row['amount_cents'];
            $paid = $row['paid_at'] !== null ? $amount : min($amount, (int) $row['settled_cents']);
            $cancelled = $row['status'] === 'cancelled';
            $counts = !$cancelled && $row['kind'] !== 'transfer';
            $date = (string) $row['due_date'];
            $days[$date] ??= ['date' => $date, 'income' => 0, 'expense' => 0, 'result' => 0, 'accumulated' => 0, 'entries' => []];
            $days[$date]['entries'][] = $row + [
                'paid_cents' => $paid,
                'cancelled' => $cancelled,
                'overdue' => !$cancelled && $paid < $amount && $date < $today,
            ];
            if ($counts) {
                $kind = (string) $row['kind'];
                $totals[$kind] += $amount;
                $totals[$kind . '_paid'] += $paid;
                if ($date < $today) $totals[$kind . '_overdue'] += $amount - $paid;
                $days[$date][$kind] += $amount;
                if ($kind === 'expense') {
                    $name = (string) ($row['category'] ?? 'Sem categoria');
                    $categories[$name] ??= ['name' => $name, 'color' => $row['category_color'] ?? '#98a2b3', 'total' => 0];
                    $categories[$name]['total'] += $amount;
                }
            }
        }
        foreach ($days as &$day) {
            $day['result'] = $day['income'] - $day['expense'];
            $accumulated += $day['result'];
            $day['accumulated'] = $accumulated;
        }
        unset($day);
        usort($categories, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        return ['totals' => $totals, 'days' => array_values($days), 'categories' => $categories, 'count' => count($rows)];
    }

    /** Categorias com o total lançado no período. */
    public function categoriesWithTotals(string $start, string $end): array
    {
        $statement = $this->pdo->prepare(
            "SELECT c.id, c.name, c.kind, c.color, COALESCE(SUM(e.amount_cents), 0) AS total, COUNT(e.id) AS entries
             FROM finance_categories AS c
             LEFT JOIN finance_entries AS e ON e.category_id = c.id AND e.due_date BETWEEN ? AND ?
             WHERE c.financial_context_id = ?
             GROUP BY c.id ORDER BY c.kind DESC, c.name"
        );
        $statement->execute([$start, $end, $this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategory(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $kind = (string) ($data['category_kind'] ?? '');
        $color = (string) ($data['color'] ?? '#078b4c');
        if ($name === '' || mb_strlen($name) > 80 || !in_array($kind, ['income', 'expense'], true)) {
            throw new InvalidArgumentException('Informe o nome e o tipo da categoria.');
        }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $color = '#078b4c';
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM finance_categories WHERE financial_context_id = ? AND kind = ? AND name = ?');
        $exists->execute([$this->contextId, $kind, $name]);
        if ($exists->fetchColumn()) {
            throw new InvalidArgumentException('Já existe uma categoria com esse nome.');
        }
        $this->pdo->prepare('INSERT INTO finance_categories (financial_context_id, name, kind, color) VALUES (?, ?, ?, ?)')
            ->execute([$this->contextId, $name, $kind, $color]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Excluir a categoria mantém os lançamentos, que ficam sem categoria. */
    public function deleteCategory(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM finance_categories WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$id, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    /** Orçamento por categoria de despesa no mês, com o gasto até agora. */
    public function budgets(string $month): array
    {
        $statement = $this->pdo->prepare(
            "SELECT c.id AS category_id, c.name, c.color, COALESCE(b.amount_cents, 0) AS budget,
                    (SELECT COALESCE(SUM(e.amount_cents), 0) FROM finance_entries AS e
                     WHERE e.category_id = c.id AND e.due_date BETWEEN ? AND LAST_DAY(?)) AS spent
             FROM finance_categories AS c
             LEFT JOIN finance_budgets AS b ON b.category_id = c.id AND b.month = ?
             WHERE c.financial_context_id = ? AND c.kind = 'expense'
             ORDER BY budget DESC, c.name"
        );
        $statement->execute([$month . '-01', $month . '-01', $month, $this->contextId]);
        return array_map(static fn (array $row): array => ['budget' => (int) $row['budget'], 'spent' => (int) $row['spent']] + $row, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function saveBudget(array $data): void
    {
        $category = $this->ownedId('finance_categories', $data['category_id'] ?? null);
        $month = (string) ($data['mes'] ?? '');
        if ($category === null || !preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException('Escolha a categoria e o mês.');
        }
        $this->pdo->prepare(
            'INSERT INTO finance_budgets (financial_context_id, category_id, month, amount_cents) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents)'
        )->execute([$this->contextId, $category, $month, self::parseCents($data['amount'] ?? null)]);
    }

    public const GOAL_CATEGORIES = ['transporte' => 'Transporte', 'viagem' => 'Viagem', 'casa' => 'Casa', 'educacao' => 'Educação', 'investimento' => 'Investimento', 'outra' => 'Outra'];
    public const GOAL_ICONS = ['carro', 'aviao', 'casa', 'educacao', 'coracao', 'maleta', 'guardar', 'jogo', 'camera', 'carteira', 'alvo', 'mais'];

    /** Indica se a migration 009 (foto, categoria e local da meta) já foi aplicada. */
    public function supportsGoalDetails(): bool
    {
        return $this->pdo->query("SHOW COLUMNS FROM finance_goals LIKE 'image_path'")->fetchColumn() !== false;
    }

    public function goals(): array
    {
        $details = $this->supportsGoalDetails()
            ? "g.category, g.icon, g.monthly_contribution_cents, g.image_path, g.savings_place, g.institution, g.institution_id, i.trade_name AS institution_name, i.logo_file AS institution_logo, i.slug AS institution_slug"
            : "NULL AS category, NULL AS icon, NULL AS monthly_contribution_cents, NULL AS image_path, NULL AS savings_place, NULL AS institution, NULL AS institution_id, NULL AS institution_name, NULL AS institution_logo, NULL AS institution_slug";
        $join = $this->supportsGoalDetails() ? 'LEFT JOIN financial_institutions AS i ON i.id = g.institution_id' : '';
        $ready = GoalWallets::ready();
        $wallet = $ready ? 'g.account_id, g.color, ga.name AS account_name' : 'NULL AS account_id, NULL AS color, NULL AS account_name';
        $join .= $ready ? ' LEFT JOIN finance_accounts AS ga ON ga.id = g.account_id' : '';
        $statement = $this->pdo->prepare("SELECT g.id, g.name, g.purpose, g.created_at, g.target_cents, g.saved_cents, g.deadline, g.status, {$wallet}, {$details} FROM finance_goals AS g {$join} WHERE g.financial_context_id = ? AND g.status <> 'cancelled' ORDER BY g.status = 'completed', g.deadline IS NULL, g.deadline, g.id");
        $statement->execute([$this->contextId]);
        // saved_cents continua sendo o que foi registrado fora das contas; o guardado soma esse valor às reservas.
        $reserved = $ready ? (new GoalWallets($this->contextId))->reservedByGoal() : [];
        return array_map(static function (array $goal) use ($reserved): array {
            $goal['external_cents'] = (int) $goal['saved_cents'];
            $goal['reserved_cents'] = $reserved[(int) $goal['id']] ?? 0;
            $goal['saved_cents'] = $goal['external_cents'] + $goal['reserved_cents'];
            return $goal;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Quanto falta e quanto guardar por mês para chegar ao prazo. O ritmo é a divisão simples
     * do que falta pelos meses restantes, contando o mês atual, sem juros nem rendimento.
     *
     * @return array{percent:int,remaining_cents:int,done:bool,late:bool,months_left:?int,monthly_cents:?int}
     */
    public static function goalProgress(int $targetCents, int $savedCents, ?string $deadline, string $today): array
    {
        $done = $savedCents >= $targetCents;
        $remaining = max(0, $targetCents - $savedCents);
        $percent = $done ? 100 : min(99, (int) floor($savedCents / max(1, $targetCents) * 100));
        $late = !$done && $deadline !== null && $deadline < $today;
        $months = null;
        $monthly = null;
        if (!$done && !$late && $deadline !== null) {
            $from = new DateTimeImmutable($today);
            $to = new DateTimeImmutable($deadline);
            $months = ((int) $to->format('Y') - (int) $from->format('Y')) * 12 + (int) $to->format('n') - (int) $from->format('n') + 1;
            $monthly = (int) ceil($remaining / $months);
        }
        return ['percent' => $percent, 'remaining_cents' => $remaining, 'done' => $done, 'late' => $late, 'months_left' => $months, 'monthly_cents' => $monthly];
    }

    /** Curto até 1 ano, médio até 3 anos, longo acima disso. Prazo vencido conta como curto. */
    public static function goalTerm(?string $deadline, string $today): ?string
    {
        if ($deadline === null) {
            return null;
        }
        $from = new DateTimeImmutable($today);
        if ($deadline <= $from->modify('+1 year')->format('Y-m-d')) {
            return 'curto';
        }
        return $deadline <= $from->modify('+3 years')->format('Y-m-d') ? 'medio' : 'longo';
    }

    /**
     * Previsão e situação da meta, por mês. O ritmo é a média dos aportes dos últimos 90 dias;
     * sem aportes recentes, vale a contribuição planejada. Sem juros nem rendimento.
     *
     * @param list<array{amount_cents:int,contributed_at:string}> $contributions
     * @return array{pace_cents:?int,months:?int,finish:?string,status:string,title:string,text:string}
     */
    public static function goalForecast(int $targetCents, int $savedCents, ?string $deadline, ?int $plannedCents, array $contributions, int $freeCents, string $today): array
    {
        $from = new DateTimeImmutable($today);
        $since = $from->modify('-90 days')->format('Y-m-d');
        $recent = array_sum(array_map(static fn (array $c): int => $c['contributed_at'] >= $since ? $c['amount_cents'] : 0, $contributions));
        $pace = $recent > 0 ? (int) ceil($recent / 3) : ($plannedCents ?: null);
        $remaining = max(0, $targetCents - $savedCents);
        $result = ['pace_cents' => $pace, 'months' => null, 'finish' => null, 'status' => 'info', 'title' => '', 'text' => ''];
        if ($remaining === 0) {
            return ['status' => 'success', 'title' => 'Meta alcançada!', 'text' => 'Você juntou o valor completo.'] + $result;
        }
        $monthsTo = static fn (string $date): int => ((int) substr($date, 0, 4) - (int) $from->format('Y')) * 12 + (int) substr($date, 5, 2) - (int) $from->format('n') + 1;
        if ($pace !== null) {
            $result['months'] = (int) ceil($remaining / $pace);
            $result['finish'] = $from->modify('first day of this month')->modify('+' . ($result['months'] - 1) . ' months')->format('Y-m-d');
        }
        if ($deadline !== null && $deadline < $today) {
            return ['status' => 'warning', 'title' => 'Prazo encerrado', 'text' => 'Faltam ' . self::money($remaining) . '. Defina um novo prazo.'] + $result;
        }
        if ($pace === null) {
            return $savedCents > 0 || $deadline === null
                ? ['title' => 'Planeje o futuro', 'text' => 'Defina uma contribuição mensal para ver a previsão.'] + $result
                : ['title' => 'Comece agora', 'text' => 'Registre o primeiro aporte para acompanhar o ritmo.'] + $result;
        }
        $gain = 0;
        $extra = max(0, $freeCents);
        if ($extra > 0) {
            $gain = $result['months'] - (int) ceil($remaining / ($pace + $extra));
        }
        $canAnticipate = $gain > 0 ? ['title' => 'Você pode antecipar!', 'text' => 'Antecipe em até ' . $gain . ' ' . ($gain > 1 ? 'meses' : 'mês') . ' guardando mais ' . self::money($extra) . '/mês.'] : null;
        if ($deadline === null) {
            return ($canAnticipate ?? ['title' => 'No caminho certo', 'text' => 'Mantendo o ritmo de ' . self::money($pace) . '/mês.']) + $result;
        }
        $limit = $monthsTo($deadline);
        if ($result['months'] > $limit) {
            return ['status' => 'warning', 'title' => 'Acelere um pouco', 'text' => 'Aumente ' . self::money((int) ceil($remaining / $limit) - $pace) . '/mês para chegar no prazo.'] + $result;
        }
        $ahead = $limit - $result['months'];
        if ($ahead > 0) {
            return ['status' => 'success', 'title' => 'No ritmo certo!', 'text' => 'Deve alcançar ' . $ahead . ' ' . ($ahead > 1 ? 'meses' : 'mês') . ' antes do prazo.'] + $result;
        }
        return ($canAnticipate ?? ['status' => 'success', 'title' => 'No ritmo certo!', 'text' => 'Deve alcançar dentro do prazo.']) + $result;
    }

    /**
     * Valor guardado ao fim de cada um dos últimos meses, a partir dos aportes reais.
     *
     * @param list<array{amount_cents:int,contributed_at:string}> $contributions
     * @return list<int>
     */
    public static function goalTrend(int $savedCents, array $contributions, string $today, int $months = 6): array
    {
        $month = (new DateTimeImmutable($today))->modify('first day of this month');
        $points = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $month->modify("-{$i} months")->format('Y-m');
            $after = array_sum(array_map(static fn (array $c): int => substr($c['contributed_at'], 0, 7) > $key ? $c['amount_cents'] : 0, $contributions));
            array_unshift($points, max(0, $savedCents - $after));
        }
        return $points;
    }

    /** @param array<string,mixed>|null $photo */
    public function createGoal(array $data, ?array $photo = null, ?int $userId = null): int
    {
        [$name, $purpose, $target, $deadline] = $this->goalFields($data);
        $initial = trim((string) ($data['initial'] ?? ''));
        $initialCents = $initial === '' ? 0 : self::parseCents($initial);
        $ready = GoalWallets::ready();
        $details = $this->supportsGoalDetails();
        // A mesma submissão não cria uma segunda meta nem uma segunda carteira.
        $key = $ready && is_string($data['idempotency_key'] ?? null) ? GoalWallets::key($data['idempotency_key']) : null;
        if ($key !== null && ($existing = $this->goalByKey($key)) !== null) {
            return $existing;
        }
        [$accountId, $color] = $ready ? $this->goalWallet($data) : [null, null];
        $image = $details ? GoalPhotos::store($photo) : null;
        $this->pdo->beginTransaction();
        try {
            if (!$details) {
                $this->pdo->prepare('INSERT INTO finance_goals (financial_context_id, name, purpose, target_cents, deadline) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$this->contextId, $name, $purpose, $target, $deadline]);
            } else {
                [$place, $institution, $institutionId] = $this->goalPlace($data);
                [$category, $icon, $monthly] = $this->goalPlan($data);
                $this->pdo->prepare('INSERT INTO finance_goals (financial_context_id, name, purpose, category, icon, image_path, savings_place, institution, institution_id, target_cents, monthly_contribution_cents, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$this->contextId, $name, $purpose, $category, $icon, $image, $place, $institution, $institutionId, $target, $monthly, $deadline]);
            }
            $id = (int) $this->pdo->lastInsertId();
            if ($ready) {
                $this->pdo->prepare('UPDATE finance_goals SET account_id = ?, color = ?, idempotency_key = ? WHERE id = ?')->execute([$accountId, $color, $key, $id]);
            }
            if ($ready && $initialCents > 0 && $accountId !== null) {
                (new GoalWallets($this->contextId))->reserve($id, ['account_id' => $accountId, 'amount' => $initial, 'note' => 'Valor inicial'], $userId);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            GoalPhotos::remove($image);
            $existing = $key !== null && $error instanceof \PDOException && $error->getCode() === '23000' ? $this->goalByKey($key) : null;
            if ($existing !== null) {
                return $existing;
            }
            throw $error;
        }
        // Sem conta de origem, o valor inicial é o que já está guardado fora das contas.
        if ($initialCents > 0 && $accountId === null) {
            $this->addToGoal($id, $initial, $userId);
        }
        return $id;
    }

    private function goalByKey(string $key): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM finance_goals WHERE financial_context_id = ? AND idempotency_key = ?');
        $statement->execute([$this->contextId, $key]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array{0:?int,1:?string} Conta de referência da carteira e cor da meta. */
    private function goalWallet(array $data): array
    {
        $accountId = null;
        if (($data['account_id'] ?? '') !== '') {
            $accountId = $this->ownedId('finance_accounts', $data['account_id']);
            $cash = array_column(array_filter($this->accountsWithBalance(), static fn (array $a): bool => $a['type'] !== 'credit_card'), 'id');
            if ($accountId === null || !in_array($accountId, array_map('intval', $cash), true)) {
                throw new InvalidArgumentException('Escolha uma conta ativa, sem ser cartão de crédito.');
            }
        }
        $color = (string) ($data['color'] ?? '');
        return [$accountId, preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null];
    }

    /** Total guardado na meta: o registrado fora das contas mais as reservas. Pausada só muda quando retomada. */
    private function goalStatus(int $id, string $current, int $external, int $target): string
    {
        if ($current === 'paused') {
            return 'paused';
        }
        $reserved = GoalWallets::ready() ? ((new GoalWallets($this->contextId))->reservedByGoal()[$id] ?? 0) : 0;
        return $external + $reserved >= $target ? 'completed' : 'active';
    }

    /** @param array<string,mixed>|null $photo */
    public function updateGoal(int $id, array $data, ?array $photo = null): bool
    {
        [$name, $purpose, $target, $deadline] = $this->goalFields($data);
        $current = $this->goalRow($id);
        if ($current === null) {
            return false;
        }
        $status = $this->goalStatus($id, (string) $current['status'], (int) $current['saved_cents'], $target);
        if (GoalWallets::ready()) {
            [$accountId, $color] = $this->goalWallet($data);
            $this->pdo->prepare('UPDATE finance_goals SET account_id = ?, color = ? WHERE id = ? AND financial_context_id = ?')->execute([$accountId, $color, $id, $this->contextId]);
        }
        if (!$this->supportsGoalDetails()) {
            $this->pdo->prepare('UPDATE finance_goals SET name = ?, purpose = ?, target_cents = ?, deadline = ?, status = ? WHERE id = ? AND financial_context_id = ?')
                ->execute([$name, $purpose, $target, $deadline, $status, $id, $this->contextId]);
            return true;
        }
        [$place, $institution, $institutionId] = $this->goalPlace($data);
        [$category, $icon, $monthly] = $this->goalPlan($data);
        $image = $current['image_path'] ?? null;
        $new = GoalPhotos::store($photo);
        if ($new !== null || !empty($data['remove_photo'])) {
            GoalPhotos::remove($image);
            $image = $new;
        }
        $this->pdo->prepare('UPDATE finance_goals SET name = ?, purpose = ?, category = ?, icon = ?, image_path = ?, savings_place = ?, institution = ?, institution_id = ?, target_cents = ?, monthly_contribution_cents = ?, deadline = ?, status = ? WHERE id = ? AND financial_context_id = ?')
            ->execute([$name, $purpose, $category, $icon, $image, $place, $institution, $institutionId, $target, $monthly, $deadline, $status, $id, $this->contextId]);
        return true;
    }

    /** @return array{0:string,1:?string,2:int,3:?string} */
    private function goalFields(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Informe o nome da meta.');
        }
        $deadline = trim((string) ($data['deadline'] ?? ''));
        $purpose = trim((string) ($data['purpose'] ?? ''));
        return [$name, $purpose === '' ? null : mb_substr($purpose, 0, $this->supportsGoalDetails() ? 200 : 160), self::parseCents($data['amount'] ?? null), $deadline === '' ? null : self::date($deadline)];
    }

    /** @return array{0:?string,1:?string,2:?int} */
    private function goalPlace(array $data): array
    {
        $place = trim((string) ($data['savings_place'] ?? ''));
        $institution = trim((string) ($data['institution'] ?? ''));
        return [$place === '' ? null : mb_substr($place, 0, 120), $institution === '' ? null : mb_substr($institution, 0, 120), FinancialInstitutions::activeId($data['institution_id'] ?? null)];
    }

    /** @return array{0:?string,1:?string,2:?int} */
    private function goalPlan(array $data): array
    {
        $category = (string) ($data['category'] ?? '');
        $icon = (string) ($data['icon'] ?? '');
        $monthly = trim((string) ($data['monthly'] ?? ''));
        return [isset(self::GOAL_CATEGORIES[$category]) ? $category : null, in_array($icon, self::GOAL_ICONS, true) ? $icon : null, $monthly === '' ? null : self::parseCents($monthly)];
    }

    /**
     * Base real da análise: médias dos lançamentos pagos nos últimos três meses completos,
     * divididas pelos meses que têm lançamento, e as contribuições já planejadas em outras metas.
     *
     * @return array{months:int,income_cents:int,expense_cents:int,committed_cents:int,categories:list<array{name:string,cents:int}>}
     */
    public function goalBaseline(?string $today = null): array
    {
        $end = (new DateTimeImmutable($today ?? 'today'))->modify('first day of this month')->modify('-1 day');
        $start = $end->modify('first day of this month')->modify('-2 months');
        $range = [$this->contextId, $start->format('Y-m-d'), $end->format('Y-m-d')];
        $statement = $this->pdo->prepare("SELECT kind, SUM(amount_cents) AS total, DATE_FORMAT(paid_at, '%Y-%m') AS month FROM finance_entries WHERE financial_context_id = ? AND kind IN ('income','expense') AND paid_at BETWEEN ? AND ? AND (status IS NULL OR status <> 'cancelled') GROUP BY kind, month");
        $statement->execute($range);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $months = max(1, count(array_unique(array_column($rows, 'month'))));
        $totals = ['income' => 0, 'expense' => 0];
        foreach ($rows as $row) {
            $totals[$row['kind']] += (int) $row['total'];
        }
        $statement = $this->pdo->prepare("SELECT COALESCE(c.name, 'Sem categoria') AS name, SUM(e.amount_cents) AS total FROM finance_entries AS e LEFT JOIN finance_categories AS c ON c.id = e.category_id WHERE e.financial_context_id = ? AND e.kind = 'expense' AND e.paid_at BETWEEN ? AND ? AND (e.status IS NULL OR e.status <> 'cancelled') GROUP BY name ORDER BY total DESC LIMIT 3");
        $statement->execute($range);
        $categories = array_map(static fn (array $row): array => ['name' => (string) $row['name'], 'cents' => (int) round((int) $row['total'] / $months)], $statement->fetchAll(PDO::FETCH_ASSOC));
        $committed = 0;
        if ($this->supportsGoalDetails()) {
            $statement = $this->pdo->prepare("SELECT COALESCE(SUM(monthly_contribution_cents), 0) FROM finance_goals WHERE financial_context_id = ? AND status = 'active'");
            $statement->execute([$this->contextId]);
            $committed = (int) $statement->fetchColumn();
        }
        return ['months' => $rows === [] ? 0 : $months, 'income_cents' => (int) round($totals['income'] / $months), 'expense_cents' => (int) round($totals['expense'] / $months), 'committed_cents' => $committed, 'categories' => $categories];
    }

    private function goalRow(int $id): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled'");
        $statement->execute([$id, $this->contextId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Foto de uma meta do contexto atual. */
    public function goalPhoto(int $id): ?string
    {
        return $this->supportsGoalDetails() ? ($this->goalRow($id)['image_path'] ?? null) : null;
    }

    public function addToGoal(int $id, mixed $amount, ?int $userId = null, mixed $date = null): bool
    {
        $cents = self::parseCents($amount);
        $date = trim((string) $date);
        $date = $date === '' ? date('Y-m-d') : self::date($date);
        if ($date > date('Y-m-d')) {
            throw new InvalidArgumentException('A data do aporte não pode estar no futuro.');
        }
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT target_cents, saved_cents, status FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled' FOR UPDATE");
            $statement->execute([$id, $this->contextId]);
            $goal = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$goal) { $this->pdo->rollBack(); return false; }
            $saved = (int) $goal['saved_cents'] + $cents;
            $status = $this->goalStatus($id, (string) $goal['status'], $saved, (int) $goal['target_cents']);
            $this->pdo->prepare('UPDATE finance_goals SET saved_cents = ?, status = ? WHERE id = ? AND financial_context_id = ?')->execute([$saved, $status, $id, $this->contextId]);
            $this->pdo->prepare('INSERT INTO finance_goal_contributions (financial_context_id, goal_id, amount_cents, contributed_at, actor_user_id) VALUES (?, ?, ?, ?, ?)')->execute([$this->contextId, $id, $cents, $date, $userId]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * Aportes das metas informadas, do mais recente ao mais antigo.
     *
     * @param list<int> $goalIds
     * @return array<int,list<array{id:int,amount_cents:int,contributed_at:string}>>
     */
    public function goalContributions(array $goalIds): array
    {
        if ($goalIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($goalIds), '?'));
        $statement = $this->pdo->prepare("SELECT id, goal_id, amount_cents, contributed_at FROM finance_goal_contributions WHERE financial_context_id = ? AND goal_id IN ({$marks}) ORDER BY contributed_at DESC, id DESC");
        $statement->execute([$this->contextId, ...$goalIds]);
        $history = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $history[(int) $row['goal_id']][] = ['id' => (int) $row['id'], 'amount_cents' => (int) $row['amount_cents'], 'contributed_at' => $row['contributed_at']];
        }
        return $history;
    }

    /** Desfaz um aporte lançado por engano e devolve a meta ao saldo anterior. */
    public function deleteContribution(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT goal_id, amount_cents FROM finance_goal_contributions WHERE id = ? AND financial_context_id = ? FOR UPDATE');
            $statement->execute([$id, $this->contextId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $this->pdo->rollBack(); return false; }
            $statement = $this->pdo->prepare("SELECT target_cents, saved_cents, status FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled' FOR UPDATE");
            $statement->execute([$row['goal_id'], $this->contextId]);
            $goal = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$goal) { $this->pdo->rollBack(); return false; }
            $saved = max(0, (int) $goal['saved_cents'] - (int) $row['amount_cents']);
            $this->pdo->prepare('UPDATE finance_goals SET saved_cents = ?, status = ? WHERE id = ? AND financial_context_id = ?')->execute([$saved, $this->goalStatus((int) $row['goal_id'], (string) $goal['status'], $saved, (int) $goal['target_cents']), $row['goal_id'], $this->contextId]);
            $this->pdo->prepare('DELETE FROM finance_goal_contributions WHERE id = ? AND financial_context_id = ?')->execute([$id, $this->contextId]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteGoal(int $id, ?int $userId = null): bool
    {
        $photo = $this->goalPhoto($id);
        if (GoalWallets::ready()) {
            if (!(new GoalWallets($this->contextId))->cancelGoal($id, $userId)) {
                return false;
            }
        } else {
            $statement = $this->pdo->prepare("UPDATE finance_goals SET status = 'cancelled' WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled'");
            $statement->execute([$id, $this->contextId]);
            if ($statement->rowCount() !== 1) {
                return false;
            }
        }
        if ($photo !== null) {
            GoalPhotos::remove($photo);
            $this->pdo->prepare('UPDATE finance_goals SET image_path = NULL WHERE id = ? AND financial_context_id = ?')->execute([$id, $this->contextId]);
        }
        return true;
    }

    private function openCount(string $kind, string $start, string $end): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM finance_entries WHERE financial_context_id = ? AND kind = ? AND paid_at IS NULL AND (status IS NULL OR status <> \'cancelled\') AND due_date BETWEEN ? AND ?'
        );
        $statement->execute([$this->contextId, $kind, $start, $end]);
        return (int) $statement->fetchColumn();
    }

    public function expenseByCategory(string $start, string $end): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(c.name, 'Sem categoria') AS name, COALESCE(c.color, '#94a3b8') AS color, SUM(e.amount_cents) AS total
             FROM finance_entries AS e
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             WHERE e.financial_context_id = ? AND e.kind = 'expense' AND e.due_date BETWEEN ? AND ?
             GROUP BY name, color ORDER BY total DESC"
        );
        $statement->execute([$this->contextId, $start, $end]);
        return array_map(static function (array $row): array {
            $row['total'] = (int) $row['total'];
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function ownedId(string $table, mixed $id): ?int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }
        $statement = $this->pdo->prepare("SELECT id FROM {$table} WHERE id = ? AND financial_context_id = ?");
        $statement->execute([$id, $this->contextId]);
        return $statement->fetchColumn() === false ? null : $id;
    }

    private static function date(mixed $value): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        return $value;
    }
}
