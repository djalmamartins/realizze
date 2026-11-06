<?php
use Moves\Services\BillingReport;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\OrganizationContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$period = Finance::period($_GET);
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('relatorios', null, $query);
$monthly = $period['view'] === 'mensal';
$months = $monthly ? 6 : 12;
$loadFailed = false;
$report = null;
$series = [];
$centerRows = [];
$billing = null;
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $report = $finance->report($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    $series = $finance->monthlySeries($period['end'], $months);
    // Na empresa, o resultado também sai por centro de custo, quando há centros cadastrados.
    $centerRows = OrganizationContext::current() !== null && $finance->costCenters() !== []
        ? array_values(array_filter((new \Moves\Services\CostCenters((int) FinanceContext::currentId()))->totals($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d')), static fn (array $r): bool => $r['income_done'] + $r['income_open'] + $r['expense_done'] + $r['expense_open'] > 0))
        : [];
    if (OrganizationContext::current() !== null) {
        $billing = (new BillingReport((int) FinanceContext::currentId()))->report($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    }
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$short = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
$weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$brDate = static fn (string $date): string => (new DateTimeImmutable($date))->format('d/m/Y');
$height = static fn (int $value, int $max): string => 'height:' . round($value / max(1, $max) * 100, 1) . '%';
$holder = OrganizationContext::current()['name'] ?? 'Meu financeiro';
$totals = $report['totals'] ?? [];
$resultPaid = ($totals['income_paid'] ?? 0) - ($totals['expense_paid'] ?? 0);
$resultExpected = ($totals['income'] ?? 0) - ($totals['expense'] ?? 0);
$asOfLabel = isset($report['as_of']) ? $brDate((string)$report['as_of']) : $period['end']->format('d/m/Y');
$seriesMax = max([1, ...array_column($series, 'income'), ...array_column($series, 'expense')]);
$daily = [];
if ($monthly && $report !== null) {
    foreach ($report['days'] as $day) {
        $daily[(int) substr($day['date'], 8, 2)] = $day;
    }
}
$dailyMax = max([1, ...array_column($daily, 'income'), ...array_column($daily, 'expense')]);
$categoryRows = array_slice($report['categories'] ?? [], 0, 5);
$otherCategories = array_sum(array_column(array_slice($report['categories'] ?? [], 5), 'total'));
if ($otherCategories > 0) {
    $categoryRows[] = ['name' => 'Outras', 'color' => '#98a2b3', 'total' => $otherCategories];
}
$categoryTotal = array_sum(array_column($report['categories'] ?? [], 'total'));
$situation = static function (array $entry) use ($brDate): array {
    if ($entry['cancelled']) return ['Cancelado', 'is-muted'];
    if ($entry['paid_cents'] >= $entry['amount_cents']) {
        $word = ['income' => 'Recebido', 'expense' => 'Pago', 'transfer' => 'Transferido'][$entry['kind']];
        return [$word . ($entry['paid_at'] !== null ? ' em ' . substr($brDate((string) $entry['paid_at']), 0, 5) : ''), 'is-paid'];
    }
    if ($entry['paid_cents'] > 0) return ['Parcial ' . Finance::money($entry['paid_cents']) . ' · falta ' . Finance::money($entry['open_cents']), $entry['overdue'] ? 'is-late' : 'is-open'];
    if ($entry['overdue']) return ['Vencido', 'is-late'];
    return [$entry['kind'] === 'income' ? 'A receber' : ($entry['kind'] === 'expense' ? 'A pagar' : 'Pendente'), 'is-open'];
};
$billingQuery = $monthly ? ['mes' => $period['start']->format('Y-m')]
    : ($period['view'] === 'anual' ? ['visao' => 'anual', 'ano' => $period['start']->format('Y')]
        : ['inicio' => $period['start']->format('Y-m-d'), 'fim' => $period['end']->format('Y-m-d')]);
$billingExport = '/minha-conta/financeiro/exportar?' . http_build_query(['relatorio' => 'faturamento'] + $billingQuery);
$billingStatuses = ['received' => ['Recebido','is-paid'], 'partial' => ['Parcial','is-open'], 'partial_overdue' => ['Parcial vencido','is-late'], 'overdue' => ['Vencido','is-late'], 'open' => ['Em aberto','is-open']];
?>
<section class="card finance-workspace finance-report" aria-labelledby="reports-title" data-print-title="Realizze - Relatório - <?= $e($period['long']) ?>">
  <header class="report-print-head">
    <img src="/assets/realizze/v1/images/<?= $activeOrganization !== null ? 'new-logo-realizze-empresa.svg' : 'new-logo-realizze.svg' ?>" alt="Realizze">
    <div><strong>Relatório financeiro: <?= $e($period['long']) ?></strong><span><?= $e($holder) ?> · Período de <?= $e($period['start']->format('d/m/Y')) ?> a <?= $e($period['end']->format('d/m/Y')) ?> · Emitido em <?= $e(date('d/m/Y H:i')) ?></span></div>
  </header>
  <div class="finance-toolbar no-print">
    <div><h2 id="reports-title">Relatórios</h2><p class="muted">Resultado de <?= $e($period['long']) ?>, dia a dia.</p></div>
    <div class="finance-toolbar-actions">
      <a class="finance-button" href="/minha-conta/financeiro/exportar?<?= $e(http_build_query($monthly ? ['mes' => $period['start']->format('Y-m')] : ['visao' => 'anual', 'ano' => $period['start']->format('Y')])) ?>" download>Exportar CSV</a>
      <button type="button" class="primary" data-finance-print>Gerar PDF</button>
    </div>
  </div>
  <div class="finance-filter-bar no-print">
    <nav class="finance-month" aria-label="Período"><a href="<?= $e($link($period['previous'])) ?>" aria-label="Período anterior">‹</a><strong><?= $e($period['long']) ?></strong><a href="<?= $e($link($period['next'])) ?>" aria-label="Próximo período">›</a></nav>
    <nav class="finance-status" aria-label="Visão"><a href="<?= $e($link($monthly ? ['mes' => $period['start']->format('Y-m')] : $period['other'])) ?>"<?= $monthly ? ' aria-current="true"' : '' ?>>Mensal</a><a href="<?= $e($link($monthly ? $period['other'] : ['visao' => 'anual', 'ano' => $period['start']->format('Y')])) ?>"<?= $monthly ? '' : ' aria-current="true"' ?>>Anual</a></nav>
  </div>
  <?php if ($loadFailed || $report === null): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os relatórios</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php else: ?>
  <div class="finance-goal-summary is-four" aria-label="Indicadores do período">
    <div class="finance-goal-stat finance-indicator <?= $totals['income_paid'] > 0 ? 'is-realized' : 'is-neutral' ?>"><span>Receitas do período</span><strong><?= $e(Finance::money($totals['income'])) ?></strong><small>Realizado até <?= $e($asOfLabel) ?>: <?= $e(Finance::money($totals['income_paid'])) ?> · aberto <?= $e(Finance::money($totals['income'] - $totals['income_paid'])) ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $totals['expense_paid'] > 0 ? 'is-realized' : 'is-neutral' ?>"><span>Despesas do período</span><strong><?= $e(Finance::money($totals['expense'])) ?></strong><small>Realizado até <?= $e($asOfLabel) ?>: <?= $e(Finance::money($totals['expense_paid'])) ?> · aberto <?= $e(Finance::money($totals['expense'] - $totals['expense_paid'])) ?></small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Resultado previsto</span><strong><?= $e(Finance::money($resultExpected)) ?></strong><small>Realizado na data-base <?= $e(Finance::money($resultPaid)) ?></small></div>
    <?php $overdueTotal = $totals['expense_overdue'] + $totals['income_overdue']; ?><div class="finance-goal-stat finance-indicator <?= $overdueTotal > 0 ? 'is-overdue' : 'is-neutral' ?>"><span>Em atraso</span><strong><?= $e(Finance::money($overdueTotal)) ?></strong><small>A pagar <?= $e(Finance::money($totals['expense_overdue'])) ?> · a receber <?= $e(Finance::money($totals['income_overdue'])) ?></small></div>
  </div>
  <?php if ($billing !== null): $bill = $billing['totals']; ?>
  <section class="report-daily-list" aria-labelledby="billing-title">
    <div class="finance-toolbar">
      <div><h3 id="billing-title">Faturamento gerencial</h3><p class="muted">Receitas reconhecidas por competência. Não representa emissão de nota fiscal.</p></div>
      <a class="finance-button" href="<?= $e($billingExport) ?>" download>Exportar faturamento</a>
    </div>
    <div class="finance-goal-summary is-four" aria-label="Indicadores de faturamento">
      <div class="finance-goal-stat finance-indicator is-forecast"><span>Faturado</span><strong><?= $e(Finance::money($bill['billed_cents'])) ?></strong><small><?= (int) $bill['entries'] ?> <?= $bill['entries'] === 1 ? 'receita reconhecida' : 'receitas reconhecidas' ?></small></div>
      <div class="finance-goal-stat finance-indicator is-realized"><span>Recebido no período</span><strong><?= $e(Finance::money($bill['cash_received_cents'])) ?></strong><small>Baixas pela data efetiva, de qualquer competência</small></div>
      <div class="finance-goal-stat finance-indicator <?= $bill['open_cents'] > 0 ? 'is-forecast' : 'is-neutral' ?>"><span>Em aberto do faturado</span><strong><?= $e(Finance::money($bill['open_cents'])) ?></strong><small>Posição no fim do período<?= $bill['overdue_cents'] > 0 ? ' · vencido ' . $e(Finance::money($bill['overdue_cents'])) : '' ?></small></div>
      <div class="finance-goal-stat finance-indicator is-neutral"><span>Origem</span><strong><?= $e(Finance::money($bill['service_order_cents'])) ?></strong><small>Ordens de serviço · manual <?= $e(Finance::money($bill['manual_cents'])) ?></small></div>
    </div>
    <?php if ($billing['rows'] === []): ?>
    <div class="finance-empty"><h3>Nenhuma receita reconhecida</h3><p>As receitas de competência do período aparecerão aqui.</p></div>
    <?php else: ?>
    <div class="report-table-wrap" tabindex="0" role="region" aria-labelledby="billing-title">
      <table class="report-table">
        <thead><tr><th scope="col">Competência</th><th scope="col">Cliente e lançamento</th><th scope="col">Origem</th><th scope="col">Situação no fim do período</th><th scope="col" class="is-money">Faturado</th><th scope="col" class="is-money">Recebido</th><th scope="col" class="is-money">Em aberto</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($billing['rows'],0,100) as $row): [$billingLabel,$billingTone] = $billingStatuses[$row['status']]; ?>
        <tr>
          <td><?= $e((new DateTimeImmutable($row['competency_date']))->format('m/Y')) ?><small>Vence <?= $e($brDate($row['due_date'])) ?></small></td>
          <td><?= $e($row['customer_name'] ?: 'Cliente não informado') ?><small>#<?= (int) $row['id'] ?> · <?= $e($row['description']) ?></small></td>
          <td><?= $row['origin'] === 'service_order' ? 'OS ' . $e($row['order_number']) : 'Manual' ?></td>
          <td><span class="report-situation <?= $billingTone ?>"><?= $billingLabel ?></span></td>
          <td class="is-money"><?= $e(Finance::money($row['amount_cents'])) ?></td>
          <td class="is-money green"><?= $e(Finance::money($row['received_cents'])) ?></td>
          <td class="is-money <?= $row['open_cents'] > 0 ? 'red' : '' ?>"><?= $e(Finance::money($row['open_cents'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (count($billing['rows']) > 100): ?><p class="report-note">Exibindo os primeiros 100 lançamentos. O CSV contém todo o período.</p><?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <div class="finance-report-panels<?= $monthly ? ' has-daily' : '' ?>">
    <?php if ($monthly): ?>
    <section class="finance-report-panel"><h3>Receitas e despesas por dia</h3>
      <div class="bars report-daily" role="img" aria-label="Gráfico de barras de receitas e despesas por dia do mês">
        <?php for ($dayNumber = 1, $last = (int) $period['end']->format('j'); $dayNumber <= $last; $dayNumber++): $point = $daily[$dayNumber] ?? ['income' => 0, 'expense' => 0]; ?>
        <div class="bars-month"><div class="bars-pair"><span class="bar bar-income" style="<?= $height($point['income'], $dailyMax) ?>" title="Dia <?= $dayNumber ?>, receitas: <?= $e(Finance::money($point['income'])) ?>"></span><span class="bar bar-expense" style="<?= $height($point['expense'], $dailyMax) ?>" title="Dia <?= $dayNumber ?>, despesas: <?= $e(Finance::money($point['expense'])) ?>"></span></div><small><?= $dayNumber === 1 || $dayNumber % 5 === 0 ? $dayNumber : '&nbsp;' ?></small></div>
        <?php endfor; ?>
      </div>
      <p class="bars-legend"><span class="finance-dot" style="background:#078b4c"></span>Receitas <span class="finance-dot" style="background:#e5484d;margin-left:12px"></span>Despesas</p>
    </section>
    <?php endif; ?>
    <section class="finance-report-panel"><h3>Recebido e pago, <?= $monthly ? 'últimos 6 meses' : 'mês a mês' ?></h3>
      <div class="bars" role="img" aria-label="Gráfico de barras de valores recebidos e pagos por mês">
        <?php foreach ($series as $key => $values): ?>
        <div class="bars-month"><div class="bars-pair"><span class="bar bar-income" style="<?= $height($values['income'], $seriesMax) ?>" title="Recebido: <?= $e(Finance::money($values['income'])) ?>"></span><span class="bar bar-expense" style="<?= $height($values['expense'], $seriesMax) ?>" title="Pago: <?= $e(Finance::money($values['expense'])) ?>"></span></div><small><?= $e($short[substr((string) $key, 5)]) ?></small></div>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="finance-report-panel"><h3>Despesas por categoria</h3>
      <?php if ($categoryRows === []): ?><p class="muted">Sem despesas no período.</p><?php else: ?>
      <ul class="report-bars">
        <?php foreach ($categoryRows as $category): ?>
        <li><span><?= $e($category['name']) ?><b><?= $e(Finance::money($category['total'])) ?></b></span><div class="track"><span style="width:<?= round($category['total'] / max(1, $categoryTotal) * 100, 1) ?>%;background:<?= $e($category['color']) ?>"></span></div></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>
    <?php if ($centerRows !== []): ?>
    <section class="finance-report-panel"><h3>Resultado por centro de custo</h3>
      <ul class="report-bars">
        <?php foreach ($centerRows as $center): $centerResult = $center['income_done'] - $center['expense_done']; ?>
        <li><span><?= $e($center['name']) ?><b class="<?= $centerResult < 0 ? 'red' : '' ?>"><?= $e(Finance::money($centerResult)) ?></b></span><small class="muted">Recebido <?= $e(Finance::money($center['income_done'])) ?> · pago <?= $e(Finance::money($center['expense_done'])) ?><?= $center['income_open'] + $center['expense_open'] > 0 ? ' · em aberto ' . $e(Finance::money($center['income_open'] - $center['expense_open'])) : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>
  <section class="report-daily-list" aria-labelledby="report-days-title">
    <h3 id="report-days-title">Pagar e receber <small><?= (int) $report['count'] ?> <?= $report['count'] === 1 ? 'lançamento' : 'lançamentos' ?> por vencimento · posição em <?= $e($asOfLabel) ?></small></h3>
    <?php if ($report['days'] === []): ?>
    <div class="finance-empty"><h3>Nenhum lançamento neste período</h3><p>Os lançamentos de <?= $e($period['long']) ?> aparecem aqui, um a um.</p></div>
    <?php else: ?>
    <div class="report-table-wrap" tabindex="0" role="region" aria-labelledby="report-days-title">
      <table class="report-table">
        <thead><tr><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Situação</th><th class="is-money">Entrada</th><th class="is-money">Saída</th></tr></thead>
        <?php foreach ($report['days'] as $day): $date = new DateTimeImmutable($day['date']); ?>
        <tbody>
          <tr class="report-day">
            <th scope="rowgroup" colspan="4"><?= $e($date->format('d/m/Y')) ?> <em><?= $e($weekdays[(int) $date->format('w')]) ?></em><span>Resultado do dia <?= $e(Finance::money($day['result'])) ?> · acumulado <?= $e(Finance::money($day['accumulated'])) ?></span></th>
            <td class="is-money green"><?= $day['income'] > 0 ? $e(Finance::money($day['income'])) : '' ?></td>
            <td class="is-money red"><?= $day['expense'] > 0 ? $e(Finance::money($day['expense'])) : '' ?></td>
          </tr>
          <?php foreach ($day['entries'] as $entry): [$label, $tone] = $situation($entry); $money = $e(Finance::money((int) $entry['amount_cents'])); ?>
          <tr class="<?= $entry['cancelled'] ? 'is-cancelled' : '' ?>">
            <td><?= $e($entry['description']) ?><?php if (($entry['counterparty_name'] ?? '') !== ''): ?><small><?= $e($entry['counterparty_name']) ?></small><?php endif; ?></td>
            <td><?= $e($entry['kind'] === 'transfer' ? 'Transferência' : ($entry['category'] ?? 'Sem categoria')) ?></td>
            <td><?= $e($entry['account']) ?><?= $entry['kind'] === 'transfer' && $entry['target_account'] !== null ? ' → ' . $e($entry['target_account']) : '' ?></td>
            <td><span class="report-situation <?= $tone ?>"><?= $e($label) ?></span></td>
            <?php if ($entry['kind'] === 'transfer'): ?><td class="is-money is-neutral" colspan="2"><?= $money ?></td>
            <?php elseif ($entry['kind'] === 'income'): ?><td class="is-money"><?= $money ?></td><td></td>
            <?php else: ?><td></td><td class="is-money"><?= $money ?></td><?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
        <tfoot><tr><th scope="row" colspan="4">Total do período <span>Resultado previsto <?= $e(Finance::money($resultExpected)) ?></span></th><td class="is-money green"><?= $e(Finance::money($totals['income'])) ?></td><td class="is-money red"><?= $e(Finance::money($totals['expense'])) ?></td></tr></tfoot>
      </table>
    </div>
    <p class="report-note">Transferências entre contas e lançamentos cancelados aparecem na lista, mas não entram nos totais. Dias sem lançamentos não aparecem.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</section>
