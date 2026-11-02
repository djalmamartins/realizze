<?php
declare(strict_types=1);

/** Cenário matemático integrado: contas, transferências, pagar, receber e relatórios. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use MovesCode\Model\Connection;

$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FALHOU: ' . $message);
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$userId = 0;
try {
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES('Teste','E2E',?,'x','active')")
        ->execute(['e2e-' . bin2hex(random_bytes(6)) . '@example.invalid']);
    $userId = (int) $pdo->lastInsertId();
    $finance = new Finance(FinanceContext::forUser($userId));
    $accountA = $finance->createAccount(['name' => 'Conta A E2E', 'type' => 'checking', 'opening_balance' => '5.000,00']);
    $accountB = $finance->createAccount(['name' => 'Conta B E2E', 'type' => 'checking', 'opening_balance' => '2.000,00']);
    $today = date('Y-m-d');
    $month = [date('Y-m-01'), date('Y-m-t')];

    $pay300 = $finance->createEntry('expense', ['description' => 'Pagar 300', 'amount' => '300,00', 'due_date' => $today, 'account_id' => $accountA], $userId);
    $finance->createEntry('expense', ['description' => 'Pagar 700', 'amount' => '700,00', 'due_date' => $today, 'account_id' => $accountA], $userId);
    $receive200 = $finance->createEntry('income', ['description' => 'Receber 200', 'amount' => '200,00', 'due_date' => $today, 'account_id' => $accountB], $userId);
    $finance->createEntry('income', ['description' => 'Receber 600', 'amount' => '600,00', 'due_date' => $today, 'account_id' => $accountB], $userId);
    $check($finance->availableBalance() === 700000, 'saldo inicial consolidado = R$ 7.000,00');
    $check($finance->summary('expense', ...$month)['expected'] === 100000, 'contas a pagar inicial = R$ 1.000,00');
    $check($finance->summary('income', ...$month)['expected'] === 80000, 'contas a receber inicial = R$ 800,00');

    $transfer = $finance->transfer([
        'account_id' => $accountA, 'target_account_id' => $accountB, 'amount' => '500,00',
        'date' => $today, 'idempotency_key' => bin2hex(random_bytes(32)),
    ], $userId);
    $balances = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$accountA] === 450000 && $balances[$accountB] === 250000, 'transferência: A = R$ 4.500,00 e B = R$ 2.500,00');
    $check($finance->availableBalance() === 700000, 'transferência mantém consolidado em R$ 7.000,00');

    $check($finance->settle($pay300), 'pagamento de R$ 300,00 liquidado');
    $balances = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$accountA] === 420000 && $finance->availableBalance() === 670000, 'pagamento: A = R$ 4.200,00 e consolidado = R$ 6.700,00');
    $check($finance->summary('expense', ...$month)['expected'] === 70000, 'contas a pagar após pagamento = R$ 700,00');

    $check($finance->settle($receive200), 'recebimento de R$ 200,00 liquidado');
    $balances = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$accountB] === 270000 && $finance->availableBalance() === 690000, 'recebimento: B = R$ 2.700,00 e consolidado = R$ 6.900,00');
    $check($finance->summary('income', ...$month)['expected'] === 60000, 'contas a receber após recebimento = R$ 600,00');

    $series = $finance->monthlySeries(new DateTimeImmutable('today'))[date('Y-m')];
    $check($series['expense'] === 30000 && $series['income'] === 20000, 'gráfico mensal usa apenas valores realizados');
    $check($finance->delete($transfer), 'exclusão da transferência aceita');
    $balances = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$accountA] === 470000 && $balances[$accountB] === 220000 && $finance->availableBalance() === 690000, 'exclusão estorna só a transferência');
} finally {
    if ($userId > 0) $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
