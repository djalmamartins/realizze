<?php
use Moves\Services\Debts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\OrganizationContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$period = Finance::period($_GET);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$loadFailed = false;
$annual = $period['view'] === 'anual';
$month = $annual ? date('Y-m') : $period['start']->format('Y-m');
$rows = [];
$projection = null;
$debtPlanned = 0;
try {
    $contextId = (int) FinanceContext::currentId();
    $finance = new Finance($contextId);
    if ($annual) {
        // Orçamento é mensal: o ano soma os doze meses e só pode ser editado mês a mês.
        for ($m = 1; $m <= 12; $m++) {
            foreach ($finance->budgets($period['start']->format('Y') . '-' . sprintf('%02d', $m)) as $row) {
                $id = $row['category_id'];
                if (isset($rows[$id])) {
                    foreach (['budget', 'realized', 'pending', 'spent'] as $key) {
                        $rows[$id][$key] += $row[$key];
                    }
                } else {
                    $rows[$id] = $row;
                }
            }
        }
        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => [$b['budget'], $a['name']] <=> [$a['budget'], $b['name']]);
    } else {
        $rows = $finance->budgets($month);
    }
    $projection = $finance->planProjection($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    // Sair das Dívidas é da PF: na empresa, as parcelas previstas não existem.
    if (OrganizationContext::current() === null) {
        $debtPlanned = (new Debts($contextId))->plannedPayments($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    }
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$withBudget = array_values(array_filter($rows, static fn (array $r): bool => $r['budget'] > 0));
$withoutBudget = array_values(array_filter($rows, static fn (array $r): bool => $r['budget'] === 0));
$planned = array_sum(array_column($withBudget, 'budget'));
$realized = array_sum(array_column($withBudget, 'realized'));
$pending = array_sum(array_column($withBudget, 'pending'));
$available = $planned - $realized - $pending;
$outsideBudget = array_sum(array_column($withoutBudget, 'spent'));
$hasFuture = $projection !== null && ($projection['income_expected'] > 0 || $projection['expense_expected'] > 0 || $debtPlanned > 0);
$hasData = $projection !== null && ($hasFuture || $projection['income_realized'] > 0 || $projection['expense_realized'] > 0);
$overCount = count(array_filter($withBudget, static fn (array $r): bool => Finance::budgetState($r['budget'], $r['spent']) === 'over'));
$nearCount = count(array_filter($withBudget, static fn (array $r): bool => Finance::budgetState($r['budget'], $r['spent']) === 'near'));
$stateText = ['ok' => 'Dentro do orçamento', 'near' => 'Perto do limite', 'over' => 'Acima do orçamento'];
$amountValue = static fn (int $cents): string => number_format($cents / 100, 2, ',', '.');
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="planning-title">
  <div class="finance-toolbar">
    <div><h2 id="planning-title">Planejamento de <?= $e($period['long']) ?></h2><p class="muted"><?= $annual ? 'Soma dos limites de cada mês. Para editar, abra o mês.' : 'Defina um limite por categoria e acompanhe o gasto do mês.' ?></p></div>
    <?php if (!$annual && !$loadFailed): ?>
    <div class="finance-toolbar-actions">
      <form method="post" action="/minha-conta/financeiro/orcamento-copiar"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="planejamento"><input type="hidden" name="mes" value="<?= $e($month) ?>"><button type="submit">Copiar do mês anterior</button></form>
      <button type="button" class="primary" data-finance-open="budget-dialog"<?= $withoutBudget === [] ? ' disabled title="' . ($rows === [] ? 'Crie uma categoria de despesa primeiro' : 'Todas as categorias já têm limite') . '"' : '' ?>>+ Definir orçamento</button>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar o planejamento</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($rows === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Sem categorias de despesa</h3><p>Crie categorias de despesa para definir orçamentos.</p></div>
  <?php else: ?>
  <div class="finance-goal-summary" aria-label="Resumo do orçamento">
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Planejado</span><strong><?= $e(Finance::money($planned)) ?></strong><small><?= count($withBudget) ?> categoria<?= count($withBudget) === 1 ? '' : 's' ?> com limite</small></div>
    <div class="finance-goal-stat finance-indicator <?= $realized > 0 ? 'is-realized' : 'is-neutral' ?>"><span>Gasto</span><strong><?= $e(Finance::money($realized)) ?></strong><small><?= $pending > 0 ? 'Mais ' . $e(Finance::money($pending)) . ' a pagar' : 'Nada a pagar nessas categorias' ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $available > 0 ? 'is-positive' : ($available < 0 ? 'is-negative' : 'is-neutral') ?>"><span><?= $available < 0 ? 'Excedido' : 'Ainda pode gastar' ?></span><strong><?= $e(Finance::money(abs($available))) ?></strong><small>Planejado − gasto − a pagar</small></div>
  </div>
  <?php if ($overCount > 0): ?><p class="debt-alert debt-alert-red budget-alert" role="status"><?= $overCount === 1 ? '1 categoria está' : $overCount . ' categorias estão' ?> acima do orçamento.</p>
  <?php elseif ($nearCount > 0): ?><p class="debt-alert debt-alert-amber budget-alert" role="status"><?= $nearCount === 1 ? '1 categoria está' : $nearCount . ' categorias estão' ?> perto do limite.</p><?php endif; ?>
  <?php if ($withBudget === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Nenhum orçamento em <?= $e($period['long']) ?></h3><p><?= $annual ? 'Abra um mês para definir os limites.' : 'Defina o limite das categorias ou copie os do mês anterior.' ?></p></div>
  <?php else: ?>
  <ul class="budget-list">
    <?php foreach ($withBudget as $row): $state = Finance::budgetState($row['budget'], $row['spent']); $left = $row['budget'] - $row['spent']; $share = $row['spent'] * 100 / $row['budget']; ?>
    <li class="budget-item is-<?= $e($state) ?>">
      <div class="budget-head">
        <strong><span class="finance-dot" style="background:<?= $e($row['color']) ?>"></span><?= $e($row['name']) ?></strong>
        <span class="budget-state"><?= $e($stateText[$state]) ?> · <?= $e(number_format($share, 0, ',', '.')) ?>%</span>
      </div>
      <div class="track track-live budget-track" role="img" aria-label="<?= $e(number_format($share, 0, ',', '.')) ?>% do limite"><span class="<?= $state === 'over' ? 'is-over' : '' ?>" data-width="<?= round(min(100, $row['realized'] * 100 / $row['budget']), 1) ?>"></span><span class="is-pending" data-width="<?= round(min(100 - min(100, $row['realized'] * 100 / $row['budget']), $row['pending'] * 100 / $row['budget']), 1) ?>"></span></div>
      <div class="budget-foot">
        <span class="muted">Planejado <?= $e(Finance::money($row['budget'])) ?> · Gasto <?= $e(Finance::money($row['realized'])) ?><?= $row['pending'] > 0 ? ' · A pagar ' . $e(Finance::money($row['pending'])) : '' ?></span>
        <span class="budget-left"><strong class="<?= $left < 0 ? 'red' : '' ?>"><?= $left < 0 ? 'Excedeu ' . $e(Finance::money(-$left)) : 'Disponível ' . $e(Finance::money($left)) ?></strong><?php if (!$annual): ?> <button type="button" class="budget-edit" data-finance-open="budget-edit-<?= (int) $row['category_id'] ?>" aria-label="Editar orçamento de <?= $e($row['name']) ?>">Editar</button><?php endif; ?></span>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($outsideBudget > 0): ?>
  <details class="debt-details budget-outside"><summary>Fora do orçamento: <?= $e(Finance::money($outsideBudget)) ?></summary>
    <ul class="debt-assumptions"><?php foreach ($withoutBudget as $row): if ($row['spent'] > 0): ?><li><?= $e($row['name']) ?>: <?= $e(Finance::money($row['spent'])) ?></li><?php endif; endforeach; ?></ul>
  </details>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php if (!$loadFailed): ?>
<section class="card finance-workspace" aria-labelledby="planning-result-title">
  <h2 id="planning-result-title">Resultado <?= $annual ? 'do ano' : 'do mês' ?></h2>
  <?php if (!$hasData): ?>
  <p class="muted">Ainda não há receitas, despesas ou parcelas neste período para calcular uma projeção.</p>
  <?php else: $projected = $projection['result_projected'] - $debtPlanned; ?>
  <div class="debt-table-wrap">
    <table class="debt-table budget-result">
      <caption class="visually-hidden">Valores realizados e previstos no período</caption>
      <thead><tr><th scope="col"></th><th scope="col">Realizado</th><th scope="col">Previsto</th><th scope="col">Total</th></tr></thead>
      <tbody>
        <tr><th scope="row">Receitas</th><td><?= $e(Finance::money($projection['income_realized'])) ?></td><td><?= $e(Finance::money($projection['income_expected'])) ?></td><td><?= $e(Finance::money($projection['income_realized'] + $projection['income_expected'])) ?></td></tr>
        <tr><th scope="row">Despesas</th><td><?= $e(Finance::money($projection['expense_realized'])) ?></td><td><?= $e(Finance::money($projection['expense_expected'])) ?></td><td><?= $e(Finance::money($projection['expense_realized'] + $projection['expense_expected'])) ?></td></tr>
        <?php if ($debtPlanned > 0): ?><tr><th scope="row">Parcelas de dívidas</th><td>Nas despesas</td><td><?= $e(Finance::money($debtPlanned)) ?></td><td><?= $e(Finance::money($debtPlanned)) ?></td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th scope="row">Resultado</th><td class="<?= $projection['result_realized'] < 0 ? 'red' : '' ?>"><?= $e(Finance::money($projection['result_realized'])) ?></td><td></td><td class="<?= $projected < 0 ? 'red' : '' ?>"><strong><?= $e(Finance::money($projected)) ?></strong></td></tr></tfoot>
    </table>
  </div>
  <p class="muted budget-note">Previsto inclui lançamentos em aberto e recorrências ativas<?= $debtPlanned > 0 ? ', além das parcelas de dívidas ainda sem pagamento no mês. Se você já lançou a parcela como despesa, vincule-a ao registrar o pagamento em Sair das dívidas para não contá-la duas vezes' : '' ?>. É uma estimativa, não uma garantia.</p>
  <?php endif; ?>
</section>
<?php if (!$annual && $rows !== []): ?>
<dialog class="finance-dialog" id="budget-dialog" aria-labelledby="budget-dialog-title">
  <form method="post" action="/minha-conta/financeiro/orcamento" data-finance-form>
    <header><h2 id="budget-dialog-title">Definir orçamento de <?= $e($period['long']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="planejamento"><input type="hidden" name="mes" value="<?= $e($month) ?>">
    <label>Categoria<select name="category_id" required><?php foreach ($withoutBudget as $row): ?><option value="<?= (int) $row['category_id'] ?>"><?= $e($row['name']) ?></option><?php endforeach; ?></select></label>
    <label>Limite do mês (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
<?php foreach ($withBudget as $row): $categoryId = (int) $row['category_id']; ?>
<dialog class="finance-dialog" id="budget-edit-<?= $categoryId ?>" aria-labelledby="budget-edit-title-<?= $categoryId ?>">
  <form method="post" action="/minha-conta/financeiro/orcamento" data-finance-form>
    <header><h2 id="budget-edit-title-<?= $categoryId ?>"><?= $e($row['name']) ?>: <?= $e($period['long']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="planejamento"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
    <label>Limite do mês (R$)<input name="amount" inputmode="decimal" value="<?= $e($amountValue($row['budget'])) ?>" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
  <form method="post" action="/minha-conta/financeiro/orcamento-excluir" class="budget-delete" data-finance-confirm="Excluir o orçamento de <?= $e($row['name']) ?> em <?= $e($period['long']) ?>? Os lançamentos não mudam.">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="planejamento"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
    <button type="submit" class="finance-link-danger">Excluir este orçamento</button>
  </form>
</dialog>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
