<?php
/** Extrato do mês e transferências entre contas do contexto financeiro atual. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$rawMonth = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '';
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth) ? $rawMonth : date('Y-m');
$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('last day of this month');
$kind = in_array($_GET['tipo'] ?? '', ['income', 'expense', 'transfer'], true) ? $_GET['tipo'] : 'all';
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$monthLabel = $monthNames[(int) $start->format('n')] . ' de ' . $start->format('Y');
$validDate = static fn (mixed $value): bool => is_string($value) && ($parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) !== false && $parsed->format('Y-m-d') === $value;
$customPeriod = $validDate($_GET['inicio'] ?? null) && $validDate($_GET['fim'] ?? null) && $_GET['inicio'] <= $_GET['fim'];
$rangeStart = $customPeriod ? $_GET['inicio'] : $start->format('Y-m-d');
$rangeEnd = $customPeriod ? $_GET['fim'] : $end->format('Y-m-d');
if ($customPeriod) {
    $monthLabel = (new DateTimeImmutable($rangeStart))->format('d/m/Y') . ' a ' . (new DateTimeImmutable($rangeEnd))->format('d/m/Y');
}
$rows = $all = $accounts = [];
$search = $accountFilter = '';
$loadFailed = false;
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $accounts = $finance->accounts();
    $all = $finance->movements($rangeStart, $rangeEnd);
    $search = is_string($_GET['busca'] ?? null) ? mb_substr(trim($_GET['busca']), 0, 120) : '';
    $accountFilter = is_string($_GET['conta'] ?? null) ? $_GET['conta'] : '';
    $rows = array_values(array_filter($all, static function (array $r) use ($kind, $search, $accountFilter): bool {
        return ($kind === 'all' || $r['kind'] === $kind)
            && ($accountFilter === '' || $r['account'] === $accountFilter || $r['target_account'] === $accountFilter)
            && ($search === '' || mb_stripos($r['description'], $search) !== false || mb_stripos((string) ($r['category'] ?? ''), $search) !== false);
    }));
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$total = static fn (string $type): int => array_sum(array_map(static fn (array $r): int => $r['kind'] === $type ? (int) $r['amount_cents'] : 0, $all));
$link = static fn (string $target, string $type = 'all', array $drop = []): string => \Moves\Services\AppUrl::section('movimentacoes', null, array_diff_key(array_filter(['mes' => $target, 'tipo' => $type === 'all' ? '' : $type, 'inicio' => $customPeriod ? $rangeStart : '', 'fim' => $customPeriod ? $rangeEnd : '', 'busca' => $search, 'conta' => $accountFilter], static fn (string $v): bool => $v !== ''), array_flip($drop)));
$filterChips = [];
if ($customPeriod) $filterChips['Período: ' . $monthLabel] = $link($month, $kind, ['inicio', 'fim']);
if ($search !== '') $filterChips['Busca: ' . $search] = $link($month, $kind, ['busca']);
if ($accountFilter !== '') $filterChips['Conta: ' . $accountFilter] = $link($month, $kind, ['conta']);
$filterClearUrl = $link($month, $kind, ['inicio', 'fim', 'busca', 'conta']);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="movements-title">
  <div class="finance-toolbar">
    <div><h2 id="movements-title">Movimentações</h2><p class="muted">Tudo o que entrou, saiu ou foi transferido no mês.</p></div>
    <button type="button" class="primary" data-finance-open="transfer-dialog"<?= count($accounts) < 2 ? ' disabled title="Cadastre ao menos duas contas"' : '' ?>>⇄ Transferir entre contas</button>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo do mês">
    <div class="finance-goal-stat"><span>Entradas</span><strong class="green"><?= $e(Finance::money($total('income'))) ?></strong><small><?= $e($monthLabel) ?></small></div>
    <div class="finance-goal-stat"><span>Saídas</span><strong class="red"><?= $e(Finance::money($total('expense'))) ?></strong><small><?= $e($monthLabel) ?></small></div>
    <div class="finance-goal-stat"><span>Resultado</span><strong><?= $e(Finance::money($total('income') - $total('expense'))) ?></strong><small>Transferências não entram na conta</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês">
      <a href="<?= $e($link($start->modify('-1 month')->format('Y-m'), $kind)) ?>" aria-label="Mês anterior">‹</a>
      <strong><?= $e($monthLabel) ?></strong>
      <a href="<?= $e($link($start->modify('+1 month')->format('Y-m'), $kind)) ?>" aria-label="Próximo mês">›</a>
    </nav>
    <nav class="finance-status" aria-label="Tipo">
      <?php foreach (['all' => 'Todas', 'income' => 'Entradas', 'expense' => 'Saídas', 'transfer' => 'Transferências'] as $value => $label): ?>
      <a href="<?= $e($link($month, $value)) ?>"<?= $kind === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php $filterPanelId = 'movement-filters'; require dirname(__DIR__) . '/components/filter-button.php'; ?>
  </div>
  <?php require dirname(__DIR__) . '/components/filter-bar.php'; ?>
<dialog class="finance-dialog filter-dialog" id="movement-filters" aria-labelledby="movement-filters-title">
  <form method="get" action="<?= $e(\Moves\Services\AppUrl::section('movimentacoes')) ?>" data-swap-get>
    <header><h2 id="movement-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="mes" value="<?= $e($month) ?>"><?php if ($kind !== 'all'): ?><input type="hidden" name="tipo" value="<?= $e($kind) ?>"><?php endif; ?>
    <?php $periodStart = $rangeStart; $periodEnd = $rangeEnd; require dirname(__DIR__) . '/components/filter-period.php'; ?>
    <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Descrição ou categoria"></label>
    <label>Conta<select name="conta"><option value="">Todas</option><?php foreach ($accounts as $account): ?><option value="<?= $e($account['name']) ?>"<?= $accountFilter === $account['name'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
    <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
  </form>
</dialog>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as movimentações</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($rows === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrows-left-right') ?></span><h3>Nenhuma movimentação neste mês</h3><p>Troque o mês ou o tipo, ou registre uma transferência.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Movimentações">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Data</th><th scope="col">Descrição</th><th scope="col">Categoria</th><th scope="col">Conta</th><th scope="col" class="num">Valor</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): $sign = ['income' => '+ ', 'expense' => '− ', 'transfer' => '']; $tone = ['income' => 'green', 'expense' => 'red', 'transfer' => ''][$row['kind']]; ?>
      <tr>
        <td><?= $e((new DateTimeImmutable($row['paid_at']))->format('d/m/Y')) ?></td>
        <td><strong><?= $e($row['description']) ?></strong></td>
        <td><?= $row['kind'] === 'transfer' ? 'Transferência' : $e($row['category'] ?? '—') ?></td>
        <td><?= $e($row['account']) ?><?= $row['target_account'] ? ' → ' . $e($row['target_account']) : '' ?></td>
        <td class="num <?= $tone ?>"><strong><?= $sign[$row['kind']] ?><?= $e(Finance::money((int) $row['amount_cents'])) ?></strong></td>
        <td class="finance-actions">
          <?php if ($row['kind'] === 'transfer'): ?>
          <form method="post" action="/minha-conta/financeiro/excluir" data-finance-confirm="Excluir esta transferência?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="movimentacoes"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit" class="is-danger">Excluir</button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="transfer-dialog" aria-labelledby="transfer-dialog-title">
  <form method="post" action="/minha-conta/financeiro/transferencia" data-finance-form>
    <header><h2 id="transfer-dialog-title">Transferir entre contas</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="movimentacoes"><input type="hidden" name="mes" value="<?= $e($month) ?>">
    <div class="finance-dialog-row">
      <label>De<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
      <label>Para<select name="target_account_id" required><?php foreach ($accounts as $index => $account): ?><option value="<?= (int) $account['id'] ?>"<?= $index === 1 ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="finance-dialog-row">
      <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
      <label>Data<input name="date" type="date" value="<?= $e(date('Y-m-d')) ?>" required></label>
    </div>
    <label>Descrição<input name="description" maxlength="160" placeholder="Opcional"></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Transferir</button></footer>
  </form>
</dialog>
