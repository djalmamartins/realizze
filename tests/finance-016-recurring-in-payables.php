<?php
declare(strict_types=1);

/** Assinatura automática entra em Pagar no próprio mês, sem duplicar e sem contar duas vezes na previsão. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\RecurringFinance;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'R', ?, 'x', 'active')")
    ->execute(['recur-' . bin2hex(random_bytes(6)) . '@example.invalid']);
$uid = (int) $pdo->lastInsertId();
$context = FinanceContext::forUser($uid);
try {
    $finance = new Finance($context);
    $service = new RecurringFinance($context);
    $account = $finance->createAccount(['name' => 'Conta', 'type' => 'checking']);
    $auto = $service->create(['kind' => 'expense', 'name' => 'Automática', 'amount' => '39,90', 'next_due_date' => date('Y-m-d', strtotime('+40 days')), 'frequency' => 'monthly', 'account_id' => $account, 'auto_create' => '1'], $uid);
    $pdo->prepare('UPDATE finance_recurring_items SET next_due_date = ? WHERE id = ?')->execute(['2031-03-03', $auto]);
    $pdo->prepare('DELETE FROM finance_recurring_occurrences WHERE recurring_item_id = ?')->execute([$auto]);
    $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = ?')->execute([$context]);
    $manual = $service->create(['kind' => 'expense', 'name' => 'Manual', 'amount' => '10,00', 'next_due_date' => '2031-03-05', 'frequency' => 'monthly', 'account_id' => $account], $uid);

    $check($service->generateDue(new DateTimeImmutable('2031-03-01'), 100) === 0, 'Antes do mês da cobrança nada é gerado');
    $check($service->generateDue(new DateTimeImmutable('2031-03-31'), 100) === 1, 'No mês da cobrança gera a automática');
    $check($service->generateDue(new DateTimeImmutable('2031-03-31'), 100) === 0, 'Abrir a tela de novo não duplica');
    $rows = $finance->entries('expense', '2031-03-01', '2031-03-31', 'open');
    $check(count($rows) === 1 && $rows[0]['description'] === 'Automática' && (int) $rows[0]['amount_cents'] === 3990, 'Aparece em Pagar como em aberto');
    $check($finance->summary('expense', '2031-03-01', '2031-03-31')['expected'] === 3990, 'Entra nos totais de Pagar');
    $planned = array_column($service->planned('expense', '2031-03-01', '2031-03-31'), 'name');
    $check($planned === ['Manual'], 'Gerada sai das previstas; a manual continua prevista');
    $projection = $finance->planProjection('2031-03-01', '2031-03-31');
    $check($projection['expense_expected'] === 3990 + 1000, 'Planejamento não conta duas vezes');
    $check(array_column($service->planned('expense', '2031-04-01', '2031-04-30'), 'name') === ['Automática', 'Manual'], 'Mês seguinte segue como previsto');
    $check((string) $manual !== '' && $finance->entries('expense', '2031-03-01', '2031-03-31', 'paid') === [], 'Nada é dado como pago');
} finally {
    $pdo->prepare('DELETE FROM finance_recurring_occurrences WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM finance_recurring_items WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM finance_accounts WHERE financial_context_id = ?')->execute([$context]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
}
echo "Assinaturas em Pagar aprovadas.\n";
