<?php
declare(strict_types=1);

/** Conciliação: sugestões pela conta, valor e sentido; conciliar, ignorar e desfazer sem mexer em saldo. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\BankReconciliation;
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
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Conc', 'Teste', ?, 'x', 'active')")
    ->execute(['conc-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Conc Alfa', ''), Organizations::create($userId, 'Conc Beta', '')];
try {
    $ctx = FinanceContext::forOrganization($orgs[0]);
    $finance = new Finance($ctx);
    $service = new BankReconciliation($ctx);
    $bank = $finance->createAccount(['name' => 'Banco', 'type' => 'checking', 'opening_balance' => '1.000,00']);
    $other = $finance->createAccount(['name' => 'Outro', 'type' => 'checking']);
    $rent = $finance->createEntry('expense', ['description' => 'Aluguel', 'amount' => '500,00', 'due_date' => '2026-03-05', 'account_id' => $bank, 'paid' => '1'], $userId);
    $finance->createEntry('expense', ['description' => 'Aluguel outra conta', 'amount' => '500,00', 'due_date' => '2026-03-05', 'account_id' => $other, 'paid' => '1'], $userId);
    $sale = $finance->createEntry('income', ['description' => 'Venda', 'amount' => '500,00', 'due_date' => '2026-03-06', 'account_id' => $bank, 'paid' => '1'], $userId);
    $finance->createEntry('expense', ['description' => 'Longe', 'amount' => '500,00', 'due_date' => '2026-04-30', 'account_id' => $bank, 'paid' => '1'], $userId);
    $pdo->prepare("INSERT INTO finance_reconciliation_imports (financial_context_id, account_id, original_name, stored_path, mime_type, checksum, imported_by) VALUES (?, ?, 'x.csv', '/dev/null', 'text/csv', ?, ?)")
        ->execute([$ctx, $bank, bin2hex(random_bytes(32)), $userId]);
    $import = (int) $pdo->lastInsertId();
    $line = static function (string $date, int $cents) use ($pdo, $ctx, $import, $bank): int {
        $pdo->prepare('INSERT INTO finance_reconciliation_lines (financial_context_id, import_id, account_id, occurred_on, description, amount_cents, external_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$ctx, $import, $bank, $date, 'Linha', $cents, bin2hex(random_bytes(8))]);
        return (int) $pdo->lastInsertId();
    };
    $out = $line('2026-03-06', -50000);
    $in = $line('2026-03-06', 50000);
    $none = $line('2026-03-06', -1234);
    $lines = $service->lines();
    $suggest = $service->suggestions($lines);
    $check(array_column($suggest[$out], 'id') === [$rent], 'Saída sugere só a despesa da mesma conta, valor e semana');
    $check(array_column($suggest[$in], 'id') === [$sale], 'Entrada sugere só a receita');
    $check($suggest[$none] === [], 'Valor sem lançamento igual não tem sugestão');

    $balance = $finance->balanceSummary()['total_cents'];
    $check($refused(static fn () => $service->review($out, $sale, 'matched', $userId)), 'Não concilia saída com receita');
    $service->review($out, $rent, 'matched', $userId);
    $check($service->counts()['matched'] === 1 && $finance->balanceSummary()['total_cents'] === $balance, 'Conciliar não muda saldo');
    $again = $line('2026-03-06', -50000);
    $check(($service->suggestions($service->lines())[$again] ?? null) === [], 'Lançamento já conciliado não é sugerido de novo');
    $check($refused(static fn () => $service->review($again, $rent, 'matched', $userId)), 'Não concilia o mesmo lançamento duas vezes');
    $service->review($out, null, 'pending', $userId);
    $check($service->counts()['matched'] === 0 && array_column($service->suggestions($service->lines())[$again], 'id') === [$rent], 'Desfazer libera o lançamento');
    $service->review($none, null, 'ignored', $userId);
    $check(count($service->lines(['status' => 'ignored'])) === 1 && count($service->lines(['status' => 'pending'])) === 3, 'Filtro por situação');
    $beta = new BankReconciliation(FinanceContext::forOrganization($orgs[1]));
    $check($beta->lines() === [] && $refused(static fn () => $beta->review($out, $rent, 'matched', $userId)), 'Outra empresa não vê nem revisa as linhas');
} finally {
    foreach ($orgs as $org) {
        $c = FinanceContext::forOrganization($org);
        foreach (['finance_reconciliation_lines', 'finance_reconciliation_imports', 'finance_entry_settlements', 'finance_entries', 'finance_accounts', 'finance_categories'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE financial_context_id = ?")->execute([$c]);
        }
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$c]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo $failed === 0 ? "FIN-021: CONCILIAÇÃO APROVADA.\n" : "FIN-021: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
