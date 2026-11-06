<?php
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\ManagementDre;
use Moves\Services\OrganizationContext;
use Moves\Services\SessionAuth;
use Moves\Services\Authorization;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$period = Finance::period($_GET);
$criterion = ($_GET['criterio'] ?? '') === 'caixa' ? 'cash' : 'competence';
$startDate = $period['start'];
$endDate = $period['end'];
$previousStart = $period['compareStart'];
$previousEnd = $period['compareEnd'];
$dre = $previousDre = null;
$classifications = [];
$classificationReady = false;
try {
    $service = new ManagementDre((int) FinanceContext::currentId());
    $dre = $service->report($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $criterion);
    $previousDre = $service->report($previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'), $criterion);
    $classificationReady = $service->classificationReady();
    $classifications = $service->categoryClassifications();
} catch (Throwable $error) {
    error_log('DRE: ' . $error->getMessage());
}
$periodQuery = ['inicio'=>$startDate->format('Y-m-d'),'fim'=>$endDate->format('Y-m-d')];
$url = static fn (array $targetPeriod, string $targetCriterion): string => AppUrl::section('dre', null, $targetPeriod + [
    'criterio' => $targetCriterion === 'cash' ? 'caixa' : 'competencia',
]);
$exportUrl = '/minha-conta/financeiro/exportar?' . http_build_query([
    'relatorio' => 'dre', 'inicio'=>$startDate->format('Y-m-d'),'fim'=>$endDate->format('Y-m-d'), 'criterio' => $criterion === 'cash' ? 'caixa' : 'competencia',
]);
$flash=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);
$organization=OrganizationContext::current();$sessionUser=SessionAuth::current();
$canClassify=$organization!==null&&$sessionUser!==null&&(new Authorization())->can((int)$sessionUser['id'],(int)$organization['id'],'finance.manage');
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
<?php if(is_array($flash)):?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif;?>
<section class="card finance-workspace finance-report" aria-labelledby="dre-title">
  <div class="finance-toolbar">
    <div><h2 id="dre-title">DRE gerencial</h2><p class="muted">Visão de gestão com critério explícito; não substitui demonstração contábil oficial.</p></div>
    <a class="finance-button" href="<?= $e($exportUrl) ?>" download>Exportar DRE</a>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Período">
      <a href="<?= $e($url($period['previous'], $criterion)) ?>" aria-label="Período anterior">‹</a>
      <strong><?= $e($period['long']) ?></strong>
      <a href="<?= $e($url($period['next'], $criterion)) ?>" aria-label="Próximo período">›</a>
    </nav>
    <nav class="finance-status" aria-label="Critério do relatório">
      <a href="<?= $e($url($periodQuery, 'competence')) ?>"<?= $criterion === 'competence' ? ' aria-current="true"' : '' ?>>Competência</a>
      <a href="<?= $e($url($periodQuery, 'cash')) ?>"<?= $criterion === 'cash' ? ' aria-current="true"' : '' ?>>Caixa</a>
    </nav>
  </div>
  <form class="finance-filter-bar" method="get" action="<?= $e(AppUrl::section('dre')) ?>" data-swap-get>
    <label>De<input type="date" name="inicio" value="<?= $e($startDate->format('Y-m-d')) ?>" required></label>
    <label>Até<input type="date" name="fim" value="<?= $e($endDate->format('Y-m-d')) ?>" required></label>
    <input type="hidden" name="criterio" value="<?= $criterion==='cash'?'caixa':'competencia' ?>"><button type="submit">Aplicar período</button>
  </form>
  <?php if ($dre === null || $previousDre === null): ?>
  <div class="finance-empty"><h3>Não foi possível carregar a DRE</h3><p>Atualize a página e tente novamente.</p></div>
  <?php else: $groups = $dre['groups']; $current = $values($dre); $previous = $values($previousDre); ?>
  <p class="muted"><?= $criterion === 'cash'
      ? 'Caixa considera pagamentos e baixas na data efetiva, inclusive parciais. Compras no cartão entram quando a fatura é paga.'
      : 'Competência considera receitas e despesas no período em que foram reconhecidas, mesmo que ainda estejam em aberto.' ?></p>
  <div class="report-table-wrap" role="region" tabindex="0" aria-label="DRE gerencial comparativa">
    <table class="report-table">
      <thead><tr><th scope="col">Grupo</th><th scope="col" class="is-money"><?= $e($period['short']) ?></th><th scope="col" class="is-money"><?= $e($previousStart->format('d/m/Y').' a '.$previousEnd->format('d/m/Y')) ?></th><th scope="col" class="is-money">Variação</th></tr></thead>
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
  <p class="muted">Classificação gerencial configurada por categoria. Sem escolha explícita, permanece o fallback por prefixo (“Custo”, “CMV”, “Dedução”, “Imposto” e “Outra receita/despesa”).</p>
  <?php if($classificationReady):?><details class="debt-details"><summary>Classificação das categorias</summary>
    <div class="finance-account-list"><?php foreach($classifications as$row):$options=$row['kind']==='income'?['gross'=>'Receita bruta','other_income'=>'Outras receitas']:['deductions'=>'Deduções','costs'=>'Custos','operating'=>'Despesas operacionais','other_expense'=>'Outras despesas'];?>
      <form method="post" action="/minha-conta/financeiro/categoria-dre" data-finance-form class="is-compact"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="dre"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="inicio" value="<?= $e($startDate->format('Y-m-d')) ?>"><input type="hidden" name="fim" value="<?= $e($endDate->format('Y-m-d')) ?>"><input type="hidden" name="criterio" value="<?= $criterion==='cash'?'caixa':'competencia' ?>"><strong><?= $e($row['name']) ?></strong><label>Grupo<select name="management_dre_group"<?= $canClassify?'':' disabled' ?>><option value="">Automático (<?= $e($row['effective_label']) ?>)</option><?php foreach($options as$key=>$label):?><option value="<?= $e($key) ?>"<?= $row['configured_group']===$key?' selected':'' ?>><?= $e($label) ?></option><?php endforeach;?></select></label><?php if($canClassify):?><button type="submit">Salvar</button><?php endif;?></form>
    <?php endforeach;?></div></details>
  <?php else:?><p class="muted">A classificação explícita ficará disponível após a migration 020; a DRE continua funcionando com o fallback compatível.</p><?php endif;?>
  <?php endif; ?>
</section>
