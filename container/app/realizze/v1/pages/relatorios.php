<?php
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$period = Finance::period($_GET);
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('relatorios', null, $query);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$loadFailed = false;
$data = null;
$series = [];
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $data = $finance->overview($period['start'], $period['end'], $period['compareStart'], $period['compareEnd']);
    $series = $finance->monthlySeries($period['end']);
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$max = max([1, ...array_merge(...array_map(static fn (array $m): array => [$m['income'], $m['expense']], array_values($series ?: [['income' => 0, 'expense' => 0]])))]);
$short = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
$categoryTotal = $data ? array_sum(array_column($data['categories'], 'total')) : 0;
?>
<section class="card finance-workspace" aria-labelledby="reports-title">
  <div class="finance-toolbar">
    <div><h2 id="reports-title">Relatórios</h2><p class="muted">Resultado de <?= $e($period['long']) ?> e evolução recente.</p></div>
    <div class="finance-toolbar-actions no-print">
      <a class="finance-button" href="/minha-conta/financeiro/exportar?<?= $e(http_build_query($period['view'] === 'anual' ? ['visao' => 'anual', 'ano' => $period['start']->format('Y')] : ['mes' => $period['start']->format('Y-m')])) ?>" download>Exportar CSV</a>
      <button type="button" class="primary" data-finance-print>Salvar em PDF</button>
    </div>
  </div>
  <div class="finance-filter-bar no-print">
    <nav class="finance-month" aria-label="Período"><a href="<?= $e($link($period['previous'])) ?>" aria-label="Período anterior">‹</a><strong><?= $e($period['long']) ?></strong><a href="<?= $e($link($period['next'])) ?>" aria-label="Próximo período">›</a></nav>
    <nav class="finance-status" aria-label="Visão"><a href="<?= $e($link($period['view'] === 'mensal' ? ['mes' => $period['start']->format('Y-m')] : $period['other'])) ?>"<?= $period['view'] === 'mensal' ? ' aria-current="true"' : '' ?>>Mensal</a><a href="<?= $e($link($period['view'] === 'anual' ? ['visao' => 'anual', 'ano' => $period['start']->format('Y')] : $period['other'])) ?>"<?= $period['view'] === 'anual' ? ' aria-current="true"' : '' ?>>Anual</a></nav>
  </div>
  <?php if ($loadFailed || $data === null): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os relatórios</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php else: ?>
  <div class="finance-goal-summary" aria-label="Resultado do período">
    <div class="finance-goal-stat"><span>Receitas pagas</span><strong class="green"><?= $e(Finance::money($data['income_paid'])) ?></strong><small>Recebidas no período</small></div>
    <div class="finance-goal-stat"><span>Despesas pagas</span><strong class="red"><?= $e(Finance::money($data['expense_paid'])) ?></strong><small>Pagas no período</small></div>
    <div class="finance-goal-stat"><span>Resultado</span><strong class="<?= $data['result'] < 0 ? 'red' : 'green' ?>"><?= $e(Finance::money($data['result'])) ?></strong><small>Receitas − despesas</small></div>
  </div>
  <div class="finance-report-panels">
    <section class="finance-report-panel"><h3>Receitas e despesas, últimos 6 meses</h3>
      <div class="bars" role="img" aria-label="Gráfico de barras de receitas e despesas por mês">
        <?php foreach ($series as $key => $values): ?>
        <div class="bars-month"><div class="bars-pair"><span class="bar bar-income" data-height="<?= round($values['income'] / $max * 100, 1) ?>" title="Receitas: <?= $e(Finance::money($values['income'])) ?>"></span><span class="bar bar-expense" data-height="<?= round($values['expense'] / $max * 100, 1) ?>" title="Despesas: <?= $e(Finance::money($values['expense'])) ?>"></span></div><small><?= $e($short[substr((string) $key, 5)]) ?></small></div>
        <?php endforeach; ?>
      </div>
      <p class="bars-legend"><span class="finance-dot" style="background:#078b4c"></span>Receitas <span class="finance-dot" style="background:#e5484d;margin-left:12px"></span>Despesas</p>
    </section>
    <section class="finance-report-panel"><h3>Despesas por categoria</h3>
      <?php if ($data['categories'] === []): ?><p class="muted">Sem despesas no período.</p><?php else: ?>
      <ul class="report-bars">
        <?php foreach ($data['categories'] as $category): $share = $categoryTotal ? $category['total'] / $categoryTotal * 100 : 0; ?>
        <li><span><?= $e($category['name']) ?><b><?= $e(Finance::money($category['total'])) ?></b></span><div class="track"><span data-width="<?= round($share, 1) ?>" style="background:<?= $e($category['color']) ?>"></span></div></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
</section>
