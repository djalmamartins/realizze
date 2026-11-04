<?php
declare(strict_types=1);

/** Editar e arquivar contas sem mexer no saldo, isolado por empresa. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
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
$refused = static function (callable $call): bool {
    try { $call(); return false; } catch (InvalidArgumentException) { return true; }
};
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Conta', 'Teste', ?, 'x', 'active')")
    ->execute(['contas-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Contas Alfa', ''), Organizations::create($userId, 'Contas Beta', '')];
try {
    $finance = new Finance(FinanceContext::forOrganization($orgs[0]));
    $other = new Finance(FinanceContext::forOrganization($orgs[1]));
    $main = $finance->createAccount(['name' => 'Principal', 'type' => 'checking', 'opening_balance' => '500,00']);
    $spare = $finance->createAccount(['name' => 'Reserva', 'type' => 'savings', 'opening_balance' => '200,00']);
    $card = $finance->createAccount(['name' => 'Cartão', 'type' => 'credit_card', 'credit_limit' => '3.000,00', 'closing_day' => '5', 'due_day' => '12']);
    $find = static fn (int $id): ?array => array_values(array_filter($finance->accountsWithBalance(), static fn (array $a): bool => (int) $a['id'] === $id))[0] ?? null;
    $check((int) $find($card)['closing_day'] === 5 && (int) $find($card)['credit_limit_cents'] === 300000, 'Cartão novo guarda limite, fechamento e vencimento');

    $check($finance->updateAccount($main, ['name' => 'Conta movimento', 'institution' => 'Banco X', 'type' => 'credit_card', 'opening_balance' => '9.999,00']), 'Edição aceita');
    $row = $find($main);
    $check($row['name'] === 'Conta movimento' && $row['institution'] === 'Banco X', 'Nome e instituição mudam');
    $check($row['type'] === 'checking' && $row['balance_cents'] === 50000 && $row['credit_limit_cents'] === null, 'Tipo, saldo e campos de cartão não mudam numa conta comum');
    $finance->updateAccount($card, ['name' => 'Cartão PJ', 'credit_limit' => '4.500,00', 'closing_day' => '28', 'due_day' => '5']);
    $check((int) $find($card)['credit_limit_cents'] === 450000 && (int) $find($card)['closing_day'] === 28 && (int) $find($card)['due_day'] === 5, 'Cartão muda limite e datas');
    $check($refused(static fn () => $finance->updateAccount($card, ['name' => 'X', 'closing_day' => '40'])), 'Dia fora de 1 a 31 é recusado');
    $check($refused(static fn () => $finance->updateAccount($main, ['name' => ''])), 'Nome vazio é recusado');
    $check(!$other->updateAccount($main, ['name' => 'Invadida']) && $find($main)['name'] === 'Conta movimento', 'Outra empresa não edita a conta');
    $check(!$other->archiveAccount($spare) && $find($spare) !== null, 'Outra empresa não arquiva a conta');

    $check($refused(static fn () => $finance->archiveAccount($spare)), 'Conta com saldo não é arquivada');
    $finance->transfer(['account_id' => $spare, 'target_account_id' => $main, 'amount' => '200,00', 'date' => date('Y-m-d')], $userId);
    $open = $finance->createEntry('expense', ['description' => 'Boleto', 'amount' => '10,00', 'due_date' => date('Y-m-d', strtotime('+5 days')), 'account_id' => $spare], $userId);
    $check($refused(static fn () => $finance->archiveAccount($spare)), 'Conta com lançamento em aberto não é arquivada');
    $finance->delete($open, $userId);
    $total = $finance->balanceSummary()['total_cents'];
    $check($finance->archiveAccount($spare) && $find($spare) === null, 'Conta zerada e sem pendência é arquivada');
    $check($finance->balanceSummary()['total_cents'] === $total && $total === 70000, 'Saldo consolidado não muda ao arquivar');
    $report = $finance->report(date('Y-m-01'), date('Y-m-t'));
    $check($report['days'] !== [], 'Transferência da conta arquivada continua no relatório');
    $check(!$finance->updateAccount($spare, ['name' => 'Volta']), 'Conta arquivada não é editada');
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
echo $failed === 0 ? "FIN-018: CONTAS APROVADAS.\n" : "FIN-018: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
