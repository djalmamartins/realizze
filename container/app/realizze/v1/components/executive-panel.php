<?php
/** Indicadores executivos da empresa, calculados dos lançamentos reais. Só aparece no contexto empresarial. */
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$period = Finance::period($_GET);
$yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
try {
    $contextId = (int) FinanceContext::currentId();
    $finance = new Finance($contextId);
    $receivables = new Receivables($contextId);
    $income = $finance->summary('income', $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    $expense = $finance->summary('expense', $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    $overdueIncome = $receivables->listing(['start' => '2000-01-01', 'end' => $yesterday, 'status' => 'overdue', 'per_page' => 5]);
    $overdueIncomeTotal = $receivables->summary('2000-01-01', $yesterday)['overdue'];
    $overdueExpense = array_sum(array_map(
        static fn (array $row): int => (int) $row['amount_cents'],
        array_filter($finance->entries('expense', '2000-01-01', $yesterday, 'all'), static fn (array $row): bool => empty($row['paid_at']) && ($row['status'] ?? '') !== 'cancelled')
    ));
} catch (Throwable $exception) {
    error_log('Realizze executive panel: ' . $exception->getMessage());
    return;
}
?>
<section class="card executive-panel" aria-label="Indicadores da empresa">
  <div class="finance-goal-summary cash-summary">
    <div class="finance-goal-stat"><span>Receitas de <?= $e($period['short']) ?></span><strong class="green"><?= $e(Finance::money($income['paid'])) ?></strong><small>Já recebidas</small></div>
    <div class="finance-goal-stat"><span>Despesas de <?= $e($period['short']) ?></span><strong class="red"><?= $e(Finance::money($expense['paid'])) ?></strong><small>Já pagas</small></div>
    <a class="finance-goal-stat" href="<?= $e(AppUrl::section('receber', null, ['situacao' => 'overdue', 'inicio' => '2000-01-01', 'fim' => $yesterday])) ?>"><span>A receber vencido</span><strong class="<?= $overdueIncomeTotal > 0 ? 'red' : '' ?>"><?= $e(Finance::money($overdueIncomeTotal)) ?></strong><small><?= (int) $overdueIncome['total'] ?> cobrança<?= (int) $overdueIncome['total'] === 1 ? '' : 's' ?> em atraso</small></a>
    <a class="finance-goal-stat" href="<?= $e(AppUrl::section('fluxo-de-caixa')) ?>"><span>A pagar vencido</span><strong class="<?= $overdueExpense > 0 ? 'red' : '' ?>"><?= $e(Finance::money($overdueExpense)) ?></strong><small>Ver fluxo de caixa</small></a>
  </div>
  <?php if ($overdueIncome['rows'] !== []): ?>
  <div class="executive-overdue">
    <h2 class="section-title">Cobranças vencidas</h2>
    <ul class="overview-list">
      <?php foreach ($overdueIncome['rows'] as $row): ?>
      <li><span><strong><?= $e($row['counterparty_name'] ?: 'Origem não informada') ?></strong><small><?= $e($row['description']) ?> · venceu em <?= $e((new DateTimeImmutable($row['due_date']))->format('d/m/Y')) ?></small></span><strong class="red"><?= $e(Finance::money($row['balance_cents'])) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</section>
