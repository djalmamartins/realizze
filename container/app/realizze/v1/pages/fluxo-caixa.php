<?php
/** Fluxo de caixa do contexto atual, calculado dos lançamentos reais. Previsto e realizado nunca se somam. */
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$period=Finance::period($_GET);
$groupingValue=$_GET['agrupamento']??$_GET['visao']??'';
$grouping=in_array($groupingValue,['dia','semana'],true)?$groupingValue:'semana';
$start=$period['start']; $end=$period['end'];
$today = new DateTimeImmutable('today');
$periodQuery=$period['view']==='personalizado'?['inicio'=>$start->format('Y-m-d'),'fim'=>$end->format('Y-m-d')]:['mes'=>$start->format('Y-m')];
$link=static fn(array$query,string$view):string=>AppUrl::section('fluxo-de-caixa',null,$query+['agrupamento'=>$view]);
$exportUrl='/minha-conta/financeiro/exportar?'.http_build_query(['relatorio'=>'fluxo-caixa','inicio'=>$start->format('Y-m-d'),'fim'=>$end->format('Y-m-d')]);
$buckets = [];
$traceRows=[];
$totals = ['in_done' => 0, 'out_done' => 0, 'in_open' => 0, 'out_open' => 0];
$overdue = 0;
$openingBalance = 0;
$closingBalance = 0;
$projected = 0;
$deficitPeriods = 0;
$failed = false;
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $openingBalance = $finance->cashBalanceOn($start->modify('-1 day')->format('Y-m-d'));
    $bucketOf = static function (DateTimeImmutable $date) use ($grouping,$start,$end): string {
        if ($grouping === 'dia') return $date->format('d/m/Y');
        $offset=(int)$start->diff($date)->days;
        $from=$start->modify('+'.(intdiv($offset,7)*7).' days');
        $candidate=$from->modify('+6 days'); $to=$candidate>$end?$end:$candidate;
        return $from->format('d/m').' a '.$to->format('d/m');
    };
    if ($grouping === 'semana') {
        for($cursor=$start;$cursor<=$end;$cursor=$cursor->modify('+7 days')){
            $buckets[$bucketOf($cursor)]=['in_done'=>0,'out_done'=>0,'in_open'=>0,'out_open'=>0];
        }
    }
    $add = static function (string $key, string $column, int $cents) use (&$buckets, &$totals): void {
        $buckets[$key] ??= ['in_done' => 0, 'out_done' => 0, 'in_open' => 0, 'out_open' => 0];
        $buckets[$key][$column] += $cents;
        $totals[$column] += $cents;
    };
    // Cada baixa parcial entra na data em que o dinheiro chegou.
    foreach ($finance->cashMovements($start->format('Y-m-d'), $end->format('Y-m-d')) as $row) {
        $add($bucketOf(new DateTimeImmutable($row['date'])), $row['kind'] === 'income' ? 'in_done' : 'out_done', $row['amount_cents']);
        $traceRows[]=$row+['phase'=>'realized'];
    }
    // Receitas vêm de Receivables, que conhece cancelamentos; Finance::entries não informa a situação.
    $open=array_values(array_filter(array_map(static fn(array$row):array=>['amount_cents'=>max(0,(int)$row['amount_cents']-(int)$row['settled_cents']),'column'=>'out_open']+$row,$finance->entries('expense',$start->format('Y-m-d'),$end->format('Y-m-d'),'open')),static fn(array$row):bool=>$row['amount_cents']>0));
    $receivables = new \Moves\Services\Receivables((int) FinanceContext::currentId());
    $page = 1;
    do {
        $listing = $receivables->listing(['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'page' => $page, 'per_page' => 50]);
        foreach ($listing['rows'] as $row) {
            // Previsto é só o que falta receber.
            if (!in_array($row['display_status'], ['cancelled', 'received'], true)) $open[] = ['amount_cents' => (int) $row['balance_cents']] + $row + ['column' => 'in_open'];
        }
    } while ($page++ < $listing['pages']);
    foreach ($open as $row) {
        $due = new DateTimeImmutable($row['due_date']);
        $add($bucketOf($due), $row['column'], (int) $row['amount_cents']);
        if ($due < $today) $overdue += (int) $row['amount_cents'];
        $traceRows[]=['entry_id'=>(int)$row['id'],'kind'=>$row['column']==='in_open'?'income':'expense','date'=>$row['due_date'],'amount_cents'=>(int)$row['amount_cents'],'description'=>$row['description'],'account'=>$row['account']??'','category'=>$row['category']??null,'source'=>'open','phase'=>'expected'];
    }
    if($grouping==='dia')uksort($buckets,static fn(string$a,string$b):int=>(DateTimeImmutable::createFromFormat('!d/m/Y',$a))<=>(DateTimeImmutable::createFromFormat('!d/m/Y',$b)));

    $runningActual = $openingBalance;
    $runningProjected = $openingBalance;
    foreach ($buckets as &$row) {
        $runningActual += $row['in_done'] - $row['out_done'];
        $runningProjected += $row['in_done'] + $row['in_open'] - $row['out_done'] - $row['out_open'];
        $row['actual_balance'] = $runningActual;
        $row['projected_balance'] = $runningProjected;
        if ($runningProjected < 0) $deficitPeriods++;
    }
    unset($row);
    $closingBalance = $openingBalance + $totals['in_done'] - $totals['out_done'];
    $projected = $closingBalance + $totals['in_open'] - $totals['out_open'];
} catch (Throwable $exception) {
    error_log('Realizze cash flow: ' . $exception->getMessage());
    $failed = true;
}
$hasData = array_sum($totals) > 0;
?>
<section class="card finance-workspace" aria-labelledby="cash-title">
  <?= $appView->render('components::report-heading', [
      'reportHeading' => ['id' => 'cash-title', 'title' => 'Fluxo de caixa', 'description' => 'Realizado é o que já foi pago ou recebido. Previsto é o saldo que continua em aberto no período.'],
      'reportActions' => [['label' => 'Exportar fluxo', 'href' => $exportUrl, 'download' => true]],
  ]) ?>
  <div class="finance-goal-summary cash-summary" aria-label="Resumo do mês">
    <div class="finance-goal-stat finance-indicator <?= $openingBalance > 0 ? 'is-positive' : ($openingBalance < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Saldo inicial</span><strong><?= $e(Finance::money($openingBalance)) ?></strong><small>Contas e carteiras no início do período</small></div>
    <div class="finance-goal-stat finance-indicator <?= $closingBalance > 0 ? 'is-positive' : ($closingBalance < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Saldo realizado</span><strong><?= $e(Finance::money($closingBalance)) ?></strong><small>Saldo inicial + entradas − saídas realizadas</small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Previsto em aberto</span><strong><?= $e(Finance::money($totals['in_open'] - $totals['out_open'])) ?></strong><small><?= $overdue > 0 ? $e(Finance::money($overdue)) . ' já vencidos' : 'A receber menos a pagar' ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $projected < 0 ? 'is-negative' : 'is-forecast' ?>"><span>Saldo projetado</span><strong><?= $e(Finance::money($projected)) ?></strong><small><?= $deficitPeriods > 0 ? $deficitPeriods . ' período(s) com déficit projetado' : 'Se tudo em aberto se confirmar' ?></small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Período">
      <a href="<?= $e($link($period['previous'],$grouping)) ?>" aria-label="Período anterior">‹</a>
      <strong><?= $e($period['long']) ?></strong>
      <a href="<?= $e($link($period['next'],$grouping)) ?>" aria-label="Próximo período">›</a>
    </nav>
    <nav class="finance-status" aria-label="Agrupar por">
      <?php foreach (['semana' => 'Por semana', 'dia' => 'Por dia'] as $value => $label): ?>
      <a href="<?= $e($link($periodQuery,$value)) ?>"<?= $grouping === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <?= $appView->render('components::report-period-form', ['reportPeriodForm' => [
      'action' => AppUrl::section('fluxo-de-caixa'), 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'),
      'hidden' => ['agrupamento' => $grouping],
  ]]) ?>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar o fluxo de caixa</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif (!$hasData): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrows-left-right') ?></span><h3>Nenhum lançamento neste período</h3><p>Troque o período ou registre recebimentos e pagamentos.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Fluxo de caixa">
    <table class="finance-table finance-entries cash-table">
      <thead><tr><th scope="col">Período</th><th scope="col" class="num">Entradas realizadas</th><th scope="col" class="num">Saídas realizadas</th><th scope="col" class="num">Entradas previstas</th><th scope="col" class="num">Saídas previstas</th><th scope="col" class="num">Saldo projetado</th></tr></thead>
      <tbody>
      <?php foreach ($buckets as $label => $row): ?>
      <tr><td><strong><?= $e($label) ?></strong></td><td class="num green"><?= $e(Finance::money($row['in_done'])) ?></td><td class="num red"><?= $e(Finance::money($row['out_done'])) ?></td><td class="num"><?= $e(Finance::money($row['in_open'])) ?></td><td class="num"><?= $e(Finance::money($row['out_open'])) ?></td><td class="num <?= $row['projected_balance'] < 0 ? 'red' : 'green' ?>"><strong><?= $e(Finance::money($row['projected_balance'])) ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num green"><?= $e(Finance::money($totals['in_done'])) ?></td><td class="num red"><?= $e(Finance::money($totals['out_done'])) ?></td><td class="num"><?= $e(Finance::money($totals['in_open'])) ?></td><td class="num"><?= $e(Finance::money($totals['out_open'])) ?></td><td class="num"><?= $e(Finance::money($projected)) ?></td></tr></tfoot>
    </table>
  </div>
  <details class="debt-details"><summary>Rastreabilidade dos movimentos (<?= count($traceRows) ?>)</summary><div class="finance-table-wrap" role="region" tabindex="0" aria-label="Movimentos do fluxo de caixa"><table class="finance-table finance-entries"><thead><tr><th>Data</th><th>Lançamento</th><th>Conta</th><th>Situação</th><th class="num">Entrada</th><th class="num">Saída</th></tr></thead><tbody><?php $sourceLabels=['direct'=>'Pagamento direto','settlement'=>'Baixa','card_payment'=>'Pagamento de fatura','card_refund'=>'Estorno de fatura','open'=>'Previsto em aberto'];foreach($traceRows as$row):?><tr><td><?= $e((new DateTimeImmutable($row['date']))->format('d/m/Y')) ?></td><td>#<?= (int)$row['entry_id'] ?> · <?= $e($row['description']) ?><small><?= $e($row['category']??'Sem categoria') ?></small></td><td><?= $e($row['account']) ?></td><td><?= $e($sourceLabels[$row['source']]??$row['source']) ?></td><?php if($row['kind']==='income'):?><td class="num green"><?= $e(Finance::money($row['amount_cents'])) ?></td><td></td><?php else:?><td></td><td class="num red"><?= $e(Finance::money($row['amount_cents'])) ?></td><?php endif;?></tr><?php endforeach;?></tbody></table></div></details>
  <?php endif; ?>
</section>
