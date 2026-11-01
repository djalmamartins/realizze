<?php
/** Avisos reais de vencimento do contexto atual. Preferências e envios aguardam o backend. */
use Moves\Services\Finance;
use Moves\Services\FinanceAlerts;
use Moves\Services\FinanceContext;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rows = [];
$failed = false;
try {
    $rows = (new FinanceAlerts((int) FinanceContext::currentId()))->upcoming();
} catch (Throwable $exception) {
    error_log('Realizze alerts: ' . $exception->getMessage());
    $failed = true;
}
$groups = ['overdue' => 'Em atraso', 'today' => 'Vencem hoje', 'next' => 'Próximos 7 dias'];
$filter = isset($groups[$_GET['filtro'] ?? '']) ? $_GET['filtro'] : 'all';
$count = static fn (string $g): int => count(array_filter($rows, static fn (array $r): bool => $r['group'] === $g));
$path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
?>
<section class="card finance-workspace" aria-labelledby="alerts-title">
  <div class="finance-toolbar"><div><h2 id="alerts-title">Notificações</h2><p class="muted">Vencimentos em aberto de recebimentos e despesas.</p></div></div>
  <div class="finance-goal-summary" aria-label="Resumo">
    <?php foreach ($groups as $key => $label): ?>
    <div class="finance-goal-stat"><span><?= $e($label) ?></span><strong class="<?= $key === 'overdue' && $count($key) > 0 ? 'red' : '' ?>"><?= $count($key) ?></strong><small>Lançamentos</small></div>
    <?php endforeach; ?>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-status" aria-label="Filtro">
      <a href="<?= $e($path) ?>"<?= $filter === 'all' ? ' aria-current="true"' : '' ?>>Todas</a>
      <?php foreach ($groups as $key => $label): ?><a href="<?= $e($path . '?filtro=' . $key) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a><?php endforeach; ?>
    </nav>
  </div>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as notificações</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php else: $shown = 0; foreach ($groups as $key => $label): if ($filter !== 'all' && $filter !== $key) { continue; } $list = array_filter($rows, static fn (array $r): bool => $r['group'] === $key); if ($list === []) { continue; } $shown++; ?>
  <section class="alert-group"><h3><?= $e($label) ?></h3>
    <ul class="overview-list">
      <?php foreach ($list as $row): $in = $row['kind'] === 'income'; ?>
      <li><span><strong><?= $e($row['description']) ?></strong><small><?= $in ? 'A receber' : 'A pagar' ?> · <?= $e((new DateTimeImmutable($row['due_date']))->format('d/m/Y')) ?></small></span><strong class="<?= $in ? 'green' : 'red' ?>"><?= $e(Finance::money($row['amount_cents'])) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endforeach; if ($shown === 0): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Nada por aqui</h3><p>Nenhum vencimento em atraso ou nos próximos 7 dias.</p></div>
  <?php endif; endif; ?>
  <p class="profile-pending team-pending">Preferências e envio por e-mail ainda não estão disponíveis.</p>
</section>
