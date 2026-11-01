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
    public function entries(string $kind, string $start, string $end, string $status = 'all'): array
    {
        $filter = match ($status) {
            'open' => ' AND e.paid_at IS NULL',
            'paid' => ' AND e.paid_at IS NOT NULL',
            'overdue' => ' AND e.paid_at IS NULL AND e.due_date < CURDATE()',
            default => '',
        };
        $statement = $this->pdo->prepare(
            "SELECT e.id, e.description, e.amount_cents, e.due_date, e.paid_at,
                    c.name AS category, c.color AS category_color, a.name AS account
             FROM finance_entries AS e
             INNER JOIN finance_accounts AS a ON a.id = e.account_id
             LEFT JOIN finance_categories AS c ON c.id = e.category_id
             WHERE e.financial_context_id = ? AND e.kind = ? AND e.due_date BETWEEN ? AND ?{$filter}
             ORDER BY e.due_date, e.id"
        );
        $statement->execute([$this->contextId, $kind, $start, $end]);
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
             WHERE financial_context_id = ? AND kind = ? AND due_date BETWEEN ? AND ?'
        );
        $statement->execute([$this->contextId, $kind, $start, $end]);
        return array_map('intval', $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function createEntry(string $kind, array $data, ?int $userId): int
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
        $paidAt = !empty($data['paid']) ? date('Y-m-d') : null;

        $this->pdo->prepare(
            'INSERT INTO finance_entries
             (financial_context_id, kind, description, amount_cents, due_date, paid_at, account_id, category_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $this->contextId, $kind, $description, self::parseCents($data['amount'] ?? null),
            $due, $paidAt, $accountId, $categoryId, $userId,
        ]);
        return (int) $this->pdo->lastInsertId();
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

    public function delete(int $entryId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM finance_entries WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$entryId, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    /** Contas com saldo: inicial + entradas pagas - saídas pagas +/- transferências. */
    public function accountsWithBalance(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.id, a.name, a.type, a.institution, a.status, a.card_kind, a.credit_limit_cents, a.closing_day, a.due_day,
                    a.opening_balance_cents + COALESCE(SUM(CASE
                        WHEN e.paid_at IS NULL THEN 0
                        WHEN e.kind = 'income' AND e.account_id = a.id THEN e.amount_cents
                        WHEN e.kind = 'expense' AND e.account_id = a.id THEN -CAST(e.amount_cents AS SIGNED)
                        WHEN e.kind = 'transfer' AND e.account_id = a.id THEN -CAST(e.amount_cents AS SIGNED)
                        WHEN e.kind = 'transfer' AND e.target_account_id = a.id THEN e.amount_cents
                        ELSE 0 END), 0) AS balance_cents
             FROM finance_accounts AS a
             LEFT JOIN finance_entries AS e ON e.financial_context_id = a.financial_context_id
                 AND (e.account_id = a.id OR e.target_account_id = a.id)
             WHERE a.financial_context_id = ? AND a.archived_at IS NULL AND a.status = 'active'
             GROUP BY a.id ORDER BY a.type = 'credit_card', a.name"
        );
        $statement->execute([$this->contextId]);
        return array_map(static function (array $row): array {
            $row['balance_cents'] = (int) $row['balance_cents'];
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Saldo disponível: soma das contas, sem cartões de crédito. */
    public function availableBalance(): int
    {
        return array_sum(array_map(
            static fn (array $account): int => $account['type'] === 'credit_card' ? 0 : $account['balance_cents'],
            $this->accountsWithBalance()
        ));
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
        $this->pdo->prepare(
            'INSERT INTO finance_accounts (financial_context_id, name, type, institution, opening_balance_cents, card_kind, credit_limit_cents, closing_day, due_day) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $this->contextId, $name, $type, trim((string) ($data['institution'] ?? '')) ?: null,
            in_array($opening, ['', '0', '0,00'], true) ? 0 : self::parseCents($opening),
            $cardKind, $type === 'credit_card' && $limit !== '' ? self::parseCents($limit) : null,
            $type === 'credit_card' ? $closingDay : null, $type === 'credit_card' ? $dueDay : null,
        ]);
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
        $this->pdo->prepare(
            "INSERT INTO finance_entries
             (financial_context_id, kind, description, amount_cents, due_date, paid_at, account_id, target_account_id, created_by)
             VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$this->contextId, mb_substr($description, 0, 160), self::parseCents($data['amount'] ?? null), $date, $date, $from, $to, $userId]);
        return (int) $this->pdo->lastInsertId();
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
        $balance = array_sum(array_map(static fn (array $a): int => $a['type'] === 'credit_card' ? 0 : $a['balance_cents'], $accounts));

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
             WHERE financial_context_id = ? AND kind = 'expense' AND paid_at IS NULL AND due_date <= LAST_DAY(CURDATE() + INTERVAL 1 MONTH)"
        );
        $due->execute([$this->contextId]);

        return [
            'balance' => $balance,
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

    public function goals(): array
    {
        $statement = $this->pdo->prepare("SELECT id, name, purpose, target_cents, saved_cents, deadline, status FROM finance_goals WHERE financial_context_id = ? AND status <> 'cancelled' ORDER BY status = 'completed', deadline IS NULL, deadline, id");
        $statement->execute([$this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createGoal(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Informe o nome da meta.');
        }
        $deadline = trim((string) ($data['deadline'] ?? ''));
        $purpose = trim((string) ($data['purpose'] ?? ''));
        $this->pdo->prepare('INSERT INTO finance_goals (financial_context_id, name, purpose, target_cents, deadline) VALUES (?, ?, ?, ?, ?)')
            ->execute([$this->contextId, $name, $purpose === '' ? null : mb_substr($purpose, 0, 160), self::parseCents($data['amount'] ?? null), $deadline === '' ? null : self::date($deadline)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addToGoal(int $id, mixed $amount, ?int $userId = null): bool
    {
        $cents = self::parseCents($amount);
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT target_cents, saved_cents FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status = 'active' FOR UPDATE");
            $statement->execute([$id, $this->contextId]);
            $goal = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$goal) { $this->pdo->rollBack(); return false; }
            $saved = (int) $goal['saved_cents'] + $cents;
            $status = $saved >= (int) $goal['target_cents'] ? 'completed' : 'active';
            $this->pdo->prepare('UPDATE finance_goals SET saved_cents = ?, status = ? WHERE id = ? AND financial_context_id = ?')->execute([$saved, $status, $id, $this->contextId]);
            $this->pdo->prepare('INSERT INTO finance_goal_contributions (financial_context_id, goal_id, amount_cents, contributed_at, actor_user_id) VALUES (?, ?, ?, CURDATE(), ?)')->execute([$this->contextId, $id, $cents, $userId]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteGoal(int $id): bool
    {
        $statement = $this->pdo->prepare("UPDATE finance_goals SET status = 'cancelled' WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled'");
        $statement->execute([$id, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    private function openCount(string $kind, string $start, string $end): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM finance_entries WHERE financial_context_id = ? AND kind = ? AND paid_at IS NULL AND due_date BETWEEN ? AND ?'
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
