<?php
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\ManagementDre;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$month = is_string($_GET['mes'] ?? null) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');
$criterion = ($_GET['criterio'] ?? '') === 'caixa' ? 'cash' : 'competence';
$startDate = new DateTimeImmutable($month . '-01');
$endDate = $startDate->modify('last day of this month');
$previousStart = $startDate->modify('-1 month');
$previousEnd = $previousStart->modify('last day of this month');
$dre = $previousDre = null;
try {
    $service = new ManagementDre((int) FinanceContext::currentId());
    $dre = $service->report($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $criterion);
    $previousDre = $service->report($previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'), $criterion);
} catch (Throwable $error) {
    error_log('DRE: ' . $error->getMessage());
}
$url = static fn (string $targetMonth, string $targetCriterion): string => AppUrl::section('dre', null, [
    'mes' => $targetMonth, 'criterio' => $targetCriterion === 'cash' ? 'caixa' : 'competencia',
]);
$exportUrl = '/minha-conta/financeiro/exportar?' . http_build_query([
    'relatorio' => 'dre', 'mes' => $month, 'criterio' => $criterion === 'cash' ? 'caixa' : 'competencia',
]);
$lines = [
    ['gross', 'Receita bruta', 1], ['deductions', '(-) Deduções', -1], ['net', '(=) Receita líquida', 1],
    ['costs', '(-) Custos', -1], ['gross_result', '(=) Resultado bruto', 1],
    ['operating', '(-) Despesas operacionais', -1], ['operating_result', '(=) Resultado operacional', 1],
    ['other_income', '(+) Outras receitas', 1], ['other_expense', '(-) Outras despesas', -1],
    ['result', '(=) Resultado gerencial', 1],
];
$values = static function (array $report): array {
    $groups = $report['groups'];
    $net = $groups['gross']['total'] - $groups['deductions']['total'];
    $gross = $net - $groups['costs']['total'];
    $operating = $gross - $groups['operating']['total'];
    return [
        'gross' => $groups['gross']['total'], 'deductions' => $groups['deductions']['total'], 'net' => $net,
        'costs' => $groups['costs']['total'], 'gross_result' => $gross, 'operating' => $groups['operating']['total'],
        'operating_result' => $operating, 'other_income' => $groups['other_income']['total'],
        'other_expense' => $groups['other_expense']['total'], 'result' => $report['result'],
    ];
};
$sourceLabels = ['direct' => 'pagamento direto', 'settlement' => 'baixa', 'card_payment' => 'pagamento de fatura', 'competence' => 'competência'];
?>
<section class="card finance-workspace finance-report" aria-labelledby="dre-title">
  <div class="finance-toolbar">
    <div><h2 id="dre-title">DRE gerencial</h2><p class="muted">Visão de gestão com critério explícito; não substitui demonstração contábil oficial.</p></div>
    <a class="finance-button" href="<?= $e($exportUrl) ?>" download>Exportar DRE</a>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês">
      <a href="<?= $e($url($previousStart->format('Y-m'), $criterion)) ?>" aria-label="Mês anterior">‹</a>
      <strong><?= $e($startDate->format('m/Y')) ?></strong>
      <a href="<?= $e($url($startDate->modify('+1 month')->format('Y-m'), $criterion)) ?>" aria-label="Próximo mês">›</a>
    </nav>
    <nav class="finance-status" aria-label="Critério do relatório">
      <a href="<?= $e($url($month, 'competence')) ?>"<?= $criterion === 'competence' ? ' aria-current="true"' : '' ?>>Competência</a>
      <a href="<?= $e($url($month, 'cash')) ?>"<?= $criterion === 'cash' ? ' aria-current="true"' : '' ?>>Caixa</a>
    </nav>
  </div>
  <?php if ($dre === null || $previousDre === null): ?>
  <div class="finance-empty"><h3>Não foi possível carregar a DRE</h3><p>Atualize a página e tente novamente.</p></div>
  <?php else: $groups = $dre['groups']; $current = $values($dre); $previous = $values($previousDre); ?>
  <p class="muted"><?= $criterion === 'cash'
      ? 'Caixa considera pagamentos e baixas na data efetiva, inclusive parciais. Compras no cartão entram quando a fatura é paga.'
      : 'Competência considera receitas e despesas no período em que foram reconhecidas, mesmo que ainda estejam em aberto.' ?></p>
  <div class="report-table-wrap" role="region" tabindex="0" aria-label="DRE gerencial comparativa">
    <table class="report-table">
      <thead><tr><th scope="col">Grupo</th><th scope="col" class="is-money"><?= $e($startDate->format('m/Y')) ?></th><th scope="col" class="is-money"><?= $e($previousStart->format('m/Y')) ?></th><th scope="col" class="is-money">Variação</th></tr></thead>
      <tbody>
      <?php foreach ($lines as [$key, $label, $sign]): $difference = $current[$key] - $previous[$key]; ?>
      <tr>
        <td><details><summary><?= $e($label) ?></summary>
          <?php if (isset($groups[$key]['rows']) && $groups[$key]['rows'] !== []): ?>
            <?php foreach ($groups[$key]['rows'] as $row): ?>
            <small>#<?= (int) $row['entry_id'] ?> · <?= $e((new DateTimeImmutable($row['occurred_at']))->format('d/m/Y')) ?> · <?= $e($row['description']) ?><?= $row['category'] ? ' · ' . $e($row['category']) : ' · Sem categoria' ?> · <?= $e($sourceLabels[$row['source']] ?? 'lançamento') ?> · <?= $e(Finance::money((int) $row['amount_cents'])) ?></small><br>
            <?php endforeach; ?>
          <?php else: ?><small><?= in_array($key, ['net', 'gross_result', 'operating_result', 'result'], true) ? 'Subtotal calculado pelas linhas anteriores.' : 'Nenhum lançamento classificado neste grupo.' ?></small><?php endif; ?>
        </details></td>
        <td class="is-money <?= $sign < 0 ? 'red' : 'green' ?>"><strong><?= $e(Finance::money($current[$key])) ?></strong></td>
        <td class="is-money"><?= $e(Finance::money($previous[$key])) ?></td>
        <td class="is-money <?= $difference * $sign < 0 ? 'red' : ($difference * $sign > 0 ? 'green' : '') ?>"><?= $e(Finance::money($difference)) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted">Classificação gerencial: categorias iniciadas por “Custo” ou “CMV” entram em Custos; “Dedução” ou “Imposto” em Deduções; “Outra receita/despesa” nos respectivos grupos. As demais despesas são operacionais.</p>
  <?php endif; ?>
</section>
