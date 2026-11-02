<?php
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$period = Finance::period($_GET);
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('planejamento', null, $query);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$loadFailed = false;
$annual = $period['view'] === 'anual';
$month = $annual ? date('Y-m') : $period['start']->format('Y-m');
$rows = [];
try {
    $finance = new Finance((int) FinanceContext::currentId());
    if ($annual) {
        // Orçamento é mensal: o ano soma os doze meses e só pode ser editado mês a mês.
        for ($m = 1; $m <= 12; $m++) {
            foreach ($finance->budgets($period['start']->format('Y') . '-' . sprintf('%02d', $m)) as $row) {
                $rows[$row['category_id']] = isset($rows[$row['category_id']])
                    ? ['budget' => $rows[$row['category_id']]['budget'] + $row['budget'], 'spent' => $rows[$row['category_id']]['spent'] + $row['spent']] + $row
                    : $row;
            }
        }
        $rows = array_values($rows);
    } else {
        $rows = $finance->budgets($month);
    }
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$planned = array_sum(array_column($rows, 'budget'));
$spent = array_sum(array_map(static fn (array $r): int => $r['budget'] > 0 ? $r['spent'] : 0, $rows));
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="planning-title">
  <div class="finance-toolbar">
    <div><h2 id="planning-title">Planejamento</h2><p class="muted">Defina um limite por categoria e acompanhe o gasto do mês.</p></div>
    <?php if (!$annual): ?><button type="button" class="primary" data-finance-open="budget-dialog"<?= $rows === [] ? ' disabled title="Crie uma categoria de despesa primeiro"' : '' ?>>+ Definir orçamento</button><?php endif; ?>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo do orçamento">
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Orçamento previsto</span><strong><?= $e(Finance::money($planned)) ?></strong><small><?= $e($period['long']) ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $spent > 0 ? 'is-realized' : 'is-neutral' ?>"><span>Gasto realizado</span><strong><?= $e(Finance::money($spent)) ?></strong><small>Nas categorias com orçamento</small></div>
    <?php $budgetAvailable = $planned - $spent; ?><div class="finance-goal-stat finance-indicator <?= $budgetAvailable > 0 ? 'is-positive' : ($budgetAvailable < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Disponível</span><strong><?= $e(Finance::money($budgetAvailable)) ?></strong><small>Orçamento − gasto</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês"><a href="<?= $e($link($period['previous'])) ?>" aria-label="Mês anterior">‹</a><strong><?= $e($period['long']) ?></strong><a href="<?= $e($link($period['next'])) ?>" aria-label="Próximo mês">›</a></nav>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar o planejamento</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($rows === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Sem categorias de despesa</h3><p>Crie categorias para definir orçamentos.</p></div>
  <?php else: ?>
  <ul class="budget-list">
    <?php foreach ($rows as $row): $share = $row['budget'] > 0 ? min(100, $row['spent'] / $row['budget'] * 100) : 0; $over = $row['budget'] > 0 && $row['spent'] > $row['budget']; ?>
    <li>
      <div class="budget-head"><strong><span class="finance-dot" style="background:<?= $e($row['color']) ?>"></span><?= $e($row['name']) ?></strong><span class="<?= $over ? 'red' : 'muted' ?>"><?= $e(Finance::money($row['spent'])) ?> de <?= $row['budget'] > 0 ? $e(Finance::money($row['budget'])) : 'sem orçamento' ?></span></div>
      <div class="track track-live"><span class="<?= $over ? 'is-over' : '' ?>" data-width="<?= round($share, 1) ?>"></span></div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="budget-dialog" aria-labelledby="budget-dialog-title">
  <form method="post" action="/minha-conta/financeiro/orcamento" data-finance-form>
    <header><h2 id="budget-dialog-title">Definir orçamento de <?= $e($period['long']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="planejamento"><input type="hidden" name="mes" value="<?= $e($month) ?>">
    <label>Categoria<select name="category_id" required><?php foreach ($rows as $row): ?><option value="<?= (int) $row['category_id'] ?>"><?= $e($row['name']) ?></option><?php endforeach; ?></select></label>
    <label>Limite do mês (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
