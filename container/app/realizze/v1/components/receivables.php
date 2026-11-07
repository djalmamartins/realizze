<?php
/** Módulo 01: listagem, indicadores e operações reais de Contas a Receber. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use Moves\Services\Contacts;
use Moves\Services\RecurringFinance;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$date = static function (mixed $value, string $fallback): string {
    if (!is_string($value)) {
        return $fallback;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed !== false && $parsed->format('Y-m-d') === $value ? $value : $fallback;
};
$today = date('Y-m-d');
$start = $date($_GET['inicio'] ?? null, date('Y-m-01'));
$end = $date($_GET['fim'] ?? null, date('Y-m-t'));
$status = in_array($_GET['situacao'] ?? null, ['pending', 'partially_received', 'received', 'cancelled', 'overdue'], true)
    ? (string) $_GET['situacao'] : '';
$search = is_string($_GET['busca'] ?? null) ? mb_substr(trim($_GET['busca']), 0, 120) : '';
$counterparty = is_string($_GET['cliente'] ?? null) ? mb_substr(trim($_GET['cliente']), 0, 160) : '';
$category = filter_var($_GET['categoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$contactFilter = filter_var($_GET['contato'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = max(1, (int) ($_GET['page'] ?? 1));
$csrf = (string) ($csrf ?? '');
$accounts = $categories = $customers = [];
$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 15];
$summary = ['to_receive' => 0, 'received' => 0, 'overdue' => 0, 'forecast' => 0];
$loadFailed = false;
$receivables = null;
$tabCounts = $entryTags = $entryDetails = $editData = [];
$entryExtras = false;
try {
    $contextId = FinanceContext::currentId();
    if ($contextId === null) {
        throw new RuntimeException('Contexto financeiro indisponível.');
    }
    $finance = new Finance($contextId);
    // Gera de forma idempotente as receitas recorrentes automáticas do mês.
    (new RecurringFinance($contextId))->generateDue(new DateTimeImmutable('last day of this month'), 100);
    $receivables = new Receivables($contextId);
    $customers = (new Contacts($contextId))->selectableCustomers();
    $accounts = $finance->accounts();
    $categories = $finance->categories('income');
    $result = $receivables->listing([
        'start' => $start, 'end' => $end, 'status' => $status, 'search' => $search,
        'counterparty' => $counterparty, 'contact_id' => $contactFilter, 'category_id' => $category, 'page' => $page,
    ]);
    $summary = $receivables->summary($start, $end);
    $entryExtras = $finance->supportsEntryExtras();
    $entryTags = $finance->tags();
    $entryDetails = $finance->entryDetails(array_map(static fn (array $row): int => (int) $row['id'], $result['rows']));
    $editData = $finance->entryEditData(array_map(static fn (array $row): int => (int) $row['id'], $result['rows']));
    $base =['start' => $start, 'end' => $end, 'search' => $search, 'counterparty' => $counterparty, 'contact_id' => $contactFilter, 'category_id' => $category, 'per_page' => 5];
    foreach (['pending', 'overdue', 'partially_received', 'received', 'cancelled'] as $tabStatus) {
        $tabCounts[$tabStatus] = $receivables->listing($base + ['status' => $tabStatus])['total'];
    }
} catch (Throwable $exception) {
    error_log('Realizze receivables view: ' . $exception->getMessage());
    $loadFailed = true;
}
$flash = $_SESSION['receivables_flash'] ?? null;
unset($_SESSION['receivables_flash']);
$methods = [
    'pix' => 'PIX', 'transfer' => 'Transferência', 'cash' => 'Dinheiro',
    'boleto' => 'Boleto', 'card' => 'Cartão', 'other' => 'Outra',
];
$statuses = [
    'pending' => 'Pendente', 'partially_received' => 'Parcialmente recebido',
    'received' => 'Recebido', 'cancelled' => 'Cancelado', 'overdue' => 'Vencido',
];
$state = [
    'inicio' => $start, 'fim' => $end, 'situacao' => $status, 'busca' => $search,
    'categoria' => $category === false ? '' : (string) $category, 'cliente' => $counterparty,
    'contato' => $contactFilter === false ? '' : (string) $contactFilter,
];
$query = static fn (array $changes = []): string => \Moves\Services\AppUrl::section('receber', null, array_filter(
    array_merge($state, $changes),
    static fn (mixed $value): bool => $value !== '' && $value !== null
));
$competence = date('Y-m', strtotime($start));
$renderState = static function () use ($state, $e): void {
    foreach ($state as $name => $value) {
        if ($value !== '') {
            echo '<input type="hidden" name="' . $e($name) . '" value="' . $e($value) . '">';
        }
    }
};
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace receivables-workspace" aria-labelledby="receivables-title">
  <?= $appView->render('components::report-heading', [
      'reportHeading' => ['id' => 'receivables-title', 'title' => 'Contas a receber', 'description' => 'Cadastre, acompanhe e baixe recebimentos do contexto financeiro atual.'],
      'reportActions' => [[
          'type' => 'button', 'label' => '+ Nova receita', 'class' => 'primary', 'entry_open' => 'income',
          'disabled' => $accounts === [], 'title' => $accounts === [] ? 'Cadastre uma conta primeiro' : null,
      ]],
  ]) ?>

  <div class="finance-goal-summary is-four" aria-label="Indicadores de contas a receber">
    <div class="finance-goal-stat finance-indicator <?= $summary['to_receive'] > 0 ? 'is-pending' : 'is-neutral' ?>"><span>Total a receber</span><strong><?= $e(Finance::money($summary['to_receive'])) ?></strong><small>Saldo em aberto no período</small></div>
    <div class="finance-goal-stat finance-indicator <?= $summary['received'] > 0 ? 'is-realized' : 'is-neutral' ?>"><span>Total recebido</span><strong><?= $e(Finance::money($summary['received'])) ?></strong><small>Baixas dos lançamentos do período</small></div>
    <div class="finance-goal-stat finance-indicator <?= $summary['overdue'] > 0 ? 'is-overdue' : 'is-neutral' ?>"><span>Total vencido</span><strong><?= $e(Finance::money($summary['overdue'])) ?></strong><small>Saldo vencido e não recebido</small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Previsto no período</span><strong><?= $e(Finance::money($summary['forecast'])) ?></strong><small>Valor original não cancelado</small></div>
  </div>

  <div class="finance-filter-bar">
  <nav class="finance-status receivables-tabs" aria-label="Situação">
    <a href="<?= $e($query(['situacao' => '', 'page' => ''])) ?>"<?= $status === '' ? ' aria-current="true"' : '' ?>>Todos (<?= array_sum($tabCounts) ?>)</a>
    <?php foreach (['pending' => 'Pendentes', 'overdue' => 'Vencidos', 'partially_received' => 'Parciais', 'received' => 'Recebidos', 'cancelled' => 'Cancelados'] as $value => $label): ?>
    <a href="<?= $e($query(['situacao' => $value, 'page' => ''])) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?> (<?= (int) ($tabCounts[$value] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </nav>
  <?php $filterPanelId = 'receivable-filters'; require __DIR__ . '/filter-button.php'; ?>
  </div>

  <?php
  $filterQuery = array_filter(['inicio' => $start, 'fim' => $end, 'situacao' => $status, 'busca' => $search, 'categoria' => $category ?: '', 'cliente' => $counterparty, 'contato' => $contactFilter ?: ''], static fn (mixed $value): bool => $value !== '');
  $without = static fn (string ...$keys): string => \Moves\Services\AppUrl::section('receber', null, array_diff_key($filterQuery, array_flip($keys)));
  $findName = static function (array $items, mixed $id, string ...$fields): string {
      foreach ($items as $item) {
          if ((int) $item['id'] === (int) $id) {
              foreach ($fields as $field) {
                  if (!empty($item[$field])) return (string) $item[$field];
              }
          }
      }
      return '';
  };
  $filterChips = [];
  if ($start !== date('Y-m-01') || $end !== date('Y-m-t')) $filterChips['Vencimento: ' . (new DateTimeImmutable($start))->format('d/m/Y') . ' a ' . (new DateTimeImmutable($end))->format('d/m/Y')] = $without('inicio', 'fim');
  if ($search !== '') $filterChips['Busca: ' . $search] = $without('busca');
  if ($category) $filterChips['Categoria: ' . $findName($categories, $category, 'name')] = $without('categoria');
  if ($counterparty !== '') $filterChips['Cliente: ' . $counterparty] = $without('cliente');
  if ($contactFilter) $filterChips['Cliente: ' . $findName($customers, $contactFilter, 'trade_name', 'legal_name')] = $without('contato');
  $filterClearUrl = \Moves\Services\AppUrl::section('receber', null, $status === '' ? [] : ['situacao' => $status]);
  require __DIR__ . '/filter-bar.php';
  ?>
<dialog class="finance-dialog filter-dialog" id="receivable-filters" aria-labelledby="receivable-filters-title">
  <form method="get" action="<?= $e(\Moves\Services\AppUrl::section('receber')) ?>" data-swap-get>
    <header><h2 id="receivable-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?php $periodStart = $start; $periodEnd = $end; require __DIR__ . '/filter-period.php'; ?>
    <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Descrição ou documento"></label>
    <?php if ($status !== ''): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
    <label>Categoria<select name="categoria"><option value="">Todas</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $category !== false && (int) $category === (int) $item['id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label>
    <label>Cliente ou origem<input name="cliente" value="<?= $e($counterparty) ?>" maxlength="160"></label>
    <label>Cliente cadastrado<select name="contato"><option value="">Todos</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= $contactFilter !== false && (int) $contactFilter === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
    <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
  </form>
</dialog>

  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os recebimentos</h3><p>Verifique a migração do banco e tente novamente.</p></div>
  <?php elseif ($result['rows'] === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrow-down-left') ?></span><h3>Nenhum recebimento encontrado</h3><p>Cadastre o primeiro recebimento ou ajuste os filtros.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Contas a receber">
    <table class="finance-table finance-entries receivables-table">
      <caption class="finance-table-caption"><?= (int) $result['total'] ?> recebimento<?= $result['total'] === 1 ? '' : 's' ?> encontrado<?= $result['total'] === 1 ? '' : 's' ?></caption>
      <thead><tr><th scope="col">Descrição / origem</th><th scope="col">Vencimento</th><th scope="col" class="num">Original</th><th scope="col" class="num">Saldo</th><th scope="col">Forma</th><th scope="col">Situação</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $entry):
          $history = $receivables?->history((int) $entry['id']) ?? ['settlements' => [], 'audit' => []];
          $editable = $entry['display_status'] !== 'cancelled';
          $canSettle = in_array($entry['display_status'], ['pending', 'partially_received', 'overdue'], true);
      ?>
      <tr id="lancamento-<?= (int) $entry['id'] ?>" tabindex="-1">
        <td><strong><?= $e($entry['description']) ?></strong><small class="receivables-origin"><?= $e($entry['counterparty_name'] ?: 'Origem não informada') ?><?= $entry['document_number'] ? ' · Doc. ' . $e($entry['document_number']) : '' ?></small><?php $detail = $entryDetails[(int) $entry['id']] ?? null; $metaHideNotes = true; require __DIR__ . '/entry-meta.php'; ?></td>
        <td class="nowrap<?= $entry['display_status'] === 'overdue' ? ' red' : '' ?>"><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m/Y')) ?><?php if ($entry['display_status'] === 'overdue'): $late = (int) (new DateTimeImmutable($entry['due_date']))->diff(new DateTimeImmutable('today'))->days; ?><small class="receivables-origin">há <?= $late ?> dia<?= $late === 1 ? '' : 's' ?></small><?php endif; ?></td>
        <td class="num"><?= $e(Finance::money($entry['amount_cents'])) ?></td>
        <td class="num"><strong><?= $e(Finance::money($entry['balance_cents'])) ?></strong></td>
        <td><?= $e($methods[$entry['payment_method']] ?? '—') ?></td>
        <td><span class="finance-badge is-<?= $e($entry['display_status']) ?>"><?= $e($statuses[$entry['display_status']]) ?></span></td>
        <td class="finance-actions">
          <?php if ($canSettle): ?><button type="button" data-finance-open="receivable-settle-<?= (int) $entry['id'] ?>">Baixar</button><?php endif; ?>
          <?php if ($editable && isset($editData[(int) $entry['id']])): ?><button type="button" data-entry-edit="<?= $e(json_encode($editData[(int) $entry['id']], JSON_UNESCAPED_UNICODE)) ?>">Editar</button><?php endif; ?>
          <button type="button" data-finance-open="receivable-history-<?= (int) $entry['id'] ?>">Detalhes</button>
        </td>
      </tr>
      <tr class="receivables-dialog-row"><td colspan="7">
      <?php if ($canSettle): ?>
      <dialog class="finance-dialog" id="receivable-settle-<?= (int) $entry['id'] ?>" aria-labelledby="receivable-settle-title-<?= (int) $entry['id'] ?>"><form method="post" action="/minha-conta/financeiro/recebimento-baixa" data-finance-form>
        <header><h2 id="receivable-settle-title-<?= (int) $entry['id'] ?>">Registrar recebimento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>"><?php $renderState(); ?>
        <p class="muted">Saldo atual: <strong><?= $e(Finance::money($entry['balance_cents'])) ?></strong></p>
        <div class="finance-dialog-row"><label>Valor recebido<input name="amount" inputmode="decimal" value="<?= $e(number_format($entry['balance_cents'] / 100, 2, ',', '.')) ?>" required></label><label>Data do recebimento<input type="date" name="received_at" value="<?= $e($today) ?>" required></label></div>
        <div class="finance-dialog-row"><label>Conta de destino<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) $account['id'] === (int) $entry['account_id'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label><label>Forma<select name="payment_method" required><?php foreach ($methods as $value => $label): ?><option value="<?= $e($value) ?>"<?= $entry['payment_method'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label></div>
        <label>Observação da baixa<textarea name="notes" maxlength="500" rows="3"></textarea></label>
        <footer><button type="button" data-finance-close>Voltar</button><button type="submit" class="primary">Confirmar baixa</button></footer>
      </form></dialog>
      <?php endif; ?>
      <?php if ($editable && $entry['received_cents'] === 0): ?>
      <dialog class="finance-dialog" id="receivable-cancel-<?= (int) $entry['id'] ?>" aria-labelledby="receivable-cancel-title-<?= (int) $entry['id'] ?>"><form method="post" action="/minha-conta/financeiro/recebimento-cancelar" data-finance-form>
        <header><h2 id="receivable-cancel-title-<?= (int) $entry['id'] ?>">Cancelar recebimento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><?php $renderState(); ?>
        <p>O registro será preservado com a justificativa e não entrará nos totais.</p><label>Justificativa<textarea name="reason" minlength="5" maxlength="500" rows="4" required></textarea></label>
        <footer><button type="button" data-finance-close>Voltar</button><button type="submit" class="is-danger">Confirmar cancelamento</button></footer>
      </form></dialog>
      <?php endif; ?>
      <dialog class="finance-dialog" id="receivable-history-<?= (int) $entry['id'] ?>" aria-labelledby="receivable-history-title-<?= (int) $entry['id'] ?>"><div class="receivables-history">
        <header><h2 id="receivable-history-title-<?= (int) $entry['id'] ?>"><?= $e($entry['description']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <p class="contact-tags"><span class="finance-badge is-<?= $e($entry['display_status']) ?>"><?= $e($statuses[$entry['display_status']]) ?></span></p>
        <dl class="contact-details">
          <div><dt>Cliente ou origem</dt><dd><?php if ($entry['contact_id'] !== null && \Moves\Services\OrganizationContext::current() !== null): ?><a href="<?= $e(\Moves\Services\AppUrl::section('clientes') . '/' . (int) $entry['contact_id']) ?>"><?= $e($entry['counterparty_name']) ?></a><?php else: ?><?= $e($entry['counterparty_name'] ?: 'Não informado') ?><?php endif; ?></dd></div>
          <div><dt>Documento</dt><dd><?= $e($entry['document_number'] ?: 'Não informado') ?></dd></div>
          <div><dt>Vencimento</dt><dd><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m/Y')) ?></dd></div>
          <div><dt>Conta</dt><dd><?= $e($entry['account']) ?></dd></div>
          <div><dt>Categoria</dt><dd><?= $e($entry['category'] ?: 'Sem categoria') ?></dd></div>
          <div><dt>Recebido</dt><dd><?= $e(Finance::money($entry['received_cents'])) ?></dd></div>
          <div><dt>Valor</dt><dd><?= $e(Finance::money($entry['amount_cents'])) ?></dd></div>
          <div><dt>Saldo pendente</dt><dd><?= $e(Finance::money($entry['balance_cents'])) ?></dd></div>
          <?php if ($entry['notes']): ?><div class="is-wide"><dt>Observações</dt><dd><?= $e($entry['notes']) ?></dd></div><?php endif; ?>
          <?php if ($entry['cancellation_reason']): ?><div class="is-wide"><dt>Motivo do cancelamento</dt><dd><?= $e($entry['cancellation_reason']) ?></dd></div><?php endif; ?>
        </dl>
        <h3>Pagamentos recebidos</h3>
        <?php if ($history['settlements'] === []): ?><p class="muted">Nenhuma baixa registrada.</p><?php else: ?><ol><?php foreach ($history['settlements'] as $item): ?><li><strong><?= $e(Finance::money((int) $item['amount_cents'])) ?></strong> em <?= $e((new DateTimeImmutable($item['received_at']))->format('d/m/Y')) ?> · <?= $e($item['account']) ?> · <?= $e($methods[$item['payment_method']] ?? $item['payment_method']) ?><?php if ($editable): ?> <form class="inline-form" method="post" action="/minha-conta/financeiro/baixa-estornar" data-finance-confirm="Estornar este recebimento de <?= $e(Finance::money((int) $item['amount_cents'])) ?>? O saldo em aberto volta a aumentar."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="income"><input type="hidden" name="back" value="receber"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="settlement_id" value="<?= (int) $item['id'] ?>"><button type="submit" class="link-button">Estornar</button></form><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?>
        <h3>Histórico</h3>
        <ol><?php foreach ($history['audit'] as $event): ?><li><?= $e((json_decode((string) $event['metadata_json'], true)['event'] ?? '') === 'settlement_reversed' ? 'Baixa estornada' : (['created' => 'Criado', 'updated' => 'Editado', 'settled' => 'Baixa registrada', 'cancelled' => 'Cancelado'][$event['action']] ?? $event['action'])) ?> em <?= $e((new DateTimeImmutable($event['created_at']))->format('d/m/Y H:i')) ?></li><?php endforeach; ?></ol>
        <footer><button type="button" data-finance-close>Fechar</button><?php if ($editable && $entry['received_cents'] === 0): ?><button type="button" class="is-danger" data-finance-close data-finance-open-after="receivable-cancel-<?= (int) $entry['id'] ?>">Cancelar recebimento</button><?php endif; ?><?php if ($editable): ?><button type="button" class="primary" data-entry-edit="<?= $e(json_encode($editData[(int) $entry['id']] ?? [], JSON_UNESCAPED_UNICODE)) ?>">Editar</button><?php endif; ?></footer>
      </div></dialog>
      </td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($result['pages'] > 1): ?><nav class="receivables-pagination" aria-label="Páginas de recebimentos"><?php for ($number = 1; $number <= $result['pages']; $number++): ?><a href="<?= $e($query(['page' => $number])) ?>"<?= $number === $result['page'] ? ' aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
</section>

<?php $form = ['csrf' => $csrf, 'month' => $competence, 'date' => $today, 'back' => 'receber', 'state' => $state]; require __DIR__ . '/entry-form.php'; ?>
