<?php
declare(strict_types=1);

/** Centros de custo: cadastro, nome repetido, inativação e resultado do mês por centro. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CostCenters;
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
$refused = static function (callable $call): bool {
    try { $call(); return false; } catch (InvalidArgumentException) { return true; }
};
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Centro', 'Teste', ?, 'x', 'active')")
    ->execute(['centro-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Centro Alfa', ''), Organizations::create($userId, 'Centro Beta', '')];
try {
    $ctx = FinanceContext::forOrganization($orgs[0]);
    $finance = new Finance($ctx);
    $centers = new CostCenters($ctx);
    $account = $finance->createAccount(['name' => 'Banco', 'type' => 'checking']);
    $store = $centers->save(['name' => 'Loja', 'code' => 'L1'], $userId);
    $web = $centers->save(['name' => 'Site'], $userId);
    $check($refused(static fn () => $centers->save(['name' => 'Loja'], $userId)), 'Nome repetido é recusado com mensagem');
    $check($refused(static fn () => $centers->save(['name' => 'Outro', 'code' => 'L1'], $userId)), 'Código repetido é recusado');
    $check($centers->save(['id' => $store, 'name' => 'Loja', 'code' => 'L1'], $userId) === $store, 'Salvar sem mudança não dá erro');
    $check($refused(static fn () => (new CostCenters(FinanceContext::forOrganization($orgs[1])))->save(['id' => $store, 'name' => 'X'], $userId)), 'Outra empresa não edita o centro');

    $day = '2026-03-10';
    $finance->createEntry('expense', ['description' => 'Aluguel', 'amount' => '1.000,00', 'due_date' => $day, 'account_id' => $account, 'cost_center_id' => $store, 'paid' => '1'], $userId);
    $finance->createEntry('expense', ['description' => 'Energia', 'amount' => '200,00', 'due_date' => $day, 'account_id' => $account, 'cost_center_id' => $store], $userId);
    $finance->createEntry('expense', ['description' => 'Sem centro', 'amount' => '50,00', 'due_date' => $day, 'account_id' => $account], $userId);
    $receivables = new Receivables($ctx);
    $check($refused(static fn () => $receivables->create(['description' => 'Venda', 'amount' => '800,00', 'due_date' => $day, 'competence' => '2026-03', 'account_id' => $account, 'payment_method' => 'pix', 'cost_center_id' => '999999', 'idempotency_key' => bin2hex(random_bytes(32))], $userId)), 'Recebimento com centro de outra empresa é recusado');
    $sale = (int) $receivables->create(['description' => 'Venda', 'amount' => '800,00', 'due_date' => $day, 'competence' => '2026-03', 'account_id' => $account, 'payment_method' => 'pix', 'cost_center_id' => (string) $web, 'idempotency_key' => bin2hex(random_bytes(32))], $userId)['id'];
    $receivables->update($sale, ['description' => 'Venda', 'amount' => '800,00', 'due_date' => $day, 'competence' => '2026-03', 'account_id' => $account, 'payment_method' => 'pix'], $userId);
    $check((int) $pdo->query("SELECT cost_center_id FROM finance_entries WHERE id = {$sale}")->fetchColumn() === $web, 'Recebimento guarda o centro, e editar sem o campo mantém');
    $receivables->settle($sale, ['amount' => '300,00', 'received_at' => $day, 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => bin2hex(random_bytes(32))], $userId);
    $rows = array_column($centers->totals('2026-03-01', '2026-03-31'), null, 'name');
    $check($rows['Loja']['expense_done'] === 100000 && $rows['Loja']['expense_open'] === 20000, 'Loja separa despesa paga e prevista');
    $check($rows['Site']['income_done'] === 30000 && $rows['Site']['income_open'] === 50000, 'Baixa parcial conta no realizado do centro');
    $check($rows['Sem centro de custo']['expense_open'] === 5000, 'Despesa sem centro aparece na linha própria');
    $check(array_sum(array_column($rows, 'expense_done')) + array_sum(array_column($rows, 'expense_open')) === $finance->summary('expense', '2026-03-01', '2026-03-31')['paid'] + $finance->summary('expense', '2026-03-01', '2026-03-31')['expected'], 'Soma dos centros bate com o total de despesas do mês');
    $check(array_sum(array_column($centers->totals('2026-04-01', '2026-04-30'), 'expense_done')) === 0, 'Outro mês começa zerado');

    $centers->save(['id' => $store, 'name' => 'Loja', 'code' => 'L1', 'status' => 'inactive'], $userId);
    $check(!in_array($store, array_map('intval', array_column($finance->costCenters(), 'id')), true), 'Centro inativo some dos formulários');
    $check(array_column($centers->totals('2026-03-01', '2026-03-31'), null, 'name')['Loja']['expense_done'] === 100000, 'Centro inativo mantém o histórico');
    $check($refused(static fn () => $finance->createEntry('expense', ['description' => 'X', 'amount' => '1,00', 'due_date' => $day, 'account_id' => $account, 'cost_center_id' => $store], $userId)), 'Não lança em centro inativo');
} finally {
    foreach ($orgs as $org) {
        $c = FinanceContext::forOrganization($org);
        foreach (['finance_entry_settlements', 'finance_entries', 'finance_cost_centers', 'finance_accounts', 'finance_categories'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE financial_context_id = ?")->execute([$c]);
        }
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$c]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo $failed === 0 ? "FIN-022: CENTROS DE CUSTO APROVADOS.\n" : "FIN-022: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
