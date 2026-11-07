<?php
use Moves\Services\{AppUrl, Contacts, Finance, FinanceContext, OrganizationContext, OrganizationDirectory, OrganizationTeam, ServiceOrders, SessionAuth};

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$org = OrganizationContext::current();
if ($org === null) return;
$user = SessionAuth::current();
$canManage = OrganizationTeam::can((int) $user['id'], (int) $org['id'], 'finance.manage');
$contextId = (int) FinanceContext::currentId();
$service = new ServiceOrders((int) $org['id']);
try {
    $data = $service->listing(['status' => $_GET['situacao'] ?? '', 'customer_id' => $_GET['cliente'] ?? null]);
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
$statusFilter = is_string($_GET['situacao'] ?? null) ? $_GET['situacao'] : '';
$customerFilter = (int) ($_GET['cliente'] ?? 0);
$filterQuery = array_filter(['situacao' => $statusFilter, 'cliente' => $customerFilter]);
$filterUrl = static fn (string $without): string => AppUrl::section('ordens-de-servico', null, array_diff_key($filterQuery, [$without => true]));
$filterChips = [];
if ($statusFilter !== '') $filterChips['Situação: ' . ($labels[$statusFilter] ?? $statusFilter)] = $filterUrl('situacao');
if ($customerFilter > 0) {
    foreach ($customers as $customer) if ((int) $customer['id'] === $customerFilter) $filterChips['Cliente: ' . ($customer['trade_name'] ?: $customer['legal_name'])] = $filterUrl('cliente');
}
$filterClearUrl = AppUrl::section('ordens-de-servico');
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
  <div class="finance-filter-bar"><?php $filterPanelId = 'service-order-filters'; require __DIR__ . '/../components/filter-button.php'; ?></div>
  <?php require __DIR__ . '/../components/filter-bar.php'; ?>
  <dialog class="finance-dialog filter-dialog" id="service-order-filters" aria-labelledby="service-order-filters-title"><form method="get" action="<?= $e(AppUrl::section('ordens-de-servico')) ?>" data-swap-get>
    <header><h2 id="service-order-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <label>Situação<select name="situacao"><option value="">Todas</option><?php foreach ($labels as $key => $label): ?><option value="<?= $e($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
    <label>Cliente<select name="cliente"><option value="">Todos</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= $customerFilter === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
    <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button class="primary" type="submit">Aplicar</button></footer>
  </form></dialog>

  <?php if ($data['rows'] === []): ?>
    <div class="finance-empty"><h3>Nenhuma ordem de serviço</h3><p>Cadastre a primeira ordem para organizar a execução e a cobrança.</p></div>
  <?php else: ?>
    <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Número</th><th>Cliente</th><th>Responsável</th><th>Situação</th><th>Total</th><th>Cobrança</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($data['rows'] as $row): $id = (int) $row['id']; ?>
      <tr id="ordem-<?= $id ?>" tabindex="-1">
        <td><strong><?= $e($row['order_number']) ?></strong>
          <details><summary>Itens, anexos e histórico</summary>
            <?php foreach ($row['items'] as $item): ?><small><?= $e($item['description']) ?> · <?= $e(number_format((int) $item['quantity_milli'] / 1000, 3, ',', '.')) ?> × <?= $e(Finance::money((int) $item['unit_price_cents'])) ?> = <?= $e(Finance::money((int) $item['total_cents'])) ?></small><br><?php endforeach; ?>
            <?php foreach ($row['attachments'] as $file): ?><a class="entry-chip is-file" href="/minha-conta/ordens-de-servico/anexo/<?= (int) $file['id'] ?>" download><?= $e($file['original_name']) ?></a><?php endforeach; ?>
            <?php foreach ($row['history'] as $event): ?><small><?= $e($historyLabels[$event['action']] ?? $event['action']) ?> · <?= $e((new DateTimeImmutable($event['created_at']))->format('d/m/Y H:i')) ?><?= $event['actor_name'] ? ' · ' . $e($event['actor_name']) : '' ?></small><br><?php endforeach; ?>
          </details>
        </td>
        <td><?= $e($row['customer_name']) ?></td><td><?= $e($row['responsible_name'] ?: 'Não definido') ?></td>
        <td><span class="finance-badge <?= $row['status'] === 'completed' ? 'is-paid' : ($row['status'] === 'cancelled' ? 'is-muted' : 'is-open') ?>"><?= $e($labels[$row['status']]) ?></span></td>
        <td><?= $e(Finance::money((int) $row['total_cents'])) ?></td>
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
