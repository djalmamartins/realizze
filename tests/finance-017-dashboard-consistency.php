<?php
declare(strict_types=1);

/** Os números do Dashboard da empresa batem entre si e com Receber, inclusive com baixa parcial. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
use Moves\Services\Receivables;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK' : 'FALHOU') . " - {$message}\n";
    if (!$ok) $failed++;
};
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Dash', 'Teste', ?, 'x', 'active')")
    ->execute(['dash-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Dash Alfa', ''), Organizations::create($userId, 'Dash Beta', '')];
try {
    $context = FinanceContext::forOrganization($orgs[0]);
    $finance = new Finance($context);
    $receivables = new Receivables($context);
    $account = $finance->createAccount(['name' => 'Banco', 'type' => 'checking', 'opening_balance' => '1.000,00']);
    $month = new DateTimeImmutable('first day of this month');
    $start = $month->format('Y-m-d');
    $end = $month->modify('last day of this month')->format('Y-m-d');
    $today = date('Y-m-d');
    $key = static fn (): string => bin2hex(random_bytes(32));

    $service = (int) $receivables->create(['description' => 'Serviço', 'amount' => '1.000,00', 'due_date' => $today, 'competence' => date('Y-m'), 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => $key()], $userId)['id'];
    $receivables->settle($service, ['amount' => '400,00', 'received_at' => $today, 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => $key()], $userId);
    $finance->createEntry('income', ['description' => 'À vista', 'amount' => '250,00', 'due_date' => $today, 'account_id' => $account, 'paid' => '1'], $userId);
    $finance->createEntry('expense', ['description' => 'Aluguel', 'amount' => '300,00', 'due_date' => $today, 'account_id' => $account, 'paid' => '1'], $userId);
    $finance->createEntry('expense', ['description' => 'Energia', 'amount' => '120,00', 'due_date' => $end, 'account_id' => $account], $userId);

    $overview = $finance->overview(new DateTimeImmutable($start), new DateTimeImmutable($end), $month->modify('-1 month'), $month->modify('-1 day'));
    $receivable = $receivables->summary($start, $end);
    $check($overview['balance'] === 100000 + 40000 + 25000 - 30000, 'Saldo soma a baixa parcial, a receita à vista e a despesa paga');
    $check($overview['income_paid'] === 65000, 'Receitas recebidas incluem a baixa parcial');
    $check($overview['receivable'] === 60000 && $overview['receivable'] === $receivable['to_receive'], 'A receber é o saldo em aberto e bate com Receber');
    $check($overview['income_paid'] === $receivable['received'], 'Recebido do Dashboard bate com Receber');
    $check($overview['result'] === 65000 - 30000, 'Resultado é recebido menos pago');
    $check($overview['payable'] === 12000 && $overview['payable_count'] === 1, 'A pagar só tem a despesa em aberto');
    $check($overview['forecast'] === $overview['balance'] + 60000 - 12000, 'Fechamento previsto usa o saldo em aberto');
    $series = $finance->monthlySeries(new DateTimeImmutable($end));
    $check($series[$month->format('Y-m')]['income'] === 65000 && $series[$month->format('Y-m')]['expense'] === 30000, 'Gráfico mensal conta a baixa parcial no mês em que entrou');
    $sumKind = static fn (string $kind) => array_sum(array_column(array_filter($finance->cashMovements($start, $end), static fn (array $r): bool => $r['kind'] === $kind), 'amount_cents'));
    $check($sumKind('income') === 65000 && $sumKind('expense') === 30000, 'Fluxo de caixa realizado conta a baixa parcial na data em que entrou');
    $receivables->settle($service, ['amount' => '600,00', 'received_at' => $today, 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => $key()], $userId);
    $check($sumKind('income') === 125000, 'Quitar o restante soma só o que faltava, sem repetir o valor inteiro');
    $expense = $finance->summary('expense', $start, $end);
    $check($expense['paid'] === 30000 && $expense['expected'] === 12000, 'Despesas não mudam de critério');

    $other = new Finance(FinanceContext::forOrganization($orgs[1]));
    $empty = $other->overview(new DateTimeImmutable($start), new DateTimeImmutable($end), $month->modify('-1 month'), $month->modify('-1 day'));
    $check($empty['balance'] === 0 && $empty['income_paid'] === 0 && $empty['receivable'] === 0 && $empty['recent'] === [], 'Outra empresa do mesmo dono não vê nada');
} finally {
    foreach ($orgs as $org) {
        $ctx = FinanceContext::forOrganization($org);
        foreach (['finance_entry_settlements', 'finance_entries', 'finance_accounts', 'finance_categories'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE financial_context_id = ?")->execute([$ctx]);
        }
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$ctx]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo $failed === 0 ? "FIN-017: DASHBOARD CONSISTENTE.\n" : "FIN-017: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
