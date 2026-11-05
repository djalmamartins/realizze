<?php
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';

use MovesCode\Model\Connection;

$ok = 0; $fail = 0;
$check = static function (bool $condition, string $message) use (&$ok, &$fail): void { echo ($condition ? 'OK' : 'FALHOU') . " - {$message}\n"; $condition ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$token = bin2hex(random_bytes(4));
$obligationTitle = "Obrigação HTTP {$token}";
$orderDescription = "Ordem HTTP {$token}";
$pdo = Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');

$owner = new H();
$check($owner->login('pj-dono@example.invalid', 'PjMissao#2026'), 'Dono autentica no servidor isolado');
try {
    $obligations = $owner->req('GET', "/e/{$slug}/obrigacoes");
    $check($obligations['code'] === 200 && str_contains($obligations['body'], 'Calendário de obrigações'), 'Calendário abre no contexto PJ');
    $response = $owner->req('POST', '/minha-conta/obrigacoes/salvar', [
        'csrf_token' => $owner->csrf($obligations['body']), 'idempotency_key' => bin2hex(random_bytes(16)),
        'title' => $obligationTitle, 'type' => 'contract', 'frequency' => 'once',
        'competence_date' => '2034-03-01', 'due_date' => '2034-03-15', 'amount' => '120,00',
    ]);
    $obligations = $owner->req('GET', "/e/{$slug}/obrigacoes?inicio=2034-03-01&fim=2034-03-31");
    $check($response['code'] === 303 && str_contains($obligations['body'], $obligationTitle), 'Cadastro da obrigação funciona pela interface');
    preg_match('/id="obligation-edit-(\d+)"/', $obligations['body'], $obligationMatch);
    $obligationId = (int) ($obligationMatch[1] ?? 0);
    $response = $owner->req('POST', '/minha-conta/obrigacoes/atualizar', [
        'csrf_token' => $owner->csrf($obligations['body']), 'id' => $obligationId,
        'title' => $obligationTitle . ' editada', 'type' => 'contract', 'frequency' => 'once',
        'competence_date' => '2034-03-01', 'due_date' => '2034-03-16', 'amount' => '130,00',
    ]);
    $obligations = $owner->req('GET', "/e/{$slug}/obrigacoes?inicio=2034-03-01&fim=2034-03-31");
    $check($obligationId > 0 && $response['code'] === 303 && str_contains($obligations['body'], $obligationTitle . ' editada') && str_contains($obligations['body'], 'Editada'), 'Edição e histórico da obrigação aparecem na tela');

    $orders = $owner->req('GET', "/e/{$slug}/ordens-de-servico");
    preg_match('/name="customer_id"[^>]*>.*?<option value="(\d+)"/s', $orders['body'], $customerMatch);
    $customerId = (int) ($customerMatch[1] ?? 0);
    $check($orders['code'] === 200 && str_contains($orders['body'], 'Ordens de serviço') && $customerId > 0, 'Tela de OS abre e oferece cliente real da empresa');
    $response = $owner->req('POST', '/minha-conta/ordens-de-servico/salvar', [
        'csrf_token' => $owner->csrf($orders['body']), 'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id' => $customerId, 'description' => $orderDescription, 'start_date' => '2034-03-01', 'due_date' => '2034-03-20',
        'item_type' => ['service', 'item'], 'item_description' => ['Consultoria HTTP', 'Material HTTP'],
        'item_quantity' => ['2', '1'], 'item_unit_price' => ['100,00', '50,00'], 'item_discount' => ['0', '10,00'], 'discount' => '20,00',
    ]);
    $orders = $owner->req('GET', "/e/{$slug}/ordens-de-servico");
    $check($response['code'] === 303 && str_contains($orders['body'], $orderDescription) && str_contains($orders['body'], 'R$ 220,00'), 'OS com múltiplos itens e descontos fecha pela interface');
    preg_match('/id="service-order-edit-(\d+)"/', $orders['body'], $orderMatch);
    $orderId = (int) ($orderMatch[1] ?? 0);
    $response = $owner->req('POST', '/minha-conta/ordens-de-servico/atualizar', [
        'csrf_token' => $owner->csrf($orders['body']), 'id' => $orderId, 'customer_id' => $customerId,
        'description' => $orderDescription . ' editada', 'start_date' => '2034-03-01', 'due_date' => '2034-03-21',
        'item_type' => ['service'], 'item_description' => ['Consultoria revisada'], 'item_quantity' => ['1'], 'item_unit_price' => ['300,00'], 'item_discount' => ['0'], 'discount' => '0',
    ]);
    $orders = $owner->req('GET', "/e/{$slug}/ordens-de-servico");
    $check($orderId > 0 && $response['code'] === 303 && str_contains($orders['body'], $orderDescription . ' editada') && str_contains($orders['body'], 'R$ 300,00') && str_contains($orders['body'], 'Editada'), 'Edição, recálculo e histórico da OS aparecem na tela');

    $viewer = new H();
    $check($viewer->login('pj-leitor@example.invalid', 'PjMissao#2026'), 'Visualizador autentica no servidor isolado');
    $viewerPage = $viewer->req('GET', "/e/{$slug}/obrigacoes?inicio=2034-03-01&fim=2034-03-31");
    $check($viewerPage['code'] === 200 && !str_contains($viewerPage['body'], '+ Nova obrigação') && !str_contains($viewerPage['body'], '>Editar<'), 'Visualizador consulta obrigações sem ações de escrita');
    $forbidden = $viewer->req('POST', '/minha-conta/obrigacoes/concluir', ['csrf_token' => $viewer->csrf($viewerPage['body']), 'id' => $obligationId]);
    $check($forbidden['code'] === 403, 'Backend recusa alteração de obrigação pelo Visualizador');
    $viewerOrders = $viewer->req('GET', "/e/{$slug}/ordens-de-servico");
    $check($viewerOrders['code'] === 200 && !str_contains($viewerOrders['body'], '+ Nova ordem') && !str_contains($viewerOrders['body'], 'Gerar cobrança'), 'Visualizador consulta OS sem ações financeiras');
} finally {
    $organizationId = (int) $pdo->query("SELECT organization_id FROM organization_obligations WHERE title LIKE " . $pdo->quote($obligationTitle . '%') . ' LIMIT 1')->fetchColumn();
    if ($organizationId > 0) {
        $obligationIds = $pdo->query("SELECT id FROM organization_obligations WHERE organization_id={$organizationId} AND title LIKE " . $pdo->quote($obligationTitle . '%'))->fetchAll(PDO::FETCH_COLUMN);
        foreach ($obligationIds as $id) { $pdo->prepare('DELETE FROM organization_obligation_history WHERE obligation_id=?')->execute([$id]); $pdo->prepare('DELETE FROM organization_obligation_attachments WHERE obligation_id=?')->execute([$id]); $pdo->prepare('DELETE FROM organization_obligations WHERE id=?')->execute([$id]); }
        $orderIds = $pdo->query("SELECT id FROM organization_service_orders WHERE organization_id={$organizationId} AND description LIKE " . $pdo->quote($orderDescription . '%'))->fetchAll(PDO::FETCH_COLUMN);
        foreach ($orderIds as $id) { $pdo->prepare('DELETE FROM organization_service_order_history WHERE service_order_id=?')->execute([$id]); $pdo->prepare('DELETE FROM organization_service_order_attachments WHERE service_order_id=?')->execute([$id]); $pdo->prepare('DELETE FROM organization_service_order_items WHERE service_order_id=?')->execute([$id]); $pdo->prepare('DELETE FROM organization_service_orders WHERE id=?')->execute([$id]); }
    }
}
echo "{$ok} OK, {$fail} falha(s)\n";
exit($fail === 0 ? 0 : 1);
