<?php
/** Lista, resumo e modal de lançamentos a receber ou a pagar do contexto atual. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\RecurringFinance;
use Moves\Services\SubscriptionBrands;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isIncome = $kind === 'income';
$labels = $isIncome
    ? ['title' => 'Contas a receber', 'lead' => 'Receitas, vencimentos e recebimentos do mês.', 'new' => 'Novo recebimento', 'expected' => 'A receber', 'done' => 'Recebido', 'settle' => 'Receber', 'status' => 'Recebido', 'empty' => 'Nenhum recebimento neste mês']
    : ['title' => 'Contas a pagar', 'lead' => 'Despesas, vencimentos e pagamentos do mês.', 'new' => 'Nova despesa', 'expected' => 'A pagar', 'done' => 'Pago', 'settle' => 'Pagar', 'status' => 'Pago', 'empty' => 'Nenhuma despesa neste mês'];
$page = $isIncome ? 'receber' : 'pagar';
$rawMonth = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '';
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth) ? $rawMonth : date('Y-m');
$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('last day of this month');
$status = in_array($_GET['situacao'] ?? '', ['open', 'paid', 'overdue'], true) ? $_GET['situacao'] : 'all';
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$monthLabel = $monthNames[(int) $start->format('n')] . ' de ' . $start->format('Y');
$csrf = (string) ($csrf ?? '');
$search = is_string($_GET['busca'] ?? null) ? mb_substr(trim($_GET['busca']), 0, 120) : '';
$categoryFilter = (int) filter_var($_GET['categoria'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]);
$accountFilter = (int) filter_var($_GET['conta'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]);
$tagFilter = is_string($_GET['tag'] ?? null) ? mb_substr(trim($_GET['tag']), 0, 40) : '';
$validDate = static fn (mixed $value): bool => is_string($value) && ($parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) !== false && $parsed->format('Y-m-d') === $value;
$customPeriod = $validDate($_GET['inicio'] ?? null) && $validDate($_GET['fim'] ?? null) && $_GET['inicio'] <= $_GET['fim'];
$rangeStart = $customPeriod ? $_GET['inicio'] : $start->format('Y-m-d');
$rangeEnd = $customPeriod ? $_GET['fim'] : $end->format('Y-m-d');
if ($customPeriod) {
    $monthLabel = (new DateTimeImmutable($rangeStart))->format('d/m/Y') . ' a ' . (new DateTimeImmutable($rangeEnd))->format('d/m/Y');
}
$loadFailed = false;
$accounts = $categories = $entries = $tags = $details = [];
$extras = false;
$summary = ['expected' => 0, 'paid' => 0, 'overdue' => 0, 'count' => 0];
try {
    $finance = new Finance((int) FinanceContext::currentId());
    // Garante vencimentos do dia mesmo quando o cron local ainda não foi configurado.
    (new RecurringFinance((int) FinanceContext::currentId()))->generateDue(new DateTimeImmutable('today'), 100);
    $accounts = $finance->accounts();
    $categories = $finance->categories($kind);
    $entries = $finance->entries($kind, $rangeStart, $rangeEnd, $status, ['search' => $search, 'category_id' => $categoryFilter, 'account_id' => $accountFilter, 'tag' => $tagFilter]);
    $summary = $finance->summary($kind, $rangeStart, $rangeEnd);
    $extras = $finance->supportsEntryExtras();
    $tags = $finance->tags();
    $details = $finance->entryDetails(array_map(static fn (array $entry): int => (int) $entry['id'], $entries));
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
// Previstas somem da tela quando o usuário filtra por situação ou busca: não são lançamentos.
$planned = [];
if (!$loadFailed && in_array($status, ['all', 'open'], true) && $search === '' && $categoryFilter === 0 && $accountFilter === 0 && $tagFilter === '') {
    try {
        $planned = (new RecurringFinance((int) FinanceContext::currentId()))->planned($kind, $rangeStart, $rangeEnd);
    } catch (Throwable $exception) {
        error_log('Realizze finance: ' . $exception->getMessage());
    }
}
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$today = date('Y-m-d');
$activeFilters = array_filter(['inicio' => $customPeriod ? $rangeStart : '', 'fim' => $customPeriod ? $rangeEnd : '', 'busca' => $search, 'categoria' => $categoryFilter, 'conta' => $accountFilter, 'tag' => $tagFilter]);
$link = static fn (string $target, string $situation = 'all', array $filters = []): string => \Moves\Services\AppUrl::section($page, null, ['mes' => $target] + ($situation === 'all' ? [] : ['situacao' => $situation]) + $filters);
$nameOf = static function (array $items, int $id): string {
    foreach ($items as $item) {
        if ((int) $item['id'] === $id) return (string) $item['name'];
    }
    return '';
};
$chipLabels = ['busca' => 'Busca: ' . $search, 'categoria' => 'Categoria: ' . $nameOf($categories, $categoryFilter), 'conta' => 'Conta: ' . $nameOf($accounts, $accountFilter), 'tag' => 'Tag: ' . $tagFilter];
$filterChips = [];
if ($customPeriod) {
    $filterChips['Vencimento: ' . $monthLabel] = $link($month, $status, array_diff_key($activeFilters, ['inicio' => 1, 'fim' => 1]));
}
foreach (array_diff_key($activeFilters, ['inicio' => 1, 'fim' => 1]) as $filterKey => $unused) {
    $filterChips[$chipLabels[$filterKey]] = $link($month, $status, array_diff_key($activeFilters, [$filterKey => 1]));
}
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="entries-title">
  <div class="finance-toolbar">
    <div><h2 id="entries-title"><?= $e($labels['title']) ?></h2><p class="muted"><?= $e($labels['lead']) ?></p></div>
    <button type="button" class="primary" data-finance-open="entry-dialog"<?= $accounts === [] ? ' disabled title="Cadastre uma conta primeiro"' : '' ?>>+ <?= $e($labels['new']) ?></button>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo do mês">
    <div class="finance-goal-stat"><span><?= $e($labels['expected']) ?></span><strong class="<?= $isIncome ? 'green' : 'red' ?>"><?= $e(Finance::money($summary['expected'])) ?></strong><small><?= $e($monthLabel) ?></small></div>
    <div class="finance-goal-stat"><span><?= $e($labels['done']) ?></span><strong><?= $e(Finance::money($summary['paid'])) ?></strong><small><?= (int) $summary['count'] ?> lançamento<?= $summary['count'] === 1 ? '' : 's' ?> no mês</small></div>
    <div class="finance-goal-stat"><span>Em atraso</span><strong class="<?= $summary['overdue'] > 0 ? 'red' : '' ?>"><?= $e(Finance::money($summary['overdue'])) ?></strong><small>Vencidos e não baixados</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês">
      <a href="<?= $e($link($start->modify('-1 month')->format('Y-m'), $status, $activeFilters)) ?>" aria-label="Mês anterior">‹</a>
      <strong><?= $e($monthLabel) ?></strong>
      <a href="<?= $e($link($start->modify('+1 month')->format('Y-m'), $status, $activeFilters)) ?>" aria-label="Próximo mês">›</a>
    </nav>
    <nav class="finance-status" aria-label="Situação">
      <?php foreach (['all' => 'Todos', 'open' => 'Em aberto', 'paid' => $labels['status'], 'overdue' => 'Em atraso'] as $value => $label): ?>
      <a href="<?= $e($link($month, $value, $activeFilters)) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php $filterPanelId = 'entry-filters'; require __DIR__ . '/filter-button.php'; ?>
  </div>
  <?php $filterClearUrl = $link($month, $status); require __DIR__ . '/filter-bar.php'; ?>
<dialog class="finance-dialog filter-dialog" id="entry-filters" aria-labelledby="entry-filters-title">
  <form method="get" action="<?= $e(\Moves\Services\AppUrl::section($page)) ?>" data-swap-get>
    <header><h2 id="entry-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?php $periodStart = $rangeStart; $periodEnd = $rangeEnd; require __DIR__ . '/filter-period.php'; ?>
    <input type="hidden" name="mes" value="<?= $e($month) ?>"><?php if ($status !== 'all'): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
    <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Descrição"></label>
    <label>Categoria<select name="categoria"><option value="">Todas</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $categoryFilter === (int) $item['id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label>
    <label>Conta ou cartão<select name="conta"><option value="">Todas</option><?php foreach ($accounts as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $accountFilter === (int) $item['id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label>
    <?php if ($tags !== []): ?><label>Tag<select name="tag"><option value="">Todas</option><?php foreach ($tags as $item): ?><option value="<?= $e($item) ?>"<?= $tagFilter === $item ? ' selected' : '' ?>><?= $e($item) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
  </form>
</dialog>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os lançamentos</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($entries === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon($isIncome ? 'arrow-down-left' : 'arrow-up-right') ?></span><h3><?= $e($labels['empty']) ?></h3><p>Use o botão acima para registrar o primeiro, ou troque o mês ou a situação.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="<?= $e($labels['title']) ?>">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Descrição</th><th scope="col">Vencimento</th><th scope="col">Categoria</th><th scope="col">Conta</th><th scope="col">Situação</th><th scope="col" class="num">Valor</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry): $overdue = $entry['paid_at'] === null && $entry['due_date'] < $today; $detail = $details[(int) $entry['id']] ?? null; ?>
      <tr id="lancamento-<?= (int) $entry['id'] ?>">
        <td><strong><?= $e($entry['description']) ?></strong>
          <?php require __DIR__ . '/entry-meta.php'; ?>
        </td>
        <td><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m/Y')) ?></td>
        <td><?php if ($entry['category']): ?><span class="finance-dot" style="background:<?= $e($entry['category_color']) ?>"></span><?= $e($entry['category']) ?><?php else: ?>—<?php endif; ?></td>
        <td><?= $e($entry['account']) ?></td>
        <td><span class="finance-badge <?= $entry['paid_at'] ? 'is-paid' : ($overdue ? 'is-overdue' : 'is-open') ?>"><?= $entry['paid_at'] ? $e($labels['status']) : ($overdue ? 'Em atraso' : 'Em aberto') ?></span></td>
        <td class="num <?= $isIncome ? 'green' : 'red' ?>"><strong><?= $isIncome ? '+ ' : '− ' ?><?= $e(Finance::money((int) $entry['amount_cents'])) ?></strong></td>
        <td class="finance-actions">
          <?php if (!$entry['paid_at']): ?>
          <form method="post" action="/minha-conta/financeiro/baixa"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="<?= $e($kind) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><button type="submit"><?= $e($labels['settle']) ?></button></form>
          <?php endif; ?>
          <form method="post" action="/minha-conta/financeiro/excluir" data-finance-confirm="Excluir “<?= $e($entry['description']) ?>”?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="<?= $e($kind) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><button type="submit" class="is-danger">Excluir</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php if ($planned !== []): ?>
  <div class="finance-planned" aria-labelledby="planned-title">
    <div class="finance-planned-head">
      <div><h3 id="planned-title">Recorrências previstas</h3><p class="muted">Ainda não viraram lançamento e não entram nos totais acima.</p></div>
      <a href="<?= $e(\Moves\Services\AppUrl::section('recorrencias')) ?>">Ver recorrências</a>
    </div>
    <ul class="overview-list">
      <?php foreach ($planned as $item): $brandLogo = SubscriptionBrands::logoUrl((string) $item['name'], (string) ($item['provider'] ?? '')); $brandVisual = SubscriptionBrands::visual((string) $item['name'], (string) ($item['provider'] ?? '')); ?>
      <li>
        <span class="finance-planned-item">
          <span class="recurring-brand" style="--recurring-bg:<?= $e($brandVisual[0]) ?>;--recurring-fg:<?= $e($brandVisual[1]) ?>" aria-hidden="true"><?php if ($brandLogo !== null): ?><img src="<?= $e($brandLogo) ?>" width="28" height="28" loading="lazy" decoding="async" alt=""><?php else: ?><?= $e($brandVisual[2]) ?><?php endif; ?></span>
          <span><strong><?= $e($item['name']) ?></strong><small><?= $e((new DateTimeImmutable($item['due_date']))->format('d/m/Y')) ?> · <?= $e($item['account_name']) ?></small></span>
        </span>
        <strong><?= $e(Finance::money((int) $item['amount_cents'])) ?></strong>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</section>
<?php $form = ['id' => 'entry-dialog', 'kind' => $kind, 'title' => $labels['new'], 'accounts' => $accounts, 'categories' => $categories, 'csrf' => $csrf, 'month' => $month, 'date' => substr($today, 0, 7) === $month ? $today : $month . '-01', 'back' => '', 'extras' => $extras, 'tags' => $tags]; require __DIR__ . '/entry-form.php'; ?>
