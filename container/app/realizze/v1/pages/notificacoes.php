<?php
/** Caixa de notificações do usuário na área atual, mais as gerais. Cada item leva à tela correspondente. */
use Moves\Services\Csrf;
use Moves\Services\Notifications;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rows = [];
$scope = null;
$failed = false;
try {
    $scope = Notifications::scope();
    $rows = $scope === null ? [] : Notifications::refresh($scope, 200);
} catch (Throwable $exception) {
    error_log('Realizze notifications: ' . $exception->getMessage());
    $failed = true;
}
$filter = isset(Notifications::FILTERS[$_GET['filtro'] ?? '']) ? $_GET['filtro'] : 'all';
$unread = Notifications::unreadCount($rows);
$countEvent = static fn (string $event): int => count(array_filter($rows, static fn (array $r): bool => $r['event_type'] === $event));
$list = array_filter($rows, static fn (array $r): bool => match ($filter) {
    'all' => true,
    'unread' => $r['read_at'] === null,
    default => $r['category'] === $filter,
});
$path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
$token = Csrf::token();
?>
<section class="card finance-workspace" aria-labelledby="alerts-title">
  <div class="finance-toolbar">
    <div><h2 id="alerts-title">Notificações</h2><p class="muted">Vencimentos, comunicados e respostas do suporte. Clique para ir à tela correspondente.</p></div>
    <?php if ($unread > 0 && Notifications::supported()): ?>
    <div class="finance-toolbar-actions"><form method="post" action="/minha-conta/notificacoes/lidas"><input type="hidden" name="csrf_token" value="<?= $e($token) ?>"><button type="submit" class="note-read-all">Marcar todas como lidas</button></form></div>
    <?php endif; ?>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo">
    <div class="finance-goal-stat"><span>Não lidas</span><strong><?= $unread ?></strong><small>Notificações</small></div>
    <div class="finance-goal-stat"><span>Em atraso</span><strong class="<?= $countEvent('finance.overdue') > 0 ? 'red' : '' ?>"><?= $countEvent('finance.overdue') ?></strong><small>Lançamentos</small></div>
    <div class="finance-goal-stat"><span>Vencem hoje</span><strong><?= $countEvent('finance.due_today') ?></strong><small>Lançamentos</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-status" aria-label="Filtro">
      <a href="<?= $e($path) ?>"<?= $filter === 'all' ? ' aria-current="true"' : '' ?>>Todas</a>
      <?php foreach (Notifications::FILTERS as $key => $label): ?><a href="<?= $e($path . '?filtro=' . $key) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a><?php endforeach; ?>
    </nav>
  </div>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as notificações</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($list === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Nada por aqui</h3><p>Nenhuma notificação neste filtro.</p></div>
  <?php else: ?>
  <ul class="note-list">
    <?php foreach ($list as $note): $noteCompact = false; ?><li><?php require dirname(__DIR__) . '/components/notification-item.php'; ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (Notifications::supported() && $scope !== null): $prefs = Notifications::preferences($scope['user_id']); ?>
  <details class="note-prefs" id="preferencias"<?= isset($_GET['preferencias']) ? ' open' : '' ?>>
    <summary>Preferências de avisos automáticos</summary>
    <form method="post" action="/minha-conta/notificacoes/preferencias">
      <input type="hidden" name="csrf_token" value="<?= $e($token) ?>">
      <label>Avisar sobre vencimentos
        <select name="due_days"><?php foreach (Notifications::DUE_DAYS as $days => $label): ?><option value="<?= $days ?>"<?= $prefs['due_days'] === $days ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label class="note-check"><input type="checkbox" name="overdue_enabled"<?= $prefs['overdue_enabled'] ? ' checked' : '' ?>> Contas e recebimentos em atraso</label>
      <label class="note-check"><input type="checkbox" name="budget_enabled"<?= $prefs['budget_enabled'] ? ' checked' : '' ?>> Orçamento ultrapassado</label>
      <label class="note-check"><input type="checkbox" name="goal_enabled"<?= $prefs['goal_enabled'] ? ' checked' : '' ?>> Metas perto do prazo</label>
      <p class="muted">Vale para todas as suas áreas. Comunicados e respostas do suporte continuam chegando.</p>
      <button type="submit" class="note-read-all">Salvar preferências</button>
    </form>
  </details>
  <?php endif; ?>
  <?php if (!Notifications::supported()): ?><p class="profile-pending team-pending">Marcar como lida fica disponível após a atualização do banco de dados.</p><?php endif; ?>
</section>
