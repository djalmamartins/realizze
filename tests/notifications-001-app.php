<?php
declare(strict_types=1);

/** Notificações do App: estágios, isolamento por área, CMS, abertura e contrato para push. Exige a migration 109; limpa o que cria. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CmsBroadcasts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\HelpdeskNotifier;
use Moves\Services\Notifications;
use Moves\Services\Organizations;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$ids = $companies = $broadcasts = [];
$byEvent = static fn (array $feed, string $event): array => array_values(array_filter($feed, static fn (array $r): bool => $r['event_type'] === $event));
$finance = static fn (array $feed): array => array_values(array_filter($feed, static fn (array $r): bool => $r['category'] === 'financeiro'));
try {
    $check(Notifications::supported(), 'Migration 109 aplicada');
    foreach (['owner', 'other'] as $name) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'N', ?, 'x', 'active')")
            ->execute(['notif-' . bin2hex(random_bytes(6)) . '@example.invalid']);
        $ids[$name] = (int) $pdo->lastInsertId();
    }
    $personal = FinanceContext::forUser($ids['owner']);
    $otherContext = FinanceContext::forUser($ids['other']);
    $scope = ['user_id' => $ids['owner'], 'context_id' => $personal];
    $otherScope = ['user_id' => $ids['other'], 'context_id' => $otherContext];
    $pdo->prepare("INSERT INTO finance_accounts (financial_context_id, name) VALUES (?, 'Conta')")->execute([$personal]);
    $account = (int) $pdo->lastInsertId();
    $service = new Finance($personal);
    $entry = (int) $service->createEntry('expense', ['description' => 'Aluguel teste', 'amount' => '100,00', 'due_date' => date('Y-m-d', strtotime('+3 days')), 'account_id' => $account], $ids['owner']);

    Notifications::sync($ids['owner'], $personal);
    $rows = $finance(Notifications::feed($scope));
    $check(count($rows) === 1 && $rows[0]['event_type'] === 'finance.due_soon' && $rows[0]['read_at'] === null, 'Vencimento próximo gera aviso não lido');
    $check(str_contains($rows[0]['body'], 'Aluguel teste') && str_contains($rows[0]['body'], '100,00'), 'Aviso traz descrição e valor reais');
    $check(Notifications::sync($ids['owner'], $personal) === 0, 'Sincronizar de novo não muda nem duplica');

    $url = Notifications::open($ids['owner'], (int) $rows[0]['id']);
    $check($url === '/p/pagar/' . date('Y/m', strtotime('+3 days')) . '#lancamento-' . $entry, 'Abrir leva ao lançamento em Pagar: ' . $url);
    $check(Notifications::unreadCount($finance(Notifications::feed($scope))) === 0, 'Abrir marca como lida');
    $check(Notifications::open($ids['other'], (int) $rows[0]['id']) === null, 'Outro usuário não abre');

    $pdo->prepare('UPDATE finance_entries SET due_date = CURDATE() - INTERVAL 2 DAY WHERE id = ?')->execute([$entry]);
    Notifications::sync($ids['owner'], $personal);
    $rows = $finance(Notifications::feed($scope));
    $check(count($rows) === 1 && $rows[0]['event_type'] === 'finance.overdue' && $rows[0]['priority'] === 'critical', 'Data alterada troca o aviso, sem repetir');

    $key = (int) $rows[0]['id'];
    Notifications::open($ids['owner'], $key);
    $pdo->prepare('UPDATE finance_entries SET due_date = CURDATE() WHERE id = ?')->execute([$entry]);
    Notifications::sync($ids['owner'], $personal);
    $pdo->prepare('UPDATE finance_entries SET due_date = CURDATE() - INTERVAL 2 DAY WHERE id = ?')->execute([$entry]);
    Notifications::sync($ids['owner'], $personal);
    $check(count($byEvent(Notifications::feed($scope), 'finance.overdue')) === 1, 'Atraso reaparece uma vez só');

    $pdo->prepare('UPDATE finance_entries SET paid_at = CURDATE() WHERE id = ?')->execute([$entry]);
    Notifications::sync($ids['owner'], $personal);
    $check($finance(Notifications::feed($scope)) === [], 'Conta paga sai da lista');
    $pdo->prepare('UPDATE finance_entries SET paid_at = NULL, due_date = CURDATE() WHERE id = ?')->execute([$entry]);
    Notifications::sync($ids['owner'], $personal);
    $today = $byEvent(Notifications::feed($scope), 'finance.due_today');
    $check(count($today) === 1 && $today[0]['read_at'] === null, 'Vencer hoje avisa de novo');

    $pdo->prepare("INSERT INTO finance_goals (financial_context_id, name, target_cents, saved_cents, deadline, status) VALUES (?, 'Meta teste', 1000, 10, CURDATE() + INTERVAL 2 DAY, 'active')")->execute([$personal]);
    Notifications::sync($ids['owner'], $personal);
    $goal = $byEvent(Notifications::feed($scope), 'finance.goal_deadline');
    $check(count($goal) === 1 && Notifications::destination($goal[0]) === '/p/metas', 'Meta perto do prazo avisa e leva a Metas');

    Notifications::savePreferences($ids['owner'], ['due_days' => '0', 'budget_enabled' => '1']);
    Notifications::sync($ids['owner'], $personal);
    $check($byEvent(Notifications::feed($scope), 'finance.goal_deadline') === [] && count($byEvent(Notifications::feed($scope), 'finance.due_today')) === 1, 'Preferência retira a meta e mantém o vencimento do dia');
    $check(Notifications::preferences($ids['owner'])['overdue_enabled'] === false, 'Caixa desmarcada fica salva');
    Notifications::savePreferences($ids['owner'], ['due_days' => '99', 'overdue_enabled' => '1', 'budget_enabled' => '1', 'goal_enabled' => '1']);
    $check(Notifications::preferences($ids['owner'])['due_days'] === 7, 'Antecedência inválida volta ao padrão');
    Notifications::sync($ids['owner'], $personal);
    $check(count($byEvent(Notifications::feed($scope), 'finance.goal_deadline')) === 1, 'Religar a preferência traz o aviso de volta');
    $check(Notifications::ago(date('Y-m-d H:i:s')) === 'agora' && Notifications::ago(date('Y-m-d H:i:s', strtotime('-2 days'))) === 'há 2 dias', 'Tempo relativo');

    $companies[] = $company = Organizations::create($ids['owner'], 'Empresa Notif ' . bin2hex(random_bytes(3)), '');
    $companyContext = FinanceContext::forOrganization($company);
    $pdo->prepare("INSERT INTO finance_accounts (financial_context_id, name) VALUES (?, 'Conta PJ')")->execute([$companyContext]);
    $companyAccount = (int) $pdo->lastInsertId();
    (new Finance($companyContext))->createEntry('income', ['description' => 'Fatura empresa', 'amount' => '50,00', 'due_date' => date('Y-m-d'), 'account_id' => $companyAccount], $ids['owner']);
    Notifications::syncAll();
    $companyScope = ['user_id' => $ids['owner'], 'context_id' => $companyContext];
    $companyRows = $finance(Notifications::feed($companyScope));
    $check(count($companyRows) === 1 && str_contains($companyRows[0]['body'], 'Fatura empresa'), 'Processador gera o aviso da empresa');
    $check(!str_contains(json_encode(Notifications::feed($scope), JSON_UNESCAPED_UNICODE), 'Fatura empresa'), 'Aviso da empresa não aparece na área pessoal');
    $check(!str_contains(json_encode(Notifications::feed(['user_id' => $ids['owner'], 'context_id' => 0]), JSON_UNESCAPED_UNICODE), 'Fatura'), 'Sem permissão financeira, a empresa não aparece');
    $companyUrl = Notifications::destination($companyRows[0]);
    $check(str_contains($companyUrl, '/receber/') && str_starts_with($companyUrl, '/e/'), 'Aviso da empresa leva ao endereço da empresa: ' . $companyUrl);
    $check($finance(Notifications::feed($otherScope)) === [], 'Outro usuário não recebe nada');
    $check(!array_filter(HelpdeskNotifier::inbox($ids['owner'], 'customer', 100), static fn (array $r): bool => str_starts_with($r['event_type'], 'finance.')), 'Caixa do suporte não mistura avisos financeiros');

    Notifications::markAllRead($scope);
    $check(Notifications::unreadCount(Notifications::feed($scope)) === 0 && Notifications::unreadCount($finance(Notifications::feed($companyScope))) === 1, 'Marcar todas vale só para a área atual');

    $actor = $ids['owner'];
    $broadcasts[] = $broadcast = CmsBroadcasts::create(['title' => 'Comunicado teste', 'body' => 'Mensagem do CMS', 'audience' => 'customers', 'link' => '/metas'], $actor);
    $rows = array_values(array_filter(Notifications::refresh($scope), static fn (array $r): bool => (int) $r['broadcast_id'] === $broadcast));
    $check(count($rows) === 1 && $rows[0]['category'] === 'comunicado' && $rows[0]['read_at'] === null, 'Comunicado do CMS chega não lido ao usuário');
    $check(array_filter(Notifications::feed($companyScope), static fn (array $r): bool => (int) $r['broadcast_id'] === $broadcast) !== [], 'Comunicado aparece em qualquer área');
    $check(Notifications::open($ids['owner'], (int) $rows[0]['id']) === '/p/metas', 'Link curto do CMS vira a tela da área atual');
    $check(Notifications::destination(['link' => '//malicioso.example']) === '/p/notificacoes' && Notifications::destination(['link' => 'javascript:alert(1)']) === '/p/notificacoes', 'Destino inseguro é recusado');
    $check(Notifications::destination(['link' => '/suporte/ABC-1']) === '/suporte/ABC-1', 'Chamado continua indo ao suporte');

    $payload = Notifications::payload($companyRows[0]);
    $check($payload['target']['screen'] === 'receber' && $payload['target']['entity'] === 'finance_entry' && $payload['category'] === 'financeiro' && isset($payload['url'], $payload['title'], $payload['context_id']), 'Contrato para os apps traz tela nativa e URL');
    $pdo->prepare("INSERT INTO notification_devices (user_id, platform, provider, token, token_hash) VALUES (?, 'ios', 'apns', 'tok', ?)")->execute([$ids['owner'], hash('sha256', 'tok' . $ids['owner'])]);
    $device = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO notification_deliveries (channel, dedupe_key, notification_id, device_id, payload_json, next_attempt_at, created_at) VALUES ('push', ?, ?, ?, ?, NOW(), NOW())")
        ->execute(['test-push:' . $device, $payload['id'], $device, json_encode($payload)]);
    $check($pdo->lastInsertId() > 0, 'Fila aceita push por aparelho');
    $pdo->prepare("DELETE FROM notification_deliveries WHERE device_id = ?")->execute([$device]);
} finally {
    foreach ($broadcasts as $id) {
        $pdo->prepare('DELETE FROM cms_broadcasts WHERE id = ?')->execute([$id]);
    }
    foreach ($companies as $id) {
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$id]);
    }
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM financial_contexts WHERE personal_user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
echo "notifications-001-app: tudo certo\n";
