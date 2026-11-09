<?php
use Moves\Services\{AppUrl, Contacts, Finance, FinanceContext, OrganizationContext, OrganizationDirectory, OrganizationTeam, ServiceOrders, SessionAuth};

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$org = OrganizationContext::current();
if ($org === null) return;
$user = SessionAuth::current();
$canManage = OrganizationTeam::can((int) $user['id'], (int) $org['id'], 'finance.manage');
$contextId = (int) FinanceContext::currentId();
$service = new ServiceOrders((int) $org['id']);
$validDate = static fn (mixed $value): bool => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
$status = is_string($_GET['situacao'] ?? null) ? $_GET['situacao'] : '';
$customerFilter = (int) filter_var($_GET['cliente'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]);
$responsibleFilter = (int) filter_var($_GET['responsavel'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]);
$search = is_string($_GET['busca'] ?? null) ? trim(mb_substr($_GET['busca'], 0, 120)) : '';
$start = $validDate($_GET['inicio'] ?? null) ? $_GET['inicio'] : '';
$end = $validDate($_GET['fim'] ?? null) ? $_GET['fim'] : '';
$filters = ['customer_id' => $customerFilter ?: null, 'responsible_id' => $responsibleFilter ?: null, 'search' => $search, 'start' => $start, 'end' => $end];
try {
    $data = $service->listing(['status' => $status] + $filters);
    // Indicadores e abas contam todas as situações com os demais filtros; antes, filtrar uma situação zerava as outras.
    $all = $status === '' ? $data : $service->listing($filters);
    $data['indicators'] = $all['indicators'];
    $customers = (new Contacts($contextId))->selectableCustomers();
    $members = OrganizationDirectory::members((int) $org['id']);
    $finance = new Finance($contextId);
    $accounts = array_filter($finance->accounts(), static fn (array $account): bool => $account['type'] !== 'credit_card');
    $categories = $finance->categories('income');
} catch (Throwable $exception) {
    error_log('Service orders page: ' . $exception->getMessage());
    $data = ['rows' => [], 'indicators' => array_fill_keys(['draft', 'open', 'in_progress', 'completed', 'cancelled'], 0)];
    $customers = $members = $accounts = $categories = [];
}
$flash = $_SESSION['service_orders_flash'] ?? null;
unset($_SESSION['service_orders_flash']);
$labels = ['draft' => 'Rascunho', 'open' => 'Aberta', 'in_progress' => 'Em andamento', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'];
$historyLabels = ['created' => 'Criada', 'updated' => 'Editada', 'status_changed' => 'Situação alterada', 'receivable_created' => 'Cobrança gerada'];
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>"><?= $e($flash[1]) ?></p><?php endif; ?>

<section class="card finance-workspace" aria-labelledby="orders-title">
  <div class="finance-toolbar">
    <div><h2 id="orders-title">Ordens de serviço</h2><p class="muted">Serviços, itens e cobranças vinculadas sem alterar saldos antes do recebimento.</p></div>
    <?php if ($canManage): ?><button class="primary" type="button" data-finance-open="service-order-create">+ Nova ordem</button><?php endif; ?>
  </div>
  <div class="finance-goal-summary is-four">
    <?php foreach (['draft' => 'Rascunhos', 'open' => 'Abertas', 'in_progress' => 'Em andamento', 'completed' => 'Concluídas'] as $key => $label): ?>
      <div class="finance-goal-stat finance-indicator is-neutral"><span><?= $label ?></span><strong><?= (int) $data['indicators'][$key] ?></strong></div>
    <?php endforeach; ?>
  </div>
  <?php
  // Mesmo padrão de Contas a Receber: abas de situação, modal de filtros, etiquetas e Limpar.
  $filterQuery = array_filter(['situacao' => $status, 'busca' => $search, 'cliente' => $customerFilter ?: '', 'responsavel' => $responsibleFilter ?: '', 'inicio' => $start, 'fim' => $end], static fn (mixed $value): bool => $value !== '');
  $query = static fn (array $changes = []): string => AppUrl::section('ordens-de-servico', null, array_filter(array_merge($filterQuery, $changes), static fn (mixed $value): bool => $value !== '' && $value !== null));
  $without = static fn (string ...$keys): string => AppUrl::section('ordens-de-servico', null, array_diff_key($filterQuery, array_flip($keys)));
  $customerName = static function (int $id) use ($customers): string { foreach ($customers as $c) if ((int) $c['id'] === $id) return (string) ($c['trade_name'] ?: $c['legal_name']); return 'Cliente'; };
  $memberName = static function (int $id) use ($members): string { foreach ($members as $m) if ((int) ($m['user_id'] ?? $m['id'] ?? 0) === $id) return trim((string) ($m['name'] ?? (($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')))); return 'Responsável'; };
  ?>
  <div class="finance-filter-bar">
    <nav class="finance-status receivables-tabs" aria-label="Situação">
      <a href="<?= $e($query(['situacao' => ''])) ?>"<?= $status === '' ? ' aria-current="true"' : '' ?>>Todas (<?= array_sum($data['indicators']) ?>)</a>
      <?php foreach (['draft' => 'Rascunhos', 'open' => 'Abertas', 'in_progress' => 'Em andamento', 'completed' => 'Concluídas', 'cancelled' => 'Canceladas'] as $value => $label): ?>
      <a href="<?= $e($query(['situacao' => $value])) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?> (<?= (int) ($data['indicators'][$value] ?? 0) ?>)</a>
      <?php endforeach; ?>
    </nav>
    <?php $filterPanelId = 'service-order-filters'; require dirname(__DIR__) . '/components/filter-button.php'; ?>
  </div>
  <?php
  $filterChips = [];
  if ($start !== '' || $end !== '') $filterChips['Prazo: ' . ($start !== '' ? date('d/m/Y', strtotime($start)) : '…') . ' a ' . ($end !== '' ? date('d/m/Y', strtotime($end)) : '…')] = $without('inicio', 'fim');
  if ($search !== '') $filterChips['Busca: ' . $search] = $without('busca');
  if ($customerFilter) $filterChips['Cliente: ' . $customerName($customerFilter)] = $without('cliente');
  if ($responsibleFilter) $filterChips['Responsável: ' . $memberName($responsibleFilter)] = $without('responsavel');
  $filterClearUrl = AppUrl::section('ordens-de-servico', null, $status === '' ? [] : ['situacao' => $status]);
  require dirname(__DIR__) . '/components/filter-bar.php';
  ?>
  <dialog class="finance-dialog filter-dialog" id="service-order-filters" aria-labelledby="service-order-filters-title">
    <form method="get" action="<?= $e(AppUrl::section('ordens-de-servico')) ?>" data-swap-get>
      <header><h2 id="service-order-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
      <?php $periodStart = $start; $periodEnd = $end; require dirname(__DIR__) . '/components/filter-period.php'; ?>
      <p class="muted">O período considera o prazo da ordem. Sem datas, todas aparecem.</p>
      <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Número, cliente ou descrição" maxlength="120"></label>
      <?php if ($status !== ''): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
      <label>Cliente<select name="cliente"><option value="">Todos</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= $customerFilter === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
      <label>Responsável<select name="responsavel"><option value="">Todos</option><?php foreach ($members as $member): $memberId = (int) ($member['user_id'] ?? $member['id'] ?? 0); ?><option value="<?= $memberId ?>"<?= $responsibleFilter === $memberId ? ' selected' : '' ?>><?= $e($memberName($memberId)) ?></option><?php endforeach; ?></select></label>
      <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
    </form>
  </dialog>

  <?php if ($data['rows'] === []): ?>
    <div class="finance-empty"><h3>Nenhuma ordem de serviço</h3><p>Cadastre a primeira ordem para organizar a execução e a cobrança.</p></div>
  <?php else: ?>
    <div class="finance-table-wrap"><table class="finance-table finance-entries"><thead><tr><th>Número</th><th>Cliente</th><th>Responsável</th><th>Situação</th><th class="num">Total</th><th>Cobrança</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($data['rows'] as $row): $id = (int) $row['id']; ?>
      <tr id="ordem-<?= $id ?>" tabindex="-1">
        <td><strong class="nowrap"><?= $e($row['order_number']) ?></strong>
          <details><summary>Itens, anexos e histórico</summary>
            <?php foreach ($row['items'] as $item): ?><small><?= $e($item['description']) ?> · <?= $e(number_format((int) $item['quantity_milli'] / 1000, 3, ',', '.')) ?> × <?= $e(Finance::money((int) $item['unit_price_cents'])) ?> = <?= $e(Finance::money((int) $item['total_cents'])) ?></small><br><?php endforeach; ?>
            <?php foreach ($row['attachments'] as $file): ?><a class="entry-chip is-file" href="/minha-conta/ordens-de-servico/anexo/<?= (int) $file['id'] ?>" download><?= $e($file['original_name']) ?></a><?php endforeach; ?>
            <?php foreach ($row['history'] as $event): ?><small><?= $e($historyLabels[$event['action']] ?? $event['action']) ?> · <?= $e((new DateTimeImmutable($event['created_at']))->format('d/m/Y H:i')) ?><?= $event['actor_name'] ? ' · ' . $e($event['actor_name']) : '' ?></small><br><?php endforeach; ?>
          </details>
        </td>
        <td><?= $e($row['customer_name']) ?></td><td><?= $e($row['responsible_name'] ?: 'Não definido') ?></td>
        <td><span class="finance-badge <?= $row['status'] === 'completed' ? 'is-paid' : ($row['status'] === 'cancelled' ? 'is-muted' : 'is-open') ?>"><?= $e($labels[$row['status']]) ?></span></td>
        <td class="num"><?= $e(Finance::money((int) $row['total_cents'])) ?></td>
        <td><?= $row['receivable_entry_id'] !== null ? '<a href="' . $e(AppUrl::section('receber')) . '">Vinculada</a>' : 'Não gerada' ?></td>
        <td class="finance-actions">
          <?php if ($canManage && $row['status'] !== 'cancelled' && $row['receivable_entry_id'] === null): ?><button type="button" data-finance-open="service-order-edit-<?= $id ?>">Editar</button><?php endif; ?>
          <?php if ($canManage && $row['status'] !== 'cancelled'): ?><form method="post" action="/minha-conta/ordens-de-servico/situacao"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>"><select name="status" aria-label="Nova situação"><?php foreach ($labels as $key => $label): ?><option value="<?= $e($key) ?>"<?= $row['status'] === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><button>Atualizar</button></form><?php endif; ?>
          <?php if ($canManage && $row['receivable_entry_id'] === null && $row['status'] !== 'cancelled'): ?><button type="button" data-finance-open="order-charge-<?= $id ?>">Gerar cobrança</button><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
  <?php foreach ($data['rows'] as $row): $id = (int) $row['id']; ?>
    <?php if ($row['status'] !== 'cancelled' && $row['receivable_entry_id'] === null): ?>
      <dialog class="finance-dialog" id="service-order-edit-<?= $id ?>"><form method="post" action="/minha-conta/ordens-de-servico/atualizar" enctype="multipart/form-data" data-finance-form data-service-order-form>
        <header><h2>Editar <?= $e($row['order_number']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
        <?php require __DIR__ . '/../components/service-order-fields.php'; ?>
        <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar alterações</button></footer>
      </form></dialog>
      <dialog class="finance-dialog" id="order-charge-<?= $id ?>"><form method="post" action="/minha-conta/ordens-de-servico/gerar-cobranca" data-finance-form>
        <header><h2>Gerar cobrança · <?= $e($row['order_number']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Vencimento<input type="date" name="due_date" value="<?= $e($row['due_date'] ?: date('Y-m-d')) ?>" required></label>
        <label>Conta prevista<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
        <label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="payment_method" value="other">
        <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Criar em Receber</button></footer>
      </form></dialog>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php $row = null; ?>
  <dialog class="finance-dialog" id="service-order-create"><form method="post" action="/minha-conta/ordens-de-servico/salvar" enctype="multipart/form-data" data-finance-form data-service-order-form>
    <header><h2>Nova ordem de serviço</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <?php require __DIR__ . '/../components/service-order-fields.php'; ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar rascunho</button></footer>
  </form></dialog>
<?php endif; ?>
