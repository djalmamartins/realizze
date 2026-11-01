<?php
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$period = Finance::period($_GET);
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('categorias', null, $query);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$loadFailed = false;
$categoryIcon = require dirname(__DIR__) . '/components/category-icon.php';
$rows = [];
try {
    $rows = (new Finance((int) FinanceContext::currentId()))->categoriesWithTotals($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$groups = ['income' => 'Receitas', 'expense' => 'Despesas'];
$month = $period['start']->format('Y-m');
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="categories-title">
  <div class="finance-toolbar">
    <div><h2 id="categories-title">Categorias</h2><p class="muted">Organize receitas e despesas. Totais de <?= $e($period['long']) ?>.</p></div>
    <button type="button" class="primary" data-finance-open="category-dialog">+ Nova categoria</button>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Período"><a href="<?= $e($link($period['previous'])) ?>" aria-label="Período anterior">‹</a><strong><?= $e($period['long']) ?></strong><a href="<?= $e($link($period['next'])) ?>" aria-label="Próximo período">›</a></nav>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as categorias</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($rows === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('list-filter') ?></span><h3>Nenhuma categoria criada</h3><p>Crie a primeira para organizar seus lançamentos.</p></div>
  <?php else: ?>
  <div class="finance-columns">
    <?php foreach ($groups as $kind => $title): $list = array_filter($rows, static fn (array $r): bool => $r['kind'] === $kind); ?>
    <div>
      <h3><?= $e($title) ?></h3>
      <ul class="finance-account-list">
        <?php foreach ($list as $row): ?>
        <li class="is-compact"><span class="category-icon" style="--category-color:<?= $e($row['color']) ?>"><?= $categoryIcon((string) $row['name']) ?></span><span><strong><?= $e($row['name']) ?></strong><small><?= (int) $row['entries'] ?> lançamento<?= (int) $row['entries'] === 1 ? '' : 's' ?></small></span>
          <span class="finance-row-end"><strong><?= $e(Finance::money((int) $row['total'])) ?></strong>
          <form method="post" action="/minha-conta/financeiro/categoria-excluir" data-finance-confirm="Excluir a categoria “<?= $e($row['name']) ?>”? Os lançamentos ficam sem categoria."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="categorias"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit" class="finance-link-danger" aria-label="Excluir <?= $e($row['name']) ?>">Excluir</button></form></span>
        </li>
        <?php endforeach; ?>
        <?php if ($list === []): ?><li class="is-compact"><span></span><span class="muted">Nenhuma categoria.</span></li><?php endif; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="category-dialog" aria-labelledby="category-dialog-title">
  <form method="post" action="/minha-conta/financeiro/categoria" data-finance-form>
    <header><h2 id="category-dialog-title">Nova categoria</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="categorias"><input type="hidden" name="mes" value="<?= $e($month) ?>">
    <label>Nome<input name="name" maxlength="80" required></label>
    <div class="finance-dialog-row">
      <label>Tipo<select name="category_kind"><option value="expense">Despesa</option><option value="income">Receita</option></select></label>
      <label>Cor<input name="color" type="color" value="#078b4c"></label>
    </div>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
