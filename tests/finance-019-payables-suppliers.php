<?php
declare(strict_types=1);

/** Despesa com fornecedor e centro de custo: criar, editar, filtrar e isolar por empresa. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Contacts;
use Moves\Services\CostCenters;
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
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Pagar', 'Teste', ?, 'x', 'active')")
    ->execute(['pagar-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Pagar Alfa', ''), Organizations::create($userId, 'Pagar Beta', '')];
try {
    $ctx = FinanceContext::forOrganization($orgs[0]);
    $finance = new Finance($ctx);
    $contacts = new Contacts($ctx);
    $account = $finance->createAccount(['name' => 'Banco', 'type' => 'checking']);
    $supplier = $contacts->create(['person_type' => 'company', 'legal_name' => 'Tecidos SA', 'is_supplier' => '1'], $userId);
    $customer = $contacts->create(['person_type' => 'company', 'legal_name' => 'Cliente SA', 'is_customer' => '1'], $userId);
    $otherSupplier = (new Contacts(FinanceContext::forOrganization($orgs[1])))->create(['person_type' => 'company', 'legal_name' => 'Fora SA', 'is_supplier' => '1'], $userId);
    $center = (new CostCenters($ctx))->save(['name' => 'Loja'], $userId);
    $check(array_column($contacts->selectableSuppliers(), 'id') === [$supplier], 'Lista de fornecedores tem só fornecedores ativos da empresa');

    $base = ['description' => 'Tecido', 'amount' => '300,00', 'due_date' => date('Y-m-d'), 'account_id' => $account];
    $check($refused(static fn () => $finance->createEntry('expense', $base + ['supplier_id' => $customer], $userId)), 'Cliente que não é fornecedor é recusado');
    $check($refused(static fn () => $finance->createEntry('expense', $base + ['supplier_id' => $otherSupplier], $userId)), 'Fornecedor de outra empresa é recusado');
    $entry = $finance->createEntry('expense', $base + ['supplier_id' => $supplier, 'cost_center_id' => $center], $userId);
    $row = $finance->entries('expense', date('Y-m-01'), date('Y-m-t'))[0];
    $check((int) $row['contact_id'] === $supplier && $row['contact_name'] === 'Tecidos SA' && $row['cost_center'] === 'Loja', 'Despesa guarda fornecedor e centro de custo');
    $other = $finance->createEntry('expense', ['description' => 'Energia'] + $base, $userId);
    $check(count($finance->entries('expense', date('Y-m-01'), date('Y-m-t'), 'all', ['contact_id' => $supplier])) === 1, 'Filtro por fornecedor');
    $check(count($finance->entries('expense', date('Y-m-01'), date('Y-m-t'), 'all', ['cost_center_id' => $center])) === 1, 'Filtro por centro de custo');

    $finance->updateExpense($entry, ['description' => 'Tecido azul', 'amount' => '320,00', 'due_date' => date('Y-m-d'), 'account_id' => $account], $userId);
    $row = $finance->entryEditData([$entry])[$entry];
    $check($row['contact_id'] === $supplier && $row['cost_center_id'] === $center && $row['description'] === 'Tecido azul', 'Editar sem os campos na tela mantém fornecedor e centro');
    $finance->updateExpense($entry, ['description' => 'Tecido azul', 'amount' => '320,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'supplier_id' => '', 'cost_center_id' => ''], $userId);
    $row = $finance->entryEditData([$entry])[$entry];
    $check($row['contact_id'] === 0 && $row['cost_center_id'] === 0, 'Editar pode tirar fornecedor e centro');
    $finance->updateExpense($entry, ['description' => 'Tecido azul', 'amount' => '320,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'supplier_id' => (string) $supplier], $userId);
    $check($finance->entryEditData([$entry])[$entry]['contact_id'] === $supplier, 'Editar pode vincular o fornecedor depois');
    $check((int) (new CostCenters($ctx))->all(true)[0]['expense_cents'] === 0, 'Centro sem despesa vinculada fica zerado');
    $finance->createEntry('income', ['description' => 'Venda', 'amount' => '50,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'supplier_id' => $supplier], $userId);
    $check(count($finance->entries('income', date('Y-m-01'), date('Y-m-t'), 'all', ['contact_id' => $supplier])) === 0, 'Receita não recebe fornecedor');
    $finance->delete($other, $userId);
} finally {
    foreach ($orgs as $org) {
        $c = FinanceContext::forOrganization($org);
        foreach (['finance_entry_settlements', 'finance_entries', 'finance_cost_centers', 'finance_contacts', 'finance_accounts', 'finance_categories'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE financial_context_id = ?")->execute([$c]);
        }
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$c]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo $failed === 0 ? "FIN-019: PAGAR COM FORNECEDOR APROVADO.\n" : "FIN-019: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
