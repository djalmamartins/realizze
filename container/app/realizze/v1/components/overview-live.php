<?php
/** Visão geral com dados reais do contexto atual. Transferências não contam como receita nem despesa. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn (int $cents): string => '<span data-count="' . $cents . '">' . htmlspecialchars(Finance::money($cents), ENT_QUOTES, 'UTF-8') . '</span>';
$period = Finance::period($_GET);
$today = new DateTimeImmutable('today');
$data = null;
$manual = [];
$categoriesIncome = $categoriesExpense = $series = [];
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $data = $finance->overview($period['start'], $period['end'], $period['compareStart'], $period['compareEnd']);
    $manual = $finance->accounts();
    $categoriesIncome = $finance->categories('income');
    $categoriesExpense = $finance->categories('expense');
    $series = $finance->monthlySeries($period['end'], $period['view'] === 'anual' ? 12 : 6);
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
}
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
if ($data === null): ?>
<section class="card"><div class="finance-empty"><h3>Não foi possível carregar seus dados</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div></section>
<?php return; endif;
if ($manual === []) {
    echo $appView->render('components::getting-started', ['icon' => $icon]);
}
$total = (int) $period['start']->diff($period['end'])->days + 1;
$elapsed = $today < $period['start'] ? 0 : ($today > $period['end'] ? $total : (int) $period['start']->diff($today)->days + 1);
$percent = (int) round($elapsed / $total * 100);
$categoryTotal = array_sum(array_column($data['categories'], 'total'));
$insight = 'Sem despesas suficientes para comparar com o período anterior.';
$previous = array_column($data['previous_categories'], 'total', 'name');
$biggest = null;
foreach ($data['categories'] as $category) {
    $difference = $category['total'] - ($previous[$category['name']] ?? 0);
    if ($previous !== [] && ($biggest === null || $difference > $biggest[1])) {
        $biggest = [$category['name'], $difference];
    }
}
if ($biggest !== null) {
    $insight = $biggest[1] > 0
        ? 'Você gastou ' . Finance::money($biggest[1]) . ' a mais com ' . $biggest[0] . ' em comparação ao período anterior.'
        : 'Seus gastos por categoria não aumentaram em comparação ao período anterior.';
}
$month = $period['view'] === 'mensal' ? $period['start']->format('Y-m') : $today->format('Y-m');
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<div class="overview-actions">
  <button type="button" class="primary" data-finance-open="quick-income"<?= $manual === [] ? ' disabled' : '' ?>>+ Recebimento</button>
  <button type="button" class="primary is-outline" data-finance-open="quick-expense"<?= $manual === [] ? ' disabled' : '' ?>>+ Despesa</button>
</div>
<section aria-label="Indicadores financeiros" class="cards">
  <article class="card balance"><span class="label">Saldo disponível</span><div class="value"><?= $money($data['balance']) ?></div><span class="muted">Em contas e carteiras</span></article>
  <?php foreach ([
    ['Resultado de ' . $period['short'], $data['result'], 'Receitas − despesas pagas', $data['result'] < 0 ? 'red' : 'green', '<path d="M3 12h4l3-7 4 14 3-7h4"/>', \Moves\Services\AppUrl::section('movimentacoes')],
    ['A receber', $data['receivable'], $data['receivable_count'] . ' título' . ($data['receivable_count'] === 1 ? '' : 's') . ' em aberto', 'green', '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>', \Moves\Services\AppUrl::section('receber', null, ['situacao' => 'open'])],
    ['A pagar', $data['payable'], $data['payable_count'] . ' título' . ($data['payable_count'] === 1 ? '' : 's') . ' em aberto', 'red', '<path d="M7 17 17 7m0 0H9m8 0v8"/>', \Moves\Services\AppUrl::section('pagar', null, ['situacao' => 'open'])],
  ] as [$label, $value, $description, $color, $path, $href]): ?>
  <a class="card card-link" href="<?= $e($href) ?>"><div class="stat"><span class="stat-icon <?= $color === 'red' ? 'red-icon' : '' ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $path ?></svg></span><div><span class="label"><?= $e($label) ?></span><div class="value <?= $color ?>"><?= $money($value) ?></div><span class="muted"><?= $e($description) ?></span></div></div></a>
  <?php endforeach; ?>
</section>
<section class="card progress-panel" aria-label="Andamento do período">
  <strong><?= $e($period['short']) ?>: <?= $percent ?>% concluído</strong>
  <div class="track track-live" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>"><span data-width="<?= $percent ?>"><?= $percent >= 10 ? $percent . '%' : '' ?></span></div>
  <span class="muted"><?= $total - $elapsed ?> dia<?= $total - $elapsed === 1 ? '' : 's' ?> restante<?= $total - $elapsed === 1 ? '' : 's' ?> · até <?= $e($period['end']->format('d/m')) ?></span>
</section>
<section class="card due" aria-labelledby="next-due-title"><h2 class="section-title" id="next-due-title">Próximos vencimentos</h2><div class="due-grid">
<?php foreach ([['Hoje e atrasados', 'today'], ['Amanhã', 'tomorrow'], ['Próximos 7 dias', 'week'], ['Depois', 'later']] as [$label, $key]): $count = $data['due'][$key . '_count']; ?>
  <div class="due-item"><span class="stat-icon <?= $key === 'today' && $count > 0 ? 'red-icon' : '' ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.500 10h17M8 3v4m8-4v4"/></svg></span><div><small><?= $e($label) ?></small><strong><?= $money($data['due'][$key . '_total']) ?></strong><span class="muted"><?= $count ?> título<?= $count === 1 ? '' : 's' ?></span></div></div>
<?php endforeach; ?>
  <div class="forecast"><span class="green">Fechamento previsto</span><p class="muted">Saldo atual + a receber − a pagar no período.</p><strong class="<?= $data['forecast'] < 0 ? 'red' : 'green' ?>"><?= $money($data['forecast']) ?></strong></div>
</div></section>
<section class="card overview-chart-card" aria-labelledby="overview-chart-title">
  <div class="section-head"><h2 id="overview-chart-title"><?= $period['view'] === 'anual' ? 'Receitas e despesas de ' . $e($period['label']) : 'Receitas e despesas, últimos 6 meses' ?></h2><a href="<?= $e(\Moves\Services\AppUrl::section('relatorios')) ?>">Ver relatórios</a></div>
  <div class="overview-chart-layout">
    <div class="overview-chart-plot">
      <?= $appView->render('components::overview-chart', ['chartSeries' => $series]) ?>
    </div>
    <aside class="overview-chart-alerts" aria-label="Resumo de vencimentos">
      <h3>Vencimentos e alertas</h3>
      <?php foreach ([
        ['Hoje e atrasados', 'today', 'pagar'],
        ['Amanhã', 'tomorrow', 'pagar'],
        ['Próximos 7 dias', 'week', 'pagar'],
      ] as [$label, $key, $target]): $count = (int) ($data['due'][$key . '_count'] ?? 0); ?>
      <a class="overview-chart-alert" href="<?= $e(\Moves\Services\AppUrl::section($target, null, ['situacao' => 'open'])) ?>">
        <span><strong><?= $e($label) ?></strong><small><?= $count ?> título<?= $count === 1 ? '' : 's' ?></small></span>
        <b class="<?= $key === 'today' && $count > 0 ? 'red' : '' ?>"><?= $money((int) ($data['due'][$key . '_total'] ?? 0)) ?></b>
      </a>
      <?php endforeach; ?>
      <a class="overview-chart-alert-link" href="<?= $e(\Moves\Services\AppUrl::section('notificacoes')) ?>">Ver notificações</a>
    </aside>
  </div>
</section>
<section class="content-grid" aria-label="Painéis financeiros">
  <article class="card"><div class="section-head"><h2>Últimas movimentações</h2><a href="<?= $e(\Moves\Services\AppUrl::section('movimentacoes')) ?>">Ver todas</a></div>
    <?php if ($data['recent'] === []): ?><p class="muted">Nenhuma movimentação registrada.</p><?php else: ?>
    <ul class="overview-list">
      <?php foreach ($data['recent'] as $row): $in = $row['kind'] === 'income'; $transfer = $row['kind'] === 'transfer'; ?>
      <li><span><strong><?= $e($row['description']) ?></strong><small><?= $e($row['account']) ?><?= $row['target_account'] ? ' → ' . $e($row['target_account']) : '' ?> · <?= $e((new DateTimeImmutable($row['paid_at']))->format('d/m')) ?></small></span><strong class="<?= $transfer ? '' : ($in ? 'green' : 'red') ?>"><?= $transfer ? '' : ($in ? '+ ' : '− ') ?><?= $e(Finance::money((int) $row['amount_cents'])) ?></strong></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </article>
  <article class="card"><div class="section-head"><h2>Gastos por categoria</h2><a href="<?= $e(\Moves\Services\AppUrl::section('relatorios')) ?>">Ver relatórios</a></div>
    <?php if ($categoryTotal === 0): ?><p class="muted">Sem despesas no período.</p><?php else: ?>
    <div class="donut-wrap">
      <svg class="donut" viewBox="0 0 120 120" role="img" aria-label="Gastos por categoria">
        <circle class="donut-track" cx="60" cy="60" r="46"/>
        <?php $offset = 0; foreach ($data['categories'] as $category): $share = $category['total'] / $categoryTotal * 100; ?>
        <circle class="donut-slice" cx="60" cy="60" r="46" pathLength="100" stroke="<?= $e($category['color']) ?>" stroke-dasharray="<?= round($share, 3) ?> 100" stroke-dashoffset="<?= round(-$offset, 3) ?>"/>
        <?php $offset += $share; endforeach; ?>
        <text x="60" y="58" text-anchor="middle" class="donut-total"><?= $e(Finance::money($categoryTotal)) ?></text>
        <text x="60" y="72" text-anchor="middle" class="donut-label">Total</text>
      </svg>
      <ul class="donut-legend">
        <?php foreach (array_slice($data['categories'], 0, 6) as $category): ?>
        <li><span class="finance-dot" style="background:<?= $e($category['color']) ?>"></span><?= $e($category['name']) ?><b><?= (int) round($category['total'] / $categoryTotal * 100) ?>%</b></li>
        <?php endforeach; ?>
      </ul>
    </div><?php endif; ?>
  </article>
  <article class="card"><div class="section-head"><h2>Contas e cartões</h2><a href="<?= $e(\Moves\Services\AppUrl::section('contas')) ?>">Ver todas</a></div>
    <?php if ($data['accounts'] === []): ?><p class="muted">Nenhuma conta cadastrada.</p><?php else: ?>
    <ul class="overview-list">
      <?php foreach ($data['accounts'] as $account): ?>
      <li><span><strong><?= $e($account['name']) ?></strong><small><?= $account['type'] === 'credit_card' ? 'Cartão de crédito' : 'Conta' ?></small></span><strong class="<?= $account['balance_cents'] < 0 ? 'red' : 'green' ?>"><?= $e(Finance::money($account['balance_cents'])) ?></strong></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </article>
</section>
<section class="card insights" aria-label="Insights"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0-3.500 10.900c.6.500 1 1.200 1 2.100h5c0-.9.4-1.600 1-2.100A6 6 0 0 0 12 3zM10 19.500h4"/></svg></span><div><strong>Insights do período</strong><p><?= $e($insight) ?></p></div></section>
<?php foreach ([['income', 'quick-income', 'Novo recebimento', $categoriesIncome], ['expense', 'quick-expense', 'Nova despesa', $categoriesExpense]] as [$kind, $id, $title, $list]): $form = ['id' => $id, 'kind' => $kind, 'title' => $title, 'accounts' => $manual, 'categories' => $list, 'csrf' => $csrf, 'month' => $month, 'date' => $today->format('Y-m-d'), 'back' => 'overview', 'extras' => $finance->supportsEntryExtras(), 'tags' => $finance->tags()]; require __DIR__ . '/entry-form.php'; endforeach; ?>
