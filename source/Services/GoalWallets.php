<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use Throwable;

/**
 * Carteira virtual de cada meta: dinheiro reservado de uma conta, sem criar conta, receita ou despesa.
 * O saldo reservado é sempre a soma do histórico. Saldo disponível = saldo total - saldo reservado.
 * Ordem das travas: contas e depois metas, sempre por id, antes de qualquer leitura comum.
 */
final class GoalWallets
{
    public const TYPES = ['reserve' => 'Reserva', 'release' => 'Resgate', 'transfer_out' => 'Transferência enviada', 'transfer_in' => 'Transferência recebida', 'expense' => 'Pagamento', 'adjustment' => 'Ajuste autorizado', 'reversal' => 'Estorno'];

    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        $this->pdo = Connection::get();
    }

    /** Indica se a migration 012 já foi aplicada. */
    public static function ready(): bool
    {
        return Connection::get()->query("SHOW TABLES LIKE 'finance_goal_wallet_operations'")->fetchColumn() !== false;
    }

    /** @return array<int,int> */
    public function reservedByAccount(): array
    {
        return $this->sumBy('account_id');
    }

    /** @return array<int,int> */
    public function reservedByGoal(): array
    {
        return $this->sumBy('goal_id');
    }

    /** @return array<int,int> */
    private function sumBy(string $column): array
    {
        $statement = $this->pdo->prepare("SELECT {$column}, SUM(delta_cents) FROM finance_goal_wallet_operations WHERE financial_context_id = ? GROUP BY {$column}");
        $statement->execute([$this->contextId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * Quanto cada meta guarda em cada conta, só onde ainda há reserva.
     *
     * @return array<int,array<int,int>>
     */
    public function goalAccounts(): array
    {
        $statement = $this->pdo->prepare('SELECT goal_id, account_id, SUM(delta_cents) AS total FROM finance_goal_wallet_operations WHERE financial_context_id = ? GROUP BY goal_id, account_id HAVING total > 0');
        $statement->execute([$this->contextId]);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['goal_id']][(int) $row['account_id']] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Histórico por meta, do mais recente ao mais antigo.
     *
     * @return array<int,list<array<string,mixed>>>
     */
    public function history(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT o.id, o.goal_id, o.type, o.delta_cents, o.operation_key, o.occurred_on, o.note, o.entry_id, o.reverses_id, a.name AS account, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS actor,
                    EXISTS (SELECT 1 FROM finance_goal_wallet_operations AS r WHERE r.reverses_id = o.id) AS reversed
             FROM finance_goal_wallet_operations AS o
             INNER JOIN finance_accounts AS a ON a.id = o.account_id
             LEFT JOIN users AS u ON u.id = o.actor_user_id
             WHERE o.financial_context_id = ? ORDER BY o.occurred_on DESC, o.id DESC"
        );
        $statement->execute([$this->contextId]);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['delta_cents'] = (int) $row['delta_cents'];
            $row['reversed'] = (bool) $row['reversed'];
            $result[(int) $row['goal_id']][] = $row;
        }
        return $result;
    }

    /** @return array<string,mixed> Reserva de dinheiro da conta. Em repetição do mesmo envio, 'duplicate' vem verdadeiro. */
    public function reserve(int $goalId, array $data, ?int $userId, ?string $type = 'reserve'): array
    {
        $cents = Finance::parseCents($data['amount'] ?? null);
        $date = $this->pastDate($data['date'] ?? null);
        $key = self::key($data['idempotency_key'] ?? null);
        return $this->atomic(function () use ($goalId, $data, $userId, $cents, $date, $key, $type): array {
            $accountId = $this->lockAccount($data['account_id'] ?? null);
            $goal = $this->lockGoals([$goalId])[$goalId];
            if ($this->seen($key, $goalId)) {
                return ['duplicate' => true];
            }
            if ($goal['status'] === 'paused') {
                throw new InvalidArgumentException('Esta meta está pausada. Retome a meta para guardar mais.');
            }
            $account = $this->accountRow($accountId);
            if ($cents > $account['available_cents']) {
                throw new InvalidArgumentException('Saldo disponível insuficiente em ' . $account['name'] . ': ' . Finance::money(max(0, $account['available_cents'])) . '.');
            }
            $this->record($goalId, $accountId, (string) $type, $cents, $key, $date, $data['note'] ?? null, $userId);
            $this->syncStatus($goalId);
            return ['duplicate' => false];
        });
    }

    /** @return array<string,mixed> Devolve à conta parte ou todo o valor reservado. */
    public function release(int $goalId, array $data, ?int $userId): array
    {
        $cents = Finance::parseCents($data['amount'] ?? null);
        $date = $this->pastDate($data['date'] ?? null);
        $key = self::key($data['idempotency_key'] ?? null);
        return $this->atomic(function () use ($goalId, $data, $userId, $cents, $date, $key): array {
            $accountId = $this->lockAccount($data['account_id'] ?? null);
            $this->lockGoals([$goalId]);
            if ($this->seen($key, $goalId)) {
                return ['duplicate' => true];
            }
            $this->requireReserved($goalId, $accountId, $cents);
            $this->record($goalId, $accountId, 'release', -$cents, $key, $date, $data['note'] ?? null, $userId);
            $this->syncStatus($goalId);
            return ['duplicate' => false];
        });
    }

    /** @return array<string,mixed> Passa a reserva de uma meta para outra, na mesma conta e no mesmo contexto. */
    public function transfer(int $fromGoalId, array $data, ?int $userId): array
    {
        $toGoalId = (int) filter_var($data['target_goal_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($toGoalId <= 0 || $toGoalId === $fromGoalId) {
            throw new InvalidArgumentException('Escolha outra meta para receber o valor.');
        }
        $cents = Finance::parseCents($data['amount'] ?? null);
        $date = $this->pastDate($data['date'] ?? null);
        $key = self::key($data['idempotency_key'] ?? null);
        return $this->atomic(function () use ($fromGoalId, $toGoalId, $data, $userId, $cents, $date, $key): array {
            $accountId = $this->lockAccount($data['account_id'] ?? null);
            $goals = $this->lockGoals([$fromGoalId, $toGoalId]);
            if ($this->seen($key, $fromGoalId)) {
                return ['duplicate' => true];
            }
            if ($goals[$toGoalId]['status'] === 'paused') {
                throw new InvalidArgumentException('A meta de destino está pausada.');
            }
            $this->requireReserved($fromGoalId, $accountId, $cents);
            $this->record($fromGoalId, $accountId, 'transfer_out', -$cents, $key, $date, $data['note'] ?? null, $userId);
            $this->record($toGoalId, $accountId, 'transfer_in', $cents, $key, $date, $data['note'] ?? null, $userId);
            $this->syncStatus($fromGoalId);
            $this->syncStatus($toGoalId);
            return ['duplicate' => false];
        });
    }

    /** @return array<string,mixed> Paga uma despesa com a reserva: o saldo e a reserva caem juntos, e o disponível fica igual. */
    public function payExpense(int $goalId, array $data, ?int $userId): array
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 160) {
            throw new InvalidArgumentException('Informe uma descrição de até 160 caracteres.');
        }
        $cents = Finance::parseCents($data['amount'] ?? null);
        $date = $this->pastDate($data['date'] ?? null);
        $key = self::key($data['idempotency_key'] ?? null);
        $category = (int) filter_var($data['category_id'] ?? 0, FILTER_VALIDATE_INT);
        return $this->atomic(function () use ($goalId, $data, $userId, $description, $cents, $date, $key, $category): array {
            $accountId = $this->lockAccount($data['account_id'] ?? null);
            $this->lockGoals([$goalId]);
            if ($this->seen($key, $goalId)) {
                return ['duplicate' => true];
            }
            $this->requireReserved($goalId, $accountId, $cents);
            $categoryId = null;
            if ($category > 0) {
                $statement = $this->pdo->prepare('SELECT id FROM finance_categories WHERE id = ? AND financial_context_id = ?');
                $statement->execute([$category, $this->contextId]);
                $categoryId = $statement->fetchColumn() === false ? null : $category;
            }
            $this->pdo->prepare(
                "INSERT INTO finance_entries (financial_context_id, kind, description, amount_cents, due_date, paid_at, account_id, category_id, created_by, idempotency_key)
                 VALUES (?, 'expense', ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$this->contextId, $description, $cents, $date, $date, $accountId, $categoryId, $userId, hash('sha256', 'goal-pay|' . $key)]);
            $entryId = (int) $this->pdo->lastInsertId();
            $this->record($goalId, $accountId, 'expense', -$cents, $key, $date, $description, $userId, $entryId);
            $this->syncStatus($goalId);
            return ['duplicate' => false, 'entry_id' => $entryId];
        });
    }

    /**
     * Estorna uma operação com linhas novas. Pagamento: apaga a despesa e devolve a reserva.
     * Transferência: estorna as duas pernas. Falha se o valor já tiver sido usado.
     */
    public function reverse(int $operationId, ?int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT * FROM finance_goal_wallet_operations WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$operationId, $this->contextId]);
        $operation = $statement->fetch(PDO::FETCH_ASSOC);
        if ($operation === false) {
            return false;
        }
        if ($operation['type'] === 'reversal') {
            throw new InvalidArgumentException('Um estorno não pode ser estornado.');
        }
        $statement = $this->pdo->prepare("SELECT * FROM finance_goal_wallet_operations WHERE financial_context_id = ? AND operation_key = ? AND type <> 'reversal'");
        $statement->execute([$this->contextId, $operation['operation_key']]);
        $legs = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $this->atomic(function () use ($legs, $operation, $userId): bool {
            $accountId = (int) $this->lockAccount($operation['account_id']);
            $goalIds = array_map(static fn (array $leg): int => (int) $leg['goal_id'], $legs);
            $cancelled = false;
            $locked = $this->pdo->prepare('SELECT status FROM finance_goals WHERE id = ? FOR UPDATE');
            foreach (array_unique($goalIds) as $goalId) {
                $locked->execute([$goalId]);
                $cancelled = $cancelled || $locked->fetchColumn() === 'cancelled';
            }
            if ($cancelled && $operation['type'] !== 'expense') {
                throw new InvalidArgumentException('Esta meta foi excluída, e as reservas dela já voltaram para as contas.');
            }
            $marks = implode(',', array_map(static fn (array $leg): int => (int) $leg['id'], $legs));
            if ($this->pdo->query("SELECT 1 FROM finance_goal_wallet_operations WHERE reverses_id IN ({$marks}) LIMIT 1")->fetchColumn() !== false) {
                throw new InvalidArgumentException('Esta operação já foi estornada.');
            }
            // Só devolver à conta dinheiro que continua lá: um resgate estornado volta a ocupar o disponível.
            if ($operation['type'] === 'release' && -(int) $operation['delta_cents'] > $this->accountRow($accountId)['available_cents']) {
                throw new InvalidArgumentException('O saldo disponível da conta não cobre o estorno deste resgate.');
            }
            foreach ($legs as $leg) {
                if ((int) $leg['delta_cents'] > 0) {
                    $this->requireReserved((int) $leg['goal_id'], $accountId, (int) $leg['delta_cents'], 'Esse valor já saiu da reserva. Resgate ou transfira de volta antes de estornar.');
                }
            }
            foreach ($legs as $leg) {
                $this->record((int) $leg['goal_id'], $accountId, 'reversal', -(int) $leg['delta_cents'], hash('sha256', 'reversal|' . $leg['id']), date('Y-m-d'), $leg['note'], $userId, null, (int) $leg['id']);
            }
            // Meta já excluída não guarda nada: o valor da despesa estornada fica disponível na conta.
            if ($cancelled) {
                $this->record((int) $operation['goal_id'], $accountId, 'release', (int) $operation['delta_cents'], hash('sha256', 'reversal-release|' . $operation['id']), date('Y-m-d'), 'Meta excluída', $userId);
            }
            if ($operation['entry_id'] !== null) {
                $this->pdo->prepare("DELETE FROM finance_entries WHERE id = ? AND financial_context_id = ? AND kind = 'expense'")->execute([$operation['entry_id'], $this->contextId]);
            }
            foreach (array_unique($goalIds) as $goalId) {
                $this->syncStatus($goalId);
            }
            return true;
        });
    }

    /** Operação que pagou este lançamento com uma meta, se houver. */
    public function operationForEntry(int $entryId): ?int
    {
        $statement = $this->pdo->prepare("SELECT id FROM finance_goal_wallet_operations WHERE entry_id = ? AND financial_context_id = ? AND type = 'expense'");
        $statement->execute([$entryId, $this->contextId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Cancela a meta: devolve todas as reservas às contas, com registro no histórico, e encerra a programação. */
    public function cancelGoal(int $goalId, ?int $userId): bool
    {
        $statement = $this->pdo->prepare("SELECT account_id, SUM(delta_cents) AS total FROM finance_goal_wallet_operations WHERE goal_id = ? AND financial_context_id = ? GROUP BY account_id HAVING total > 0 ORDER BY account_id");
        $statement->execute([$goalId, $this->contextId]);
        $accounts = array_map('intval', array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'account_id'));
        return $this->atomic(function () use ($goalId, $userId, $accounts): bool {
            foreach ($accounts as $accountId) {
                $this->pdo->prepare('SELECT id FROM finance_accounts WHERE id = ? FOR UPDATE')->execute([$accountId]);
            }
            $statement = $this->pdo->prepare("SELECT id FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled' FOR UPDATE");
            $statement->execute([$goalId, $this->contextId]);
            if ($statement->fetchColumn() === false) {
                return false;
            }
            $statement = $this->pdo->prepare('SELECT account_id, SUM(delta_cents) AS total FROM finance_goal_wallet_operations WHERE goal_id = ? GROUP BY account_id HAVING total > 0');
            $statement->execute([$goalId]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->record($goalId, (int) $row['account_id'], 'release', -(int) $row['total'], hash('sha256', 'cancel|' . $goalId . '|' . $row['account_id']), date('Y-m-d'), 'Meta excluída', $userId);
            }
            $this->pdo->prepare("UPDATE finance_goals SET status = 'cancelled' WHERE id = ?")->execute([$goalId]);
            $this->pdo->prepare("UPDATE finance_goal_schedules SET status = 'canceled' WHERE goal_id = ?")->execute([$goalId]);
            return true;
        });
    }

    /** Pausa ou retoma a meta. Pausada, não recebe reservas; o que já está guardado continua guardado. */
    public function setPaused(int $goalId, bool $paused): bool
    {
        return $this->atomic(function () use ($goalId, $paused): bool {
            $goal = $this->lockGoals([$goalId])[$goalId];
            $this->pdo->prepare('UPDATE finance_goals SET status = ? WHERE id = ?')->execute([$paused ? 'paused' : 'active', $goalId]);
            if (!$paused) {
                $this->syncStatus($goalId);
            }
            return $goal['status'] !== 'cancelled';
        });
    }

    /** @return array<int,array<string,mixed>> Programações não canceladas, por meta. */
    public function schedules(): array
    {
        $statement = $this->pdo->prepare("SELECT s.*, a.name AS account FROM finance_goal_schedules AS s INNER JOIN finance_accounts AS a ON a.id = s.account_id WHERE s.financial_context_id = ? AND s.status <> 'canceled'");
        $statement->execute([$this->contextId]);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['goal_id']] = $row;
        }
        return $result;
    }

    public function saveSchedule(int $goalId, array $data, ?int $userId, ?string $today = null): void
    {
        $cents = Finance::parseCents($data['amount'] ?? null);
        $day = filter_var($data['day'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
        if ($day === false) {
            throw new InvalidArgumentException('Informe um dia entre 1 e 31.');
        }
        $this->atomic(function () use ($goalId, $data, $userId, $cents, $day, $today): void {
            $accountId = $this->lockAccount($data['account_id'] ?? null);
            $this->lockGoals([$goalId]);
            $this->accountRow($accountId);
            $this->pdo->prepare(
                "INSERT INTO finance_goal_schedules (financial_context_id, goal_id, account_id, amount_cents, day_of_month, next_run_on, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
                 ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), amount_cents = VALUES(amount_cents), day_of_month = VALUES(day_of_month), next_run_on = VALUES(next_run_on), status = 'active'"
            )->execute([$this->contextId, $goalId, $accountId, $cents, $day, self::nextRun($day, $today ?? date('Y-m-d'), true), $userId]);
        });
    }

    public function setScheduleStatus(int $goalId, string $status): bool
    {
        if (!in_array($status, ['active', 'paused', 'canceled'], true)) {
            return false;
        }
        $statement = $this->pdo->prepare('UPDATE finance_goal_schedules SET status = ? WHERE goal_id = ? AND financial_context_id = ?');
        $statement->execute([$status, $goalId, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    /** Próximo dia de aporte. O dia 31 vira o último dia dos meses curtos. */
    public static function nextRun(int $day, string $from, bool $includeToday = false): string
    {
        $month = (new DateTimeImmutable($from))->modify('first day of this month');
        foreach ([$month, $month->modify('+1 month')] as $candidate) {
            $date = $candidate->setDate((int) $candidate->format('Y'), (int) $candidate->format('n'), min($day, (int) $candidate->format('t')))->format('Y-m-d');
            if ($date > $from || ($includeToday && $date === $from)) {
                return $date;
            }
        }
        return $date;
    }

    /**
     * Executa os aportes vencidos. Cada data roda uma única vez. Sem saldo disponível, o aporte do mês não acontece e fica registrado.
     *
     * @return array{done:int,skipped:int}
     */
    public static function processDue(?string $today = null, int $limit = 200): array
    {
        $today ??= date('Y-m-d');
        $pdo = Connection::get();
        $statement = $pdo->prepare("SELECT * FROM finance_goal_schedules WHERE status = 'active' AND next_run_on <= ? ORDER BY next_run_on, id LIMIT {$limit}");
        $statement->execute([$today]);
        $totals = ['done' => 0, 'skipped' => 0];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $schedule) {
            $wallets = new self((int) $schedule['financial_context_id']);
            try {
                $wallets->reserve((int) $schedule['goal_id'], [
                    'account_id' => $schedule['account_id'], 'amount' => number_format((int) $schedule['amount_cents'] / 100, 2, ',', ''),
                    'date' => min($schedule['next_run_on'], $today), 'note' => 'Aporte programado', 'idempotency_key' => hash('sha256', 'schedule|' . $schedule['id'] . '|' . $schedule['next_run_on']),
                ], $schedule['created_by'] === null ? null : (int) $schedule['created_by']);
                $result = 'done';
                $totals['done']++;
            } catch (InvalidArgumentException) {
                $result = 'skipped';
                $totals['skipped']++;
            }
            $pdo->prepare('UPDATE finance_goal_schedules SET next_run_on = ?, last_run_on = ?, last_result = ? WHERE id = ?')
                ->execute([self::nextRun((int) $schedule['day_of_month'], max($schedule['next_run_on'], $today)), $today, $result, $schedule['id']]);
        }
        return $totals;
    }

    public static function key(mixed $value): string
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $value) === 1 ? $value : bin2hex(random_bytes(32));
    }

    /** Roda dentro da transação aberta, se houver: a criação da meta com reserva inicial é uma só operação. */
    public function atomic(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function lockAccount(mixed $id): int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $statement = $this->pdo->prepare('SELECT id FROM finance_accounts WHERE id = ? AND financial_context_id = ? FOR UPDATE');
        $statement->execute([$id === false ? 0 : $id, $this->contextId]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Escolha uma conta.');
        }
        return (int) $id;
    }

    /** @return array<int,array<string,mixed>> */
    private function lockGoals(array $ids): array
    {
        sort($ids);
        $result = [];
        $statement = $this->pdo->prepare("SELECT id, status FROM finance_goals WHERE id = ? AND financial_context_id = ? AND status <> 'cancelled' FOR UPDATE");
        foreach ($ids as $id) {
            $statement->execute([$id, $this->contextId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new InvalidArgumentException('Meta não encontrada.');
            }
            $result[$id] = $row;
        }
        return $result;
    }

    /** Conta ativa que pode guardar reservas. Cartão de crédito não tem saldo para reservar. */
    private function accountRow(int $accountId): array
    {
        foreach ((new Finance($this->contextId))->accountsWithBalance() as $account) {
            if ((int) $account['id'] === $accountId && $account['type'] !== 'credit_card') {
                return $account;
            }
        }
        throw new InvalidArgumentException('Escolha uma conta ativa, sem ser cartão de crédito.');
    }

    private function requireReserved(int $goalId, int $accountId, int $cents, string $message = ''): void
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(delta_cents), 0) FROM finance_goal_wallet_operations WHERE goal_id = ? AND account_id = ? AND financial_context_id = ?');
        $statement->execute([$goalId, $accountId, $this->contextId]);
        $reserved = (int) $statement->fetchColumn();
        if ($cents > $reserved) {
            throw new InvalidArgumentException($message ?: 'A meta tem só ' . Finance::money($reserved) . ' reservados nesta conta.');
        }
    }

    private function seen(string $key, int $goalId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM finance_goal_wallet_operations WHERE financial_context_id = ? AND operation_key = ? AND goal_id = ?');
        $statement->execute([$this->contextId, $key, $goalId]);
        return $statement->fetchColumn() !== false;
    }

    private function record(int $goalId, int $accountId, string $type, int $delta, string $key, string $date, mixed $note, ?int $userId, ?int $entryId = null, ?int $reverses = null): void
    {
        $note = trim((string) $note);
        $this->pdo->prepare(
            'INSERT INTO finance_goal_wallet_operations (financial_context_id, goal_id, account_id, type, delta_cents, operation_key, entry_id, reverses_id, occurred_on, note, actor_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$this->contextId, $goalId, $accountId, $type, $delta, $key, $entryId, $reverses, $date, $note === '' ? null : mb_substr($note, 0, 255), $userId]);
    }

    /** A meta chega a "concluída" pelo total guardado; pausada só muda quando retomada. */
    private function syncStatus(int $goalId): void
    {
        $this->pdo->prepare(
            "UPDATE finance_goals AS g SET g.status = IF(g.saved_cents + (SELECT COALESCE(SUM(o.delta_cents), 0) FROM finance_goal_wallet_operations AS o WHERE o.goal_id = g.id) >= g.target_cents, 'completed', 'active')
             WHERE g.id = ? AND g.status NOT IN ('paused','cancelled')"
        )->execute([$goalId]);
    }

    private function pastDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        if ($value > date('Y-m-d')) {
            throw new InvalidArgumentException('A data não pode estar no futuro.');
        }
        return $value;
    }
}
