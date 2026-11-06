<?php
use Moves\Services\BillingReport;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\OrganizationContext;
use Moves\Services\ObligationReport;
use Moves\Services\ServiceOrderReport;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$period = Finance::period($_GET);
$billingFilters = [
    'origin' => in_array($_GET['faturamento_origem'] ?? '', ['manual','service_order'], true)
        ? (string) $_GET['faturamento_origem'] : '',
    'customer' => is_string($_GET['faturamento_cliente'] ?? null)
        ? (string) $_GET['faturamento_cliente'] : '',
];
$billingFilterQuery = array_filter([
    'faturamento_origem' => $billingFilters['origin'],
    'faturamento_cliente' => $billingFilters['customer'],
], static fn (string $value): bool => $value !== '');
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('relatorios', null, $query + $billingFilterQuery);
$monthly = $period['view'] === 'mensal';
$months = $monthly ? 6 : 12;
$loadFailed = false;
$report = null;
$series = [];
$centerRows = [];
$billing = null;
$billingPrevious = null;
$serviceOrders = null;
$obligations = null;
$accountPosition = [];
$accountPositionAsOf = min($period['end']->format('Y-m-d'),date('Y-m-d'));
$accountPositionOpeningDate = min($period['start']->modify('-1 day')->format('Y-m-d'),$accountPositionAsOf);
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $report = $finance->report($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    $openingAccounts=$finance->cashAccountsPositionOn($accountPositionOpeningDate);
    $closingAccounts=$finance->cashAccountsPositionOn($accountPositionAsOf);
    foreach($openingAccounts as$row)$accountPosition[$row['id']]=$row+['opening_cents'=>$row['balance_cents'],'closing_cents'=>0];
    foreach($closingAccounts as$row){$accountPosition[$row['id']]??=$row+['opening_cents'=>0];$accountPosition[$row['id']]['closing_cents']=$row['balance_cents'];}
    $series = $finance->monthlySeries($period['end'], $months);
    // Na empresa, o resultado também sai por centro de custo, quando há centros cadastrados.
    $centerRows = OrganizationContext::current() !== null && $finance->costCenters() !== []
        ? array_values(array_filter((new \Moves\Services\CostCenters((int) FinanceContext::currentId()))->totals($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d')), static fn (array $r): bool => $r['income_done'] + $r['income_open'] + $r['expense_done'] + $r['expense_open'] > 0))
        : [];
    if (OrganizationContext::current() !== null) {
        $billingService = new BillingReport((int) FinanceContext::currentId());
        $billing = $billingService->report(
            $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'), $billingFilters
        );
        $billingPrevious = $billingService->report(
            $period['compareStart']->format('Y-m-d'), $period['compareEnd']->format('Y-m-d'), $billingFilters
        );
        if (ServiceOrderReport::ready()) {
            $serviceOrders = (new ServiceOrderReport((int) OrganizationContext::current()['id']))->report(
                $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'), min($period['end']->format('Y-m-d'),date('Y-m-d'))
            );
        }
        if (ObligationReport::ready()) {
            $obligations=(new ObligationReport((int)OrganizationContext::current()['id']))->report(
                $period['start']->format('Y-m-d'),$period['end']->format('Y-m-d'),min($period['end']->format('Y-m-d'),date('Y-m-d'))
            );
        }
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
$billingExport = '/minha-conta/financeiro/exportar?' . http_build_query(['relatorio' => 'faturamento'] + $billingQuery + $billingFilterQuery);
$positionExport = '/minha-conta/financeiro/exportar?' . http_build_query(['relatorio'=>'posicao-contas']+$billingQuery);
$serviceOrderExport = '/minha-conta/financeiro/exportar?' . http_build_query(['relatorio'=>'ordens-servico']+$billingQuery);
$obligationExport = '/minha-conta/financeiro/exportar?' . http_build_query(['relatorio'=>'obrigacoes']+$billingQuery);
$billingStatuses = ['received' => ['Recebido','is-paid'], 'partial' => ['Parcial','is-open'], 'partial_overdue' => ['Parcial vencido','is-late'], 'overdue' => ['Vencido','is-late'], 'open' => ['Em aberto','is-open']];
$accountTypes=['checking'=>'Conta corrente','savings'=>'Poupança','wallet'=>'Carteira'];
$positionOpening=array_sum(array_column($accountPosition,'opening_cents'));
$positionClosing=array_sum(array_column($accountPosition,'closing_cents'));
$billingComparison = static function (int $current, int $previous): string {
    $delta = $current - $previous;
    return 'Anterior ' . Finance::money($previous) . ' · '
        . ($delta === 0 ? 'sem variação' : ($delta > 0 ? 'alta ' : 'queda ') . Finance::money(abs($delta)));
};
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
  <section class="report-daily-list" aria-labelledby="account-position-title">
    <div class="finance-toolbar">
      <div><h3 id="account-position-title">Posição de contas</h3><p class="muted">Saldos de contas bancárias e carteiras; cartões e seus limites não compõem o caixa.</p></div>
      <a class="finance-button" href="<?= $e($positionExport) ?>" download>Exportar posição</a>
    </div>
    <div class="finance-goal-summary is-four" aria-label="Resumo da posição de contas">
      <div class="finance-goal-stat finance-indicator is-neutral"><span>Saldo inicial</span><strong><?= $e(Finance::money($positionOpening)) ?></strong><small><?= $e($brDate($accountPositionOpeningDate)) ?></small></div>
      <?php $netMovement=$positionClosing-$positionOpening; ?><div class="finance-goal-stat finance-indicator <?= $netMovement<0?'is-negative':($netMovement>0?'is-positive':'is-neutral') ?>"><span>Movimentação líquida</span><strong><?= $e(Finance::money($netMovement)) ?></strong><small>Entradas − saídas; transferências internas são neutras</small></div>
      <div class="finance-goal-stat finance-indicator <?= $positionClosing<0?'is-negative':($positionClosing>0?'is-positive':'is-neutral') ?>"><span>Saldo na data-base</span><strong><?= $e(Finance::money($positionClosing)) ?></strong><small><?= $e($brDate($accountPositionAsOf)) ?></small></div>
      <div class="finance-goal-stat finance-indicator is-neutral"><span>Contas de caixa</span><strong><?= count($accountPosition) ?></strong><small>Conta corrente, poupança e carteira</small></div>
    </div>
    <?php if($accountPosition===[]): ?><div class="finance-empty"><h3>Nenhuma conta na data-base</h3><p>Cadastre uma conta ou carteira para acompanhar a posição.</p></div>
    <?php else: ?><div class="report-table-wrap" tabindex="0" role="region" aria-labelledby="account-position-title"><table class="report-table">
      <thead><tr><th scope="col">Conta</th><th scope="col">Tipo</th><th scope="col" class="is-money">Saldo inicial</th><th scope="col" class="is-money">Movimentação líquida</th><th scope="col" class="is-money">Saldo na data-base</th></tr></thead><tbody>
      <?php foreach($accountPosition as$row):$movement=$row['closing_cents']-$row['opening_cents']; ?><tr><td><?= $e($row['name']) ?><?php if($row['institution']):?><small><?= $e($row['institution']) ?></small><?php endif;?></td><td><?= $e($accountTypes[$row['type']]??'Conta') ?></td><td class="is-money"><?= $e(Finance::money($row['opening_cents'])) ?></td><td class="is-money <?= $movement<0?'red':($movement>0?'green':'') ?>"><?= $e(Finance::money($movement)) ?></td><td class="is-money <?= $row['closing_cents']<0?'red':'green' ?>"><strong><?= $e(Finance::money($row['closing_cents'])) ?></strong></td></tr><?php endforeach; ?>
      </tbody><tfoot><tr><th scope="row" colspan="2">Total em caixa</th><td class="is-money"><?= $e(Finance::money($positionOpening)) ?></td><td class="is-money"><?= $e(Finance::money($netMovement)) ?></td><td class="is-money"><?= $e(Finance::money($positionClosing)) ?></td></tr></tfoot>
    </table></div><?php endif; ?>
  </section>
  <?php if ($billing !== null): $bill = $billing['totals']; $billPrevious = $billingPrevious['totals'] ?? []; ?>
  <section class="report-daily-list" aria-labelledby="billing-title">
    <div class="finance-toolbar">
      <div><h3 id="billing-title">Faturamento gerencial</h3><p class="muted">Receitas reconhecidas por competência. Comparação com <?= $e($period['compareStart']->format('d/m/Y')) ?> a <?= $e($period['compareEnd']->format('d/m/Y')) ?>. Não representa emissão de nota fiscal.</p></div>
      <a class="finance-button" href="<?= $e($billingExport) ?>" download>Exportar faturamento</a>
    </div>
    <form class="finance-filter-bar no-print" method="get" action="<?= $e(\Moves\Services\AppUrl::section('relatorios')) ?>" data-swap-get>
      <?php if ($monthly): ?><input type="hidden" name="mes" value="<?= $e($period['start']->format('Y-m')) ?>">
      <?php elseif ($period['view'] === 'anual'): ?><input type="hidden" name="visao" value="anual"><input type="hidden" name="ano" value="<?= $e($period['start']->format('Y')) ?>">
      <?php else: ?><input type="hidden" name="inicio" value="<?= $e($period['start']->format('Y-m-d')) ?>"><input type="hidden" name="fim" value="<?= $e($period['end']->format('Y-m-d')) ?>"><?php endif; ?>
      <label>Origem<select name="faturamento_origem"><option value="">Todas</option><option value="manual"<?= $billing['filters']['origin'] === 'manual' ? ' selected' : '' ?>>Lançamento manual</option><option value="service_order"<?= $billing['filters']['origin'] === 'service_order' ? ' selected' : '' ?>>Ordem de serviço</option></select></label>
      <label>Cliente<select name="faturamento_cliente"><option value="">Todos</option><option value="unassigned"<?= $billing['filters']['customer'] === 'unassigned' ? ' selected' : '' ?>>Sem cliente cadastrado</option><?php foreach ($billing['customers'] as $customer): ?><option value="<?= $e($customer['key']) ?>"<?= $billing['filters']['customer'] === $customer['key'] ? ' selected' : '' ?>><?= $e($customer['label']) ?></option><?php endforeach; ?></select></label>
      <button class="finance-button" type="submit">Aplicar filtros</button>
      <?php if ($billing['filters']['origin'] !== '' || $billing['filters']['customer'] !== ''): ?><a class="finance-button" href="<?= $e(\Moves\Services\AppUrl::section('relatorios', null, $billingQuery)) ?>">Limpar</a><?php endif; ?>
    </form>
    <div class="finance-goal-summary is-four" aria-label="Indicadores de faturamento">
      <div class="finance-goal-stat finance-indicator is-forecast"><span>Faturado</span><strong><?= $e(Finance::money($bill['billed_cents'])) ?></strong><small><?= $e($billingComparison($bill['billed_cents'], (int) ($billPrevious['billed_cents'] ?? 0))) ?></small></div>
      <div class="finance-goal-stat finance-indicator is-realized"><span>Recebido no período</span><strong><?= $e(Finance::money($bill['cash_received_cents'])) ?></strong><small><?= $e($billingComparison($bill['cash_received_cents'], (int) ($billPrevious['cash_received_cents'] ?? 0))) ?></small></div>
      <div class="finance-goal-stat finance-indicator <?= $bill['open_cents'] > 0 ? 'is-forecast' : 'is-neutral' ?>"><span>Em aberto do faturado</span><strong><?= $e(Finance::money($bill['open_cents'])) ?></strong><small><?= $e($billingComparison($bill['open_cents'], (int) ($billPrevious['open_cents'] ?? 0))) ?><?= $bill['overdue_cents'] > 0 ? ' · vencido ' . $e(Finance::money($bill['overdue_cents'])) : '' ?></small></div>
      <div class="finance-goal-stat finance-indicator is-neutral"><span>Origem</span><strong><?= $e(Finance::money($bill['service_order_cents'])) ?></strong><small>Ordens de serviço · manual <?= $e(Finance::money($bill['manual_cents'])) ?></small></div>
    </div>
    <?php if ($billing['rows'] === []): ?>
    <div class="finance-empty"><h3>Nenhuma receita reconhecida</h3><p><?= $billing['filters']['origin'] !== '' || $billing['filters']['customer'] !== '' ? 'Nenhum lançamento corresponde aos filtros aplicados.' : 'As receitas de competência do período aparecerão aqui.' ?></p></div>
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
  <?php if ($serviceOrders !== null): $osTotals=$serviceOrders['totals']; $osStatus=$serviceOrders['status']; ?>
  <section class="report-daily-list" aria-labelledby="service-orders-report-title">
    <div class="finance-toolbar">
      <div><h3 id="service-orders-report-title">Operação de serviços</h3><p class="muted">Ordens iniciadas no período. Valores comerciais, sem cálculo de margem ou efeito automático no caixa.</p></div>
      <a class="finance-button" href="<?= $e($serviceOrderExport) ?>" download>Exportar ordens</a>
    </div>
    <div class="finance-goal-summary is-four" aria-label="Indicadores de ordens de serviço">
      <div class="finance-goal-stat finance-indicator is-forecast"><span>Valor das ordens</span><strong><?= $e(Finance::money($osTotals['order_cents'])) ?></strong><small><?= $osTotals['orders'] ?> ordens · descontos <?= $e(Finance::money($osTotals['discount_cents'])) ?></small></div>
      <div class="finance-goal-stat finance-indicator is-realized"><span>Concluídas</span><strong><?= $e(Finance::money($osTotals['completed_cents'])) ?></strong><small><?= $osStatus['completed'] ?> concluídas · <?= $osStatus['in_progress'] ?> em andamento</small></div>
      <div class="finance-goal-stat finance-indicator <?= $osTotals['billed_cents']>0?'is-realized':'is-neutral' ?>"><span>Com cobrança vinculada</span><strong><?= $e(Finance::money($osTotals['billed_cents'])) ?></strong><small>Recebido <?= $e(Finance::money($osTotals['received_cents'])) ?> · aberto <?= $e(Finance::money($osTotals['open_cents'])) ?></small></div>
      <div class="finance-goal-stat finance-indicator <?= $osTotals['unbilled_cents']>0?'is-forecast':'is-neutral' ?>"><span>Sem cobrança</span><strong><?= $e(Finance::money($osTotals['unbilled_cents'])) ?></strong><small>Gerar cobrança é opcional e não altera o saldo bancário</small></div>
    </div>
    <?php if($serviceOrders['rows']===[]): ?><div class="finance-empty"><h3>Nenhuma ordem no período</h3><p>Ordens de serviço iniciadas no período aparecerão aqui.</p></div>
    <?php else: ?><div class="report-table-wrap" tabindex="0" role="region" aria-labelledby="service-orders-report-title"><table class="report-table">
      <thead><tr><th scope="col">Ordem e cliente</th><th scope="col">Período</th><th scope="col">Situação</th><th scope="col" class="is-money">Valor</th><th scope="col" class="is-money">Cobrança</th><th scope="col" class="is-money">Recebido</th></tr></thead><tbody>
      <?php $osLabels=['draft'=>'Rascunho','open'=>'Aberta','in_progress'=>'Em andamento','completed'=>'Concluída','cancelled'=>'Cancelada']; foreach(array_slice($serviceOrders['rows'],0,100) as$row): ?>
      <tr class="<?= $row['status']==='cancelled'?'is-cancelled':'' ?>"><td><?= $e($row['order_number']) ?> · <?= $e($row['customer_name']) ?><small><?= $e($row['description']?:'Sem descrição') ?><?= $row['responsible_name']?' · '.$e($row['responsible_name']):'' ?></small></td><td><?= $e($row['start_date']?$brDate($row['start_date']):substr($brDate(substr($row['created_at'],0,10)),0,10)) ?><small><?= $row['due_date']?'Prazo '.$e($brDate($row['due_date'])):'Sem prazo' ?></small></td><td><span class="report-situation <?= $row['status']==='completed'?'is-paid':($row['status']==='cancelled'?'is-muted':'is-open') ?>"><?= $e($osLabels[$row['status']]) ?></span></td><td class="is-money"><?= $e(Finance::money($row['total_cents'])) ?></td><td class="is-money"><?= $row['billed_cents']>0?$e(Finance::money($row['billed_cents'])):'—' ?></td><td class="is-money green"><?= $row['received_cents']>0?$e(Finance::money($row['received_cents'])):'—' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div><?php if(count($serviceOrders['rows'])>100): ?><p class="report-note">Exibindo as primeiras 100 ordens. O CSV contém todo o período.</p><?php endif; endif; ?>
  </section>
  <?php endif; ?>
  <?php if($obligations!==null):$obTotals=$obligations['totals'];$obStatus=$obligations['status']; ?>
  <section class="report-daily-list" aria-labelledby="obligations-report-title">
    <div class="finance-toolbar"><div><h3 id="obligations-report-title">Agenda de obrigações</h3><p class="muted">Controle manual por vencimento. Não calcula impostos nem gera despesas.</p></div><a class="finance-button" href="<?= $e($obligationExport) ?>" download>Exportar obrigações</a></div>
    <div class="finance-goal-summary is-four" aria-label="Indicadores de obrigações">
      <div class="finance-goal-stat finance-indicator is-neutral"><span>Obrigações</span><strong><?= $obTotals['obligations'] ?></strong><small>Valor informado <?= $e(Finance::money($obTotals['declared_cents'])) ?></small></div>
      <div class="finance-goal-stat finance-indicator is-realized"><span>Concluídas</span><strong><?= $obStatus['completed'] ?></strong><small>Valor informado <?= $e(Finance::money($obTotals['completed_cents'])) ?></small></div>
      <div class="finance-goal-stat finance-indicator <?= $obStatus['overdue']>0?'is-overdue':'is-neutral' ?>"><span>Vencidas</span><strong><?= $obStatus['overdue'] ?></strong><small>Valor informado <?= $e(Finance::money($obTotals['overdue_cents'])) ?></small></div>
      <div class="finance-goal-stat finance-indicator is-forecast"><span>Com comprovante</span><strong><?= $obTotals['with_proof'] ?></strong><small><?= $obStatus['upcoming'] ?> vencem em até 7 dias da data-base</small></div>
    </div>
    <?php if($obligations['rows']===[]):?><div class="finance-empty"><h3>Nenhuma obrigação no período</h3><p>Obrigações com vencimento no período aparecerão aqui.</p></div>
    <?php else:?><div class="report-table-wrap" tabindex="0" role="region" aria-labelledby="obligations-report-title"><table class="report-table"><thead><tr><th scope="col">Obrigação</th><th scope="col">Competência</th><th scope="col">Vencimento</th><th scope="col">Responsável</th><th scope="col">Situação</th><th scope="col">Comprovantes</th><th scope="col" class="is-money">Valor informado</th></tr></thead><tbody>
      <?php $obLabels=['pending'=>'Pendente','upcoming'=>'Próxima','overdue'=>'Vencida','completed'=>'Concluída','cancelled'=>'Cancelada'];foreach(array_slice($obligations['rows'],0,100)as$row):?><tr class="<?= $row['display_status']==='cancelled'?'is-cancelled':'' ?>"><td><?= $e($row['title']) ?><small><?= $e($row['obligation_type']) ?> · <?= $e($row['frequency']) ?></small></td><td><?= $e((new DateTimeImmutable($row['competence_date']))->format('m/Y')) ?></td><td><?= $e($brDate($row['due_date'])) ?></td><td><?= $e($row['responsible_name']?:'Não definido') ?></td><td><span class="report-situation <?= $row['display_status']==='completed'?'is-paid':($row['display_status']==='overdue'?'is-late':($row['display_status']==='cancelled'?'is-muted':'is-open')) ?>"><?= $e($obLabels[$row['display_status']]) ?></span></td><td><?= $row['attachment_count'] ?></td><td class="is-money"><?= $row['amount_cents']===null?'—':$e(Finance::money($row['amount_cents'])) ?></td></tr><?php endforeach;?></tbody></table></div><?php if(count($obligations['rows'])>100):?><p class="report-note">Exibindo as primeiras 100 obrigações. O CSV contém todo o período.</p><?php endif;endif;?>
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
