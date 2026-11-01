<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/**
 * Dívidas, pagamentos, negociações e diagnóstico de um contexto financeiro
 * pessoal. Toda consulta e alteração é limitada a $contextId, recebido apenas
 * de FinanceContext. Cálculos ficam em DebtPlanner.
 */
final class Debts
{
    public const TYPES = [
        'credit_card' => 'Cartão de crédito', 'overdraft' => 'Cheque especial', 'personal_loan' => 'Empréstimo pessoal',
        'payroll_loan' => 'Consignado', 'vehicle' => 'Financiamento de veículo', 'mortgage' => 'Financiamento imobiliário',
        'student' => 'Financiamento estudantil', 'tax' => 'Impostos e tributos', 'utility' => 'Contas de consumo',
        'informal' => 'Empréstimo com pessoa conhecida', 'other' => 'Outra',
    ];
    public const STATUSES = [
        'current' => 'Em dia', 'overdue' => 'Atrasada', 'negotiating' => 'Em negociação',
        'agreement' => 'Em acordo', 'paid' => 'Quitada', 'cancelled' => 'Cancelada',
    ];
    public const COLLATERALS = [
        'none' => 'Sem garantia', 'vehicle' => 'Veículo', 'property' => 'Imóvel',
        'payroll' => 'Desconto em folha', 'guarantor' => 'Avalista ou fiador', 'other' => 'Outra garantia',
    ];
    public const NEGOTIATION_STATUSES = [
        'proposal' => 'Proposta', 'sent' => 'Enviada', 'accepted' => 'Aceita',
        'rejected' => 'Recusada', 'broken' => 'Acordo rompido', 'completed' => 'Acordo cumprido',
    ];

    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        $this->pdo = Connection::get();
    }

    /** Dívidas não arquivadas, abertas primeiro. */
    public function all(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.*, i.trade_name AS institution_name, i.slug AS institution_slug, i.logo_file AS institution_logo,
                    a.name AS account_name,
                    (SELECT COALESCE(SUM(p.amount_cents), 0) FROM finance_debt_payments p WHERE p.debt_id = d.id) AS paid_cents
             FROM finance_debts d
             LEFT JOIN financial_institutions i ON i.id = d.institution_id
             LEFT JOIN finance_accounts a ON a.id = d.account_id AND a.financial_context_id = d.financial_context_id
             WHERE d.financial_context_id = ? AND d.archived_at IS NULL
             ORDER BY d.status IN ('paid','cancelled'), d.monthly_rate IS NULL, d.monthly_rate DESC, d.balance_cents DESC"
        );
        $statement->execute([$this->contextId]);
        return array_map(static function (array $row): array {
            foreach (['balance_cents', 'paid_cents'] as $key) {
                $row[$key] = (int) $row[$key];
            }
            foreach (['original_amount_cents', 'monthly_payment_cents', 'installments_remaining'] as $key) {
                $row[$key] = $row[$key] === null ? null : (int) $row[$key];
            }
            $row['monthly_rate'] = $row['monthly_rate'] === null ? null : (float) $row['monthly_rate'];
            $row['cet_annual'] = $row['cet_annual'] === null ? null : (float) $row['cet_annual'];
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Dívidas que ainda contam para o plano. */
    public function open(): array
    {
        return array_values(array_filter($this->all(), static fn (array $d): bool => !in_array($d['status'], ['paid', 'cancelled'], true) && $d['balance_cents'] > 0));
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $debt) {
            if ((int) $debt['id'] === $id) {
                return $debt;
            }
        }
        return null;
    }

    public function create(array $data, ?int $userId): int
    {
        $values = $this->validated($data);
        $this->pdo->prepare(
            'INSERT INTO finance_debts (financial_context_id, creditor_name, institution_id, account_id, debt_type, original_amount_cents, balance_cents,
                monthly_rate, cet_annual, monthly_payment_cents, installments_remaining, next_due_date, status, collateral, risk_notes, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$this->contextId, ...array_values($values), $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($this->find($id) === null) {
            return false;
        }
        $values = $this->validated($data);
        $columns = implode(' = ?, ', array_keys($values)) . ' = ?';
        $statement = $this->pdo->prepare("UPDATE finance_debts SET {$columns} WHERE id = ? AND financial_context_id = ?");
        $statement->execute([...array_values($values), $id, $this->contextId]);
        return true;
    }

    public function archive(int $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE finance_debts SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND financial_context_id = ? AND archived_at IS NULL');
        $statement->execute([$id, $this->contextId]);
        return $statement->rowCount() === 1;
    }

    /**
     * Registra um pagamento e abate o saldo. Pode apontar para um lançamento de
     * despesa já existente (entry_id) para não duplicar a obrigação.
     */
    public function registerPayment(int $debtId, array $data, ?int $userId): int
    {
        $amount = Finance::parseCents($data['amount'] ?? '');
        $paidOn = self::date($data['paid_on'] ?? '');
        $entryId = null;
        if (($data['entry_id'] ?? '') !== '' && ($data['entry_id'] ?? null) !== null) {
            $entryId = filter_var($data['entry_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($entryId === false || !$this->linkableEntry($entryId)) {
                throw new InvalidArgumentException('Escolha um lançamento de despesa seu que ainda não esteja vinculado a outra dívida.');
            }
        }
        $notes = self::text($data['notes'] ?? '', 500);

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT balance_cents, status FROM finance_debts WHERE id = ? AND financial_context_id = ? AND archived_at IS NULL FOR UPDATE");
            $statement->execute([$debtId, $this->contextId]);
            $debt = $statement->fetch(PDO::FETCH_ASSOC);
            if ($debt === false || in_array($debt['status'], ['paid', 'cancelled'], true)) {
                throw new InvalidArgumentException('Dívida não encontrada ou já encerrada.');
            }
            $before = (int) $debt['balance_cents'];
            $after = max(0, $before - $amount);
            $this->pdo->prepare(
                'INSERT INTO finance_debt_payments (debt_id, financial_context_id, amount_cents, paid_on, entry_id, balance_before_cents, balance_after_cents, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$debtId, $this->contextId, $amount, $paidOn, $entryId, $before, $after, $notes, $userId]);
            $paymentId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare("UPDATE finance_debts SET balance_cents = ?, status = IF(? = 0, 'paid', status) WHERE id = ? AND financial_context_id = ?")
                ->execute([$after, $after, $debtId, $this->contextId]);
            $this->pdo->commit();
            return $paymentId;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function payments(int $debtId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.*, e.description AS entry_description FROM finance_debt_payments p
             LEFT JOIN finance_entries e ON e.id = p.entry_id AND e.financial_context_id = p.financial_context_id
             WHERE p.debt_id = ? AND p.financial_context_id = ? ORDER BY p.paid_on DESC, p.id DESC'
        );
        $statement->execute([$debtId, $this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Despesas do contexto ainda não vinculadas a pagamentos de dívida (últimos 6 meses e próximos 2). */
    public function linkableEntries(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT e.id, e.description, e.amount_cents, e.due_date, e.paid_at FROM finance_entries e
             LEFT JOIN finance_debt_payments p ON p.entry_id = e.id
             WHERE e.financial_context_id = ? AND e.kind = 'expense' AND p.id IS NULL
               AND (e.status IS NULL OR e.status <> 'cancelled')
               AND e.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
             ORDER BY e.due_date DESC LIMIT 200"
        );
        $statement->execute([$this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createNegotiation(int $debtId, array $data, ?int $userId): int
    {
        $debt = $this->find($debtId);
        if ($debt === null) {
            throw new InvalidArgumentException('Dívida não encontrada.');
        }
        $down = trim((string) ($data['down_payment'] ?? '')) === '' ? 0 : Finance::parseCents($data['down_payment']);
        $installments = filter_var($data['installments'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 420]]);
        if ($installments === false) {
            throw new InvalidArgumentException('Informe de 0 a 420 parcelas.');
        }
        $value = $installments > 0 ? Finance::parseCents($data['installment'] ?? '') : 0;
        if ($down === 0 && $installments === 0) {
            throw new InvalidArgumentException('Informe a entrada ou as parcelas da proposta.');
        }
        $status = array_key_exists((string) ($data['status'] ?? ''), self::NEGOTIATION_STATUSES) ? (string) $data['status'] : 'proposal';
        $origin = ($data['origin'] ?? '') === 'creditor' ? 'creditor' : 'user';
        $first = trim((string) ($data['first_due_date'] ?? '')) === '' ? null : self::date($data['first_due_date']);
        $this->pdo->prepare(
            'INSERT INTO finance_debt_negotiations (debt_id, financial_context_id, status, origin, down_payment_cents, installments, installment_cents, first_due_date, balance_at_proposal_cents, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$debtId, $this->contextId, $status, $origin, $down, max(1, $installments), $value, $first, $debt['balance_cents'], self::text($data['notes'] ?? '', 1000), $userId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->syncDebtStatus($debtId, $status);
        return $id;
    }

    public function updateNegotiationStatus(int $negotiationId, string $status): bool
    {
        if (!array_key_exists($status, self::NEGOTIATION_STATUSES)) {
            throw new InvalidArgumentException('Situação inválida.');
        }
        $statement = $this->pdo->prepare('SELECT debt_id FROM finance_debt_negotiations WHERE id = ? AND financial_context_id = ?');
        $statement->execute([$negotiationId, $this->contextId]);
        $debtId = $statement->fetchColumn();
        if ($debtId === false) {
            return false;
        }
        $this->pdo->prepare('UPDATE finance_debt_negotiations SET status = ? WHERE id = ? AND financial_context_id = ?')->execute([$status, $negotiationId, $this->contextId]);
        $this->syncDebtStatus((int) $debtId, $status);
        return true;
    }

    public function negotiations(int $debtId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM finance_debt_negotiations WHERE debt_id = ? AND financial_context_id = ? ORDER BY created_at DESC, id DESC');
        $statement->execute([$debtId, $this->contextId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Diagnóstico salvo, ou null. */
    public function profile(): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM finance_debt_profiles WHERE financial_context_id = ?');
        $statement->execute([$this->contextId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        foreach (['net_income_cents', 'essential_expenses_cents', 'variable_expenses_cents', 'other_commitments_cents', 'emergency_reserve_cents'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        return $row;
    }

    public function saveProfile(array $data, ?int $userId): void
    {
        $money = static fn (string $key): int => in_array(trim((string) ($data[$key] ?? '')), ['', '0', '0,00'], true) ? 0 : Finance::parseCents($data[$key]);
        $values = [
            $money('net_income'), $money('essential_expenses'), $money('variable_expenses'),
            $money('other_commitments'), $money('emergency_reserve'),
            ($data['income_stability'] ?? '') === 'variable' ? 'variable' : 'stable',
        ];
        if ($values[0] <= 0) {
            throw new InvalidArgumentException('Informe sua renda líquida mensal.');
        }
        $this->pdo->prepare(
            'INSERT INTO finance_debt_profiles (financial_context_id, net_income_cents, essential_expenses_cents, variable_expenses_cents, other_commitments_cents, emergency_reserve_cents, income_stability, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE net_income_cents = VALUES(net_income_cents), essential_expenses_cents = VALUES(essential_expenses_cents),
                variable_expenses_cents = VALUES(variable_expenses_cents), other_commitments_cents = VALUES(other_commitments_cents),
                emergency_reserve_cents = VALUES(emergency_reserve_cents), income_stability = VALUES(income_stability), updated_by = VALUES(updated_by)'
        )->execute([$this->contextId, ...$values, $userId]);
    }

    /**
     * Médias mensais dos últimos 3 meses fechados, a partir dos lançamentos pagos.
     * Usadas só como sugestão no formulário do diagnóstico.
     *
     * @return array{income_cents:int,expense_cents:int,months:int}
     */
    public function suggestedAverages(?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $end = $today->modify('first day of this month')->modify('-1 day');
        $start = $end->modify('first day of this month')->modify('-2 months');
        $statement = $this->pdo->prepare(
            "SELECT kind, COALESCE(SUM(amount_cents), 0) AS total, COUNT(DISTINCT DATE_FORMAT(paid_at, '%Y-%m')) AS months
             FROM finance_entries WHERE financial_context_id = ? AND kind IN ('income','expense') AND paid_at BETWEEN ? AND ?
               AND (status IS NULL OR status <> 'cancelled')
             GROUP BY kind"
        );
        $statement->execute([$this->contextId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $result = ['income_cents' => 0, 'expense_cents' => 0, 'months' => 0];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['kind'] . '_cents'] = (int) round((int) $row['total'] / 3);
            $result['months'] = max($result['months'], (int) $row['months']);
        }
        return $result;
    }

    private function syncDebtStatus(int $debtId, string $negotiationStatus): void
    {
        $map = ['proposal' => 'negotiating', 'sent' => 'negotiating', 'accepted' => 'agreement', 'broken' => 'overdue'];
        if (isset($map[$negotiationStatus])) {
            $this->pdo->prepare("UPDATE finance_debts SET status = ? WHERE id = ? AND financial_context_id = ? AND status NOT IN ('paid','cancelled')")
                ->execute([$map[$negotiationStatus], $debtId, $this->contextId]);
        }
    }

    private function linkableEntry(int $entryId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT e.id FROM finance_entries e LEFT JOIN finance_debt_payments p ON p.entry_id = e.id
             WHERE e.id = ? AND e.financial_context_id = ? AND e.kind = 'expense' AND p.id IS NULL"
        );
        $statement->execute([$entryId, $this->contextId]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed> colunas na ordem do INSERT */
    private function validated(array $data): array
    {
        $creditor = self::text($data['creditor_name'] ?? '', 160);
        $institutionId = FinancialInstitutions::activeId($data['institution_id'] ?? null);
        if ($creditor === null && $institutionId !== null) {
            $creditor = (string) (FinancialInstitutions::find($institutionId)['trade_name'] ?? '');
        }
        if ($creditor === null || $creditor === '') {
            throw new InvalidArgumentException('Informe o credor.');
        }
        $accountId = null;
        if (($data['account_id'] ?? '') !== '' && ($data['account_id'] ?? null) !== null) {
            $accountId = filter_var($data['account_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $statement = $this->pdo->prepare('SELECT id FROM finance_accounts WHERE id = ? AND financial_context_id = ?');
            $statement->execute([$accountId === false ? 0 : $accountId, $this->contextId]);
            if ($accountId === false || $statement->fetchColumn() === false) {
                throw new InvalidArgumentException('Conta ou cartão não encontrado.');
            }
        }
        $type = (string) ($data['debt_type'] ?? 'other');
        if (!array_key_exists($type, self::TYPES)) {
            throw new InvalidArgumentException('Escolha o tipo da dívida.');
        }
        $status = (string) ($data['status'] ?? 'current');
        if (!array_key_exists($status, self::STATUSES)) {
            throw new InvalidArgumentException('Escolha a situação da dívida.');
        }
        $collateral = (string) ($data['collateral'] ?? 'none');
        if (!array_key_exists($collateral, self::COLLATERALS)) {
            throw new InvalidArgumentException('Escolha a garantia.');
        }
        $optionalMoney = static fn (string $key): ?int => trim((string) ($data[$key] ?? '')) === '' ? null : Finance::parseCents($data[$key]);
        $balance = Finance::parseCents($data['balance'] ?? '');
        $rate = self::optionalRate($data['monthly_rate'] ?? '', 'taxa mensal');
        $cet = self::optionalRate($data['cet_annual'] ?? '', 'CET anual', 1000);
        if ($rate === null && $cet !== null) {
            $rate = DebtPlanner::annualToMonthly($cet);
        }
        $installments = trim((string) ($data['installments_remaining'] ?? '')) === '' ? null
            : filter_var($data['installments_remaining'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 600]]);
        if ($installments === false) {
            throw new InvalidArgumentException('Informe um número de parcelas válido.');
        }
        $due = trim((string) ($data['next_due_date'] ?? '')) === '' ? null : self::date($data['next_due_date']);
        return [
            'creditor_name' => $creditor,
            'institution_id' => $institutionId,
            'account_id' => $accountId,
            'debt_type' => $type,
            'original_amount_cents' => $optionalMoney('original_amount'),
            'balance_cents' => $balance,
            'monthly_rate' => $rate,
            'cet_annual' => $cet,
            'monthly_payment_cents' => $optionalMoney('monthly_payment'),
            'installments_remaining' => $installments,
            'next_due_date' => $due,
            'status' => $status,
            'collateral' => $collateral,
            'risk_notes' => self::text($data['risk_notes'] ?? '', 500),
            'notes' => self::text($data['notes'] ?? '', 1000),
        ];
    }

    private static function optionalRate(mixed $value, string $label, float $max = 100): ?float
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{1,4}(\.\d{1,4})?$/', $value) || (float) $value > $max) {
            throw new InvalidArgumentException("Informe a {$label} em %, por exemplo 2,5.");
        }
        return (float) $value;
    }

    private static function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Use no máximo {$max} caracteres.");
        }
        return $value;
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
