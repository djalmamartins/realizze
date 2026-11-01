<?php
/** Módulo 01: listagem, indicadores e operações reais de Contas a Receber. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use Moves\Services\Contacts;

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
try {
    $contextId = FinanceContext::currentId();
    if ($contextId === null) {
        throw new RuntimeException('Contexto financeiro indisponível.');
    }
    $finance = new Finance($contextId);
    $receivables = new Receivables($contextId);
    $customers = (new Contacts($contextId))->selectableCustomers();
    $accounts = $finance->accounts();
    $categories = $finance->categories('income');
    $result = $receivables->listing([
        'start' => $start, 'end' => $end, 'status' => $status, 'search' => $search,
        'counterparty' => $counterparty, 'contact_id' => $contactFilter, 'category_id' => $category, 'page' => $page,
    ]);
    $summary = $receivables->summary($start, $end);
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
  <div class="finance-toolbar">
    <div><h2 id="receivables-title">Contas a receber</h2><p class="muted">Cadastre, acompanhe e baixe recebimentos do contexto financeiro atual.</p></div>
    <button type="button" class="primary" data-finance-open="receivable-create"<?= $accounts === [] || $categories === [] ? ' disabled title="Cadastre uma conta e uma categoria de receita primeiro"' : '' ?>>+ Novo recebimento</button>
  </div>

  <div class="finance-goal-summary is-four" aria-label="Indicadores de contas a receber">
    <div class="finance-goal-stat"><span>Total a receber</span><strong class="green"><?= $e(Finance::money($summary['to_receive'])) ?></strong><small>Saldo em aberto no período</small></div>
    <div class="finance-goal-stat"><span>Total recebido</span><strong><?= $e(Finance::money($summary['received'])) ?></strong><small>Baixas dos lançamentos do período</small></div>
    <div class="finance-goal-stat"><span>Total vencido</span><strong class="<?= $summary['overdue'] > 0 ? 'red' : '' ?>"><?= $e(Finance::money($summary['overdue'])) ?></strong><small>Saldo vencido e não recebido</small></div>
    <div class="finance-goal-stat"><span>Previsto no período</span><strong><?= $e(Finance::money($summary['forecast'])) ?></strong><small>Valor original não cancelado</small></div>
  </div>

  <form class="receivables-filters" method="get" action="/minha-conta">
    <input type="hidden" name="pagina" value="receber">
    <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Descrição ou documento"></label>
    <label>Início<input type="date" name="inicio" value="<?= $e($start) ?>"></label>
    <label>Fim<input type="date" name="fim" value="<?= $e($end) ?>"></label>
    <label>Situação<select name="situacao"><option value="">Todas</option><?php foreach ($statuses as $value => $label): ?><option value="<?= $e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
    <label>Categoria<select name="categoria"><option value="">Todas</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $category !== false && (int) $category === (int) $item['id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label>
    <label>Cliente ou origem<input name="cliente" value="<?= $e($counterparty) ?>" maxlength="160"></label>
    <label>Cliente cadastrado<select name="contato"><option value="">Todos</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= $contactFilter !== false && (int) $contactFilter === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
    <div class="receivables-filter-actions"><button type="submit" class="primary">Filtrar</button><a href="<?= $e(\Moves\Services\AppUrl::section('receber')) ?>">Limpar</a></div>
  </form>

  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os recebimentos</h3><p>Verifique a migração do banco e tente novamente.</p></div>
  <?php elseif ($result['rows'] === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrow-down-left') ?></span><h3>Nenhum recebimento encontrado</h3><p>Cadastre o primeiro recebimento ou ajuste os filtros.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Contas a receber">
    <table class="finance-table finance-entries receivables-table">
      <caption class="finance-table-caption"><?= (int) $result['total'] ?> recebimento<?= $result['total'] === 1 ? '' : 's' ?> encontrado<?= $result['total'] === 1 ? '' : 's' ?></caption>
      <thead><tr><th scope="col">Descrição / origem</th><th scope="col">Vencimento</th><th scope="col">Categoria</th><th scope="col" class="num">Original</th><th scope="col" class="num">Recebido</th><th scope="col" class="num">Saldo</th><th scope="col">Situação</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $entry):
          $history = $receivables?->history((int) $entry['id']) ?? ['settlements' => [], 'audit' => []];
          $editable = $entry['display_status'] !== 'cancelled';
          $canSettle = in_array($entry['display_status'], ['pending', 'partially_received', 'overdue'], true);
      ?>
      <tr>
        <td><strong><?= $e($entry['description']) ?></strong><small class="receivables-origin"><?= $e($entry['counterparty_name'] ?: 'Origem não informada') ?><?= $entry['document_number'] ? ' · Doc. ' . $e($entry['document_number']) : '' ?></small></td>
        <td><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m/Y')) ?></td>
        <td><span class="finance-dot" style="background:<?= $e($entry['category_color']) ?>"></span><?= $e($entry['category']) ?></td>
        <td class="num"><?= $e(Finance::money($entry['amount_cents'])) ?></td>
        <td class="num green"><?= $e(Finance::money($entry['received_cents'])) ?></td>
        <td class="num"><strong><?= $e(Finance::money($entry['balance_cents'])) ?></strong></td>
        <td><span class="finance-badge is-<?= $e($entry['display_status']) ?>"><?= $e($statuses[$entry['display_status']]) ?></span></td>
        <td class="finance-actions">
          <?php if ($canSettle): ?><button type="button" data-finance-open="receivable-settle-<?= (int) $entry['id'] ?>">Baixar</button><?php endif; ?>
          <?php if ($editable): ?><button type="button" data-finance-open="receivable-edit-<?= (int) $entry['id'] ?>">Editar</button><?php endif; ?>
          <?php if ($editable && $entry['received_cents'] === 0): ?><button type="button" class="is-danger" data-finance-open="receivable-cancel-<?= (int) $entry['id'] ?>">Cancelar</button><?php endif; ?>
          <button type="button" data-finance-open="receivable-history-<?= (int) $entry['id'] ?>">Histórico</button>
        </td>
      </tr>
      <tr class="receivables-dialog-row"><td colspan="8">
      <?php if ($editable): ?>
      <dialog class="finance-dialog" id="receivable-edit-<?= (int) $entry['id'] ?>" aria-labelledby="receivable-edit-title-<?= (int) $entry['id'] ?>"><form method="post" action="/minha-conta/financeiro/recebimento-editar" data-finance-form>
        <header><h2 id="receivable-edit-title-<?= (int) $entry['id'] ?>">Editar recebimento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><?php $renderState(); ?>
        <?php $formEntry = $entry; require __DIR__ . '/receivables-fields.php'; ?>
        <footer><button type="button" data-finance-close>Voltar</button><button type="submit" class="primary">Salvar alterações</button></footer>
      </form></dialog>
      <?php endif; ?>
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
        <header><h2 id="receivable-history-title-<?= (int) $entry['id'] ?>">Histórico do recebimento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <?php if ($history['settlements'] === []): ?><p class="muted">Nenhuma baixa registrada.</p><?php else: ?><ol><?php foreach ($history['settlements'] as $item): ?><li><strong><?= $e(Finance::money((int) $item['amount_cents'])) ?></strong> em <?= $e((new DateTimeImmutable($item['received_at']))->format('d/m/Y')) ?> · <?= $e($item['account']) ?> · <?= $e($methods[$item['payment_method']] ?? $item['payment_method']) ?></li><?php endforeach; ?></ol><?php endif; ?>
        <p class="muted"><?= count($history['audit']) ?> evento<?= count($history['audit']) === 1 ? '' : 's' ?> de auditoria registrado<?= count($history['audit']) === 1 ? '' : 's' ?>.</p>
        <footer><button type="button" data-finance-close>Fechar</button></footer>
      </div></dialog>
      </td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($result['pages'] > 1): ?><nav class="receivables-pagination" aria-label="Páginas de recebimentos"><?php for ($number = 1; $number <= $result['pages']; $number++): ?><a href="<?= $e($query(['page' => $number])) ?>"<?= $number === $result['page'] ? ' aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
</section>

<dialog class="finance-dialog" id="receivable-create" aria-labelledby="receivable-create-title"><form method="post" action="/minha-conta/financeiro/recebimento" data-finance-form>
  <header><h2 id="receivable-create-title">Novo recebimento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
  <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>"><?php $renderState(); ?>
  <?php $formEntry = ['description' => '', 'amount_cents' => 0, 'due_date' => $today, 'competency_date' => $competence . '-01', 'counterparty_name' => '', 'contact_id' => null, 'notes' => '', 'document_number' => '', 'payment_method' => 'pix', 'account_id' => $accounts[0]['id'] ?? 0, 'category_id' => $categories[0]['id'] ?? 0]; require __DIR__ . '/receivables-fields.php'; ?>
  <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar recebimento</button></footer>
</form></dialog>
