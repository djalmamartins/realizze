<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\{Contacts, FinanceContext, OrganizationFiles, OrganizationObligations, Organizations, ServiceOrders};
use MovesCode\Model\Connection;

$pdo = Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$failed = 0;
$check = static function (bool $value, string $message) use (&$failed): void { echo ($value ? 'OK' : 'FALHOU') . " - {$message}\n"; if (!$value) $failed++; };
$newUser = static function (string $name) use ($pdo): int { $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES(?,'Teste',?,'x','active')")->execute([$name, strtolower($name) . '-' . bin2hex(random_bytes(5)) . '@example.invalid']); return (int) $pdo->lastInsertId(); };
$upload = static function (string $name, string $content): array { $path = tempnam(sys_get_temp_dir(), 'enterprise-file-'); file_put_contents($path, $content); return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'type' => 'text/plain']; };

$owner = $newUser('ArquivoDono'); $other = $newUser('ArquivoOutro');
$org = Organizations::create($owner, 'Arquivos Alfa', ''); $alien = Organizations::create($other, 'Arquivos Beta', '');
$context = FinanceContext::forOrganization($org);
$customer = (new Contacts($context))->create(['person_type' => 'company', 'legal_name' => 'Cliente Arquivo', 'is_customer' => '1', 'status' => 'active'], $owner);
$obligations = new OrganizationObligations($org); $orders = new ServiceOrders($org);
$stored = [];
try {
    $obligationData = ['title' => 'Contrato social', 'type' => 'contract', 'competence_date' => '2033-01-01', 'due_date' => '2033-01-10', 'frequency' => 'once', 'idempotency_key' => bin2hex(random_bytes(16))];
    $obligation = $obligations->create($obligationData, $owner, [$upload('comprovante.txt', 'comprovante empresarial')]);
    $obligationAttachment = $pdo->query('SELECT * FROM organization_obligation_attachments WHERE obligation_id=' . (int) $obligation['id'])->fetch(PDO::FETCH_ASSOC);
    $stored[] = ['obligations', $obligationAttachment['stored_path']];
    $check($obligations->attachment((int) $obligationAttachment['id']) !== null, 'Comprovante da obrigação é persistido em armazenamento privado');
    $check((new OrganizationObligations($alien))->attachment((int) $obligationAttachment['id']) === null, 'Outra empresa não resolve o comprovante da obrigação');
    $obligations->update((int) $obligation['id'], $obligationData, $owner, [$upload('aditivo.txt', 'aditivo contratual')]);
    $check((int) $pdo->query('SELECT COUNT(*) FROM organization_obligation_attachments WHERE obligation_id=' . (int) $obligation['id'])->fetchColumn() === 2, 'Edição acrescenta comprovante sem apagar o histórico');

    $orderData = ['customer_id' => $customer, 'description' => 'Serviço documentado', 'start_date' => '2033-01-01', 'due_date' => '2033-01-20', 'discount' => '0', 'item_type' => ['service'], 'item_description' => ['Consultoria'], 'item_quantity' => ['1'], 'item_unit_price' => ['250,00'], 'item_discount' => ['0'], 'idempotency_key' => bin2hex(random_bytes(16))];
    $order = $orders->create($orderData, $owner, [$upload('escopo.txt', 'escopo da ordem')]);
    $orderAttachment = $pdo->query('SELECT * FROM organization_service_order_attachments WHERE service_order_id=' . (int) $order['id'])->fetch(PDO::FETCH_ASSOC);
    $stored[] = ['service-orders', $orderAttachment['stored_path']];
    $check($orders->attachment((int) $orderAttachment['id']) !== null, 'Anexo da ordem é persistido em armazenamento privado');
    $check((new ServiceOrders($alien))->attachment((int) $orderAttachment['id']) === null, 'Outra empresa não resolve o anexo da ordem');
    $orders->update((int) $order['id'], $orderData, $owner, [$upload('aceite.txt', 'aceite do cliente')]);
    $check((int) $pdo->query('SELECT COUNT(*) FROM organization_service_order_attachments WHERE service_order_id=' . (int) $order['id'])->fetchColumn() === 2, 'Edição acrescenta anexo sem apagar documentos anteriores');
    $supplier = (new Contacts($context))->create(['person_type' => 'company', 'legal_name' => 'Somente fornecedor', 'is_supplier' => '1', 'status' => 'active'], $owner);
    try { $orders->create(array_replace($orderData, ['customer_id' => $supplier, 'idempotency_key' => bin2hex(random_bytes(16))]), $owner); $blocked = false; } catch (InvalidArgumentException) { $blocked = true; }
    $check($blocked, 'Ordem recusa contato que não é cliente ativo');
} finally {
    foreach (['organization_obligation_attachments' => 'obligations', 'organization_service_order_attachments' => 'service-orders'] as $table => $area) {
        $rows = $pdo->query("SELECT stored_path FROM {$table} WHERE organization_id=" . (int) $org)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $path) OrganizationFiles::remove($org, $area, (string) $path);
    }
    foreach ([$org, $alien] as $organizationId) {
        foreach (['organization_obligation_history', 'organization_obligation_attachments', 'organization_obligations', 'organization_service_order_history', 'organization_service_order_attachments'] as $table) $pdo->prepare("DELETE FROM {$table} WHERE organization_id=?")->execute([$organizationId]);
        $orderIds = $pdo->query('SELECT id FROM organization_service_orders WHERE organization_id=' . (int) $organizationId)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($orderIds as $orderId) $pdo->prepare('DELETE FROM organization_service_order_items WHERE service_order_id=?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM organization_service_orders WHERE organization_id=?')->execute([$organizationId]);
        $pdo->prepare('DELETE FROM organization_service_order_sequences WHERE organization_id=?')->execute([$organizationId]);
    }
    $pdo->prepare('DELETE FROM finance_contact_audit_logs WHERE financial_context_id=?')->execute([$context]);
    $pdo->prepare('DELETE FROM finance_contacts WHERE financial_context_id=?')->execute([$context]);
    foreach ([$org, $alien] as $organizationId) { $pdo->prepare('DELETE FROM organization_members WHERE organization_id=?')->execute([$organizationId]); $pdo->prepare('DELETE FROM financial_contexts WHERE organization_id=?')->execute([$organizationId]); $pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$organizationId]); }
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$owner, $other]);
}
echo $failed === 0 ? "ENT-008: ANEXOS EMPRESARIAIS APROVADOS.\n" : "ENT-008: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
