<?php
declare(strict_types=1);

/** Cenário da PF de ponta a ponta: renda, despesas planejadas e parcela de dívida coerentes em todos os módulos. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Debts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK' : 'FALHOU') . " - {$message}\n";
    $failed += $ok ? 0 : 1;
};
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'I', ?, 'x', 'active')")
    ->execute(['integ-' . bin2hex(random_bytes(6)) . '@example.invalid']);
$uid = (int) $pdo->lastInsertId();
$context = FinanceContext::forUser($uid);
try {
    $finance = new Finance($context);
    $debts = new Debts($context);
    $day = date('Y-m-d');
    $start = new DateTimeImmutable(date('Y-m-01'));
    $end = new DateTimeImmutable(date('Y-m-t'));
    $account = $finance->createAccount(['name' => 'Conta', 'type' => 'checking']);
    $savings = $finance->createAccount(['name' => 'Poupança', 'type' => 'checking']);
    $home = $finance->createCategory(['name' => 'Casa', 'category_kind' => 'expense']);
    $finance->saveBudget(['category_id' => $home, 'mes' => date('Y-m'), 'amount' => '3.000,00']);

    $finance->createEntry('income', ['description' => 'Salário', 'amount' => '5.000,00', 'due_date' => $day, 'account_id' => $account, 'paid' => '1'], $uid);
    $finance->createEntry('expense', ['description' => 'Aluguel', 'amount' => '3.000,00', 'due_date' => $day, 'account_id' => $account, 'category_id' => $home, 'paid' => '1'], $uid);
    $finance->transfer(['account_id' => $account, 'target_account_id' => $savings, 'amount' => '200,00', 'date' => $day, 'due_date' => $day], $uid);
    $debt = $debts->create(['creditor_name' => 'Banco', 'balance' => '6.000,00', 'monthly_payment' => '500,00', 'installments_remaining' => '12', 'next_due_date' => $day, 'account_id' => $account], $uid);
    $debts->registerPayment($debt, ['amount' => '500,00', 'paid_on' => $day, 'idempotency_key' => bin2hex(random_bytes(16))], $uid);

    $balances = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$account] === 130000 && $balances[$savings] === 20000, 'Contas: 5.000 − 3.000 − 500 − 200 de transferência');
    $check($finance->balanceSummary()['total_cents'] === 150000, 'Saldo total não muda com a transferência');
    $budget = array_values(array_filter($finance->budgets(date('Y-m')), static fn (array $r): bool => (int) $r['category_id'] === $home))[0];
    $check($budget['realized'] === 300000, 'Planejamento: parcela sem categoria não infla Casa');
    $plan = $finance->planProjection($start->format('Y-m-d'), $end->format('Y-m-d'));
    $check($plan['income_realized'] === 500000 && $plan['expense_realized'] === 350000 && $plan['result_realized'] === 150000, 'Planejamento: parcela paga conta uma vez');
    $check($debts->plannedPayments($start->format('Y-m-d'), $end->format('Y-m-d')) === 0, 'Planejamento: parcela paga não volta como prevista');
    $overview = $finance->overview($start, $end, $start->modify('-1 month'), $start->modify('-1 day'));
    echo 'Indicadores da visão geral: ' . json_encode(array_filter($overview, 'is_int')) . "\n";
    $summary = $finance->summary('expense', $start->format('Y-m-d'), $end->format('Y-m-d'));
    $check($summary['paid'] === 350000 && $summary['expected'] === 0, 'Pagar: mesma despesa total');
    $movements = $finance->movements($start->format('Y-m-d'), $end->format('Y-m-d'), 'expense');
    $check(array_sum(array_map(static fn (array $m): int => (int) $m['amount_cents'], $movements)) === 350000, 'Movimentações: mesma despesa total');
    $report = $finance->report($start->format('Y-m-d'), $end->format('Y-m-d'));
    echo 'Chaves do relatório: ' . implode(',', array_keys($report)) . "\n";
    $check($debts->find($debt)['balance_cents'] === 550000, 'Dívida: saldo abatido');
    $check($debts->panorama()['monthly_cents'] === 50000, 'Dívida: parcela mensal');

    // Cancelado: todos os módulos precisam concordar.
    $cancelled = $finance->createEntry('expense', ['description' => 'Cancelada', 'amount' => '77,00', 'due_date' => $day, 'account_id' => $account, 'category_id' => $home], $uid);
    $pdo->prepare("UPDATE finance_entries SET status = 'cancelled' WHERE id = ?")->execute([$cancelled]);
    $byCategory = array_sum(array_column($finance->expenseByCategory($start->format('Y-m-d'), $end->format('Y-m-d')), 'total'));
    $check($byCategory === 350000, 'Gasto por categoria ignora cancelado (obtido ' . $byCategory . ')');
    $listed = array_map('intval', array_column($finance->entries('expense', $start->format('Y-m-d'), $end->format('Y-m-d'), 'open'), 'id'));
    $check(!in_array($cancelled, $listed, true), 'Lista de contas em aberto ignora cancelado');
} finally {
    $pdo->prepare('DELETE FROM finance_debts WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
}
echo $failed === 0 ? "Integração da PF aprovada.\n" : "Integração da PF: {$failed} falha(s).\n";
exit($failed === 0 ? 0 : 1);
