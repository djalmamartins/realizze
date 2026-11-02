<?php
/** Fluxo de caixa do contexto atual, calculado dos lançamentos reais. Previsto e realizado nunca se somam. */
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rawMonth = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '';
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth) ? $rawMonth : date('Y-m');
$grouping = in_array($_GET['visao'] ?? '', ['dia', 'semana'], true) ? $_GET['visao'] : 'semana';
$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('last day of this month');
$today = new DateTimeImmutable('today');
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$monthLabel = $monthNames[(int) $start->format('n')] . ' de ' . $start->format('Y');
$link = static fn (string $target, string $view): string => AppUrl::section('fluxo-de-caixa', null, ['mes' => $target, 'visao' => $view]);
$buckets = [];
$totals = ['in_done' => 0, 'out_done' => 0, 'in_open' => 0, 'out_open' => 0];
$overdue = 0;
$balance = 0;
$failed = false;
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $balance = $finance->availableBalance();
    $bucketOf = static function (DateTimeImmutable $date) use ($grouping): string {
        if ($grouping === 'dia') return $date->format('d/m');
        $week = (int) ceil((int) $date->format('j') / 7);
        return 'Semana ' . $week;
    };
    if ($grouping === 'semana') {
        for ($week = 1; $week <= (int) ceil((int) $end->format('j') / 7); $week++) {
            $buckets['Semana ' . $week] = ['in_done' => 0, 'out_done' => 0, 'in_open' => 0, 'out_open' => 0];
        }
    }
    $add = static function (string $key, string $column, int $cents) use (&$buckets, &$totals): void {
        $buckets[$key] ??= ['in_done' => 0, 'out_done' => 0, 'in_open' => 0, 'out_open' => 0];
        $buckets[$key][$column] += $cents;
        $totals[$column] += $cents;
    };
    foreach ($finance->movements($start->format('Y-m-d'), $end->format('Y-m-d')) as $row) {
        if ($row['kind'] === 'transfer') continue;
        $add($bucketOf(new DateTimeImmutable($row['paid_at'])), $row['kind'] === 'income' ? 'in_done' : 'out_done', (int) $row['amount_cents']);
    }
    // Receitas vêm de Receivables, que conhece cancelamentos; Finance::entries não informa a situação.
    $open = array_map(static fn (array $row): array => $row + ['column' => 'out_open'], array_filter($finance->entries('expense', $start->format('Y-m-d'), $end->format('Y-m-d'), 'open')));
    $receivables = new \Moves\Services\Receivables((int) FinanceContext::currentId());
    $page = 1;
    do {
        $listing = $receivables->listing(['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'page' => $page, 'per_page' => 50]);
        foreach ($listing['rows'] as $row) {
            if (!in_array($row['display_status'], ['cancelled', 'received'], true)) $open[] = $row + ['column' => 'in_open'];
        }
    } while ($page++ < $listing['pages']);
    foreach ($open as $row) {
        $due = new DateTimeImmutable($row['due_date']);
        $add($bucketOf($due), $row['column'], (int) $row['amount_cents']);
        if ($due < $today) $overdue += (int) $row['amount_cents'];
    }
    if ($grouping === 'dia') uksort($buckets, static fn (string $a, string $b): int => strcmp(substr($a, 3) . $a, substr($b, 3) . $b));
} catch (Throwable $exception) {
    error_log('Realizze cash flow: ' . $exception->getMessage());
    $failed = true;
}
$hasData = array_sum($totals) > 0;
$projected = $balance + $totals['in_open'] - $totals['out_open'];
?>
<section class="card finance-workspace" aria-labelledby="cash-title">
  <div class="finance-toolbar">
    <div><h2 id="cash-title">Fluxo de caixa</h2><p class="muted">Realizado é o que já foi pago ou recebido. Previsto é o que continua em aberto no mês.</p></div>
  </div>
  <div class="finance-goal-summary cash-summary" aria-label="Resumo do mês">
    <div class="finance-goal-stat finance-indicator <?= $balance > 0 ? 'is-positive' : ($balance < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Saldo atual</span><strong><?= $e(Finance::money($balance)) ?></strong><small>Contas e carteiras, sem cartões</small></div>
    <?php $realized = $totals['in_done'] - $totals['out_done']; ?><div class="finance-goal-stat finance-indicator <?= $realized > 0 ? 'is-positive' : ($realized < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Realizado no mês</span><strong><?= $e(Finance::money($realized)) ?></strong><small>Entradas menos saídas</small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Previsto em aberto</span><strong><?= $e(Finance::money($totals['in_open'] - $totals['out_open'])) ?></strong><small><?= $overdue > 0 ? $e(Finance::money($overdue)) . ' já vencidos' : 'A receber menos a pagar' ?></small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Saldo projetado</span><strong><?= $e(Finance::money($projected)) ?></strong><small>Se tudo em aberto se confirmar</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês">
      <a href="<?= $e($link($start->modify('-1 month')->format('Y-m'), $grouping)) ?>" aria-label="Mês anterior">‹</a>
      <strong><?= $e($monthLabel) ?></strong>
      <a href="<?= $e($link($start->modify('+1 month')->format('Y-m'), $grouping)) ?>" aria-label="Próximo mês">›</a>
    </nav>
    <nav class="finance-status" aria-label="Agrupar por">
      <?php foreach (['semana' => 'Por semana', 'dia' => 'Por dia'] as $value => $label): ?>
      <a href="<?= $e($link($month, $value)) ?>"<?= $grouping === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar o fluxo de caixa</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif (!$hasData): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrows-left-right') ?></span><h3>Nenhum lançamento neste mês</h3><p>Troque o mês ou registre recebimentos e pagamentos.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Fluxo de caixa">
    <table class="finance-table finance-entries cash-table">
      <thead><tr><th scope="col">Período</th><th scope="col" class="num">Entradas realizadas</th><th scope="col" class="num">Saídas realizadas</th><th scope="col" class="num">Entradas previstas</th><th scope="col" class="num">Saídas previstas</th></tr></thead>
      <tbody>
      <?php foreach ($buckets as $label => $row): ?>
      <tr><td><strong><?= $e($label) ?></strong></td><td class="num green"><?= $e(Finance::money($row['in_done'])) ?></td><td class="num red"><?= $e(Finance::money($row['out_done'])) ?></td><td class="num"><?= $e(Finance::money($row['in_open'])) ?></td><td class="num"><?= $e(Finance::money($row['out_open'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num green"><?= $e(Finance::money($totals['in_done'])) ?></td><td class="num red"><?= $e(Finance::money($totals['out_done'])) ?></td><td class="num"><?= $e(Finance::money($totals['in_open'])) ?></td><td class="num"><?= $e(Finance::money($totals['out_open'])) ?></td></tr></tfoot>
    </table>
  </div>
  <?php endif; ?>
</section>
