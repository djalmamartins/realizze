<?php
/** Clientes e fornecedores do contexto atual: uma estrutura para os dois papéis. */
use Moves\Services\Contacts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$search = is_string($_GET['busca'] ?? null) ? mb_substr(trim($_GET['busca']), 0, 180) : '';
$role = in_array($_GET['papel'] ?? '', ['customer', 'supplier', 'both'], true) ? $_GET['papel'] : '';
$type = in_array($_GET['tipo'] ?? '', ['individual', 'company'], true) ? $_GET['tipo'] : '';
$status = in_array($_GET['situacao'] ?? '', ['active', 'inactive'], true) ? $_GET['situacao'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$labels = match ($role) {
    'customer' => ['title' => 'Clientes', 'lead' => 'Quem compra da empresa, com histórico de recebimentos.', 'new' => 'Novo cliente', 'one' => 'cliente', 'many' => 'clientes', 'back' => 'clientes'],
    'supplier' => ['title' => 'Fornecedores', 'lead' => 'Quem fornece para a empresa e seus dados de contato.', 'new' => 'Novo fornecedor', 'one' => 'fornecedor', 'many' => 'fornecedores', 'back' => 'fornecedores'],
    default => ['title' => 'Clientes e fornecedores', 'lead' => 'Todos os contatos financeiros da empresa.', 'new' => 'Novo contato', 'one' => 'contato', 'many' => 'contatos', 'back' => 'contatos'],
};
$fixedRole = in_array($role, ['customer', 'supplier'], true);
$result = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$tabCounts = ['' => 0, 'active' => 0, 'inactive' => 0];
$service = null;
$failed = false;
try {
    $service = new Contacts((int) FinanceContext::currentId());
    $result = $service->listing(compact('search', 'role', 'type', 'status', 'page'));
    // Contagem das abas com os demais filtros (busca, tipo, papel).
    $tabCounts = [];
    foreach (['' => '', 'active' => 'active', 'inactive' => 'inactive'] as $tab => $tabStatus) {
        $tabCounts[$tab] = $tabStatus === $status ? (int) $result['total'] : (int) $service->listing(['search' => $search, 'role' => $role, 'type' => $type, 'status' => $tabStatus, 'page' => 1])['total'];
    }
} catch (Throwable $exception) {
    error_log('Realizze contacts view: ' . $exception->getMessage());
    $failed = true;
}
$flash = $_SESSION['contacts_flash'] ?? null;
unset($_SESSION['contacts_flash']);
$mask = static function (?string $d): string {
    if (!$d) {
        return 'Não informado';
    }
    return strlen($d) === 11 ? substr($d, 0, 3) . '.***.***-' . substr($d, -2) : substr($d, 0, 2) . '.***.***/****-' . substr($d, -2);
};
$path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/pj/minha-empresa/contatos';
$query = array_filter(['busca' => $search, 'tipo' => $type, 'situacao' => $status] + ($fixedRole ? [] : ['papel' => $role]), static fn ($x) => $x !== '');
$rowDialogs = '';
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="contacts-title">
  <div class="finance-toolbar">
    <div><h2 id="contacts-title"><?= $e($labels['title']) ?></h2><p class="muted"><?= $e($labels['lead']) ?></p></div>
    <button class="primary" type="button" data-finance-open="contact-create">+ <?= $e($labels['new']) ?></button>
  </div>
  <?php
  // Mesmo padrão de Contas a Receber: abas de situação, modal de filtros, etiquetas e Limpar.
  $link = static function (array $changes = []) use ($path, $query): string {
      $next = array_filter(array_merge($query, $changes), static fn ($x) => $x !== '' && $x !== null);
      return $path . ($next === [] ? '' : '?' . http_build_query($next));
  };
  $without = static fn (string ...$keys): string => $link(array_fill_keys($keys, ''));
  $typeLabels = ['individual' => 'Pessoa física', 'company' => 'Pessoa jurídica'];
  $roleLabels = ['customer' => 'Clientes', 'supplier' => 'Fornecedores', 'both' => 'Ambos'];
  ?>
  <div class="finance-filter-bar">
    <nav class="finance-status receivables-tabs" aria-label="Situação">
      <?php foreach (['' => 'Todos', 'active' => 'Ativos', 'inactive' => 'Inativos'] as $value => $label): ?>
      <a href="<?= $e($link(['situacao' => $value, 'page' => ''])) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?> (<?= (int) ($tabCounts[$value] ?? 0) ?>)</a>
      <?php endforeach; ?>
    </nav>
    <?php $filterPanelId = 'contact-filters'; require dirname(__DIR__) . '/components/filter-button.php'; ?>
  </div>
  <?php
  $filterChips = [];
  if ($search !== '') $filterChips['Busca: ' . $search] = $without('busca', 'page');
  if ($type !== '') $filterChips['Tipo: ' . $typeLabels[$type]] = $without('tipo', 'page');
  if (!$fixedRole && $role !== '') $filterChips['Papel: ' . $roleLabels[$role]] = $without('papel', 'page');
  $filterClearUrl = $path . ($status === '' ? '' : '?' . http_build_query(['situacao' => $status]));
  require dirname(__DIR__) . '/components/filter-bar.php';
  ?>
  <dialog class="finance-dialog filter-dialog" id="contact-filters" aria-labelledby="contact-filters-title">
    <form method="get" action="<?= $e($path) ?>" data-swap-get>
      <header><h2 id="contact-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
      <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Nome, fantasia ou documento" maxlength="180"></label>
      <?php if ($status !== ''): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
      <?php if (!$fixedRole): ?><label>Papel<select name="papel"><option value="">Todos</option><?php foreach ($roleLabels as $key => $label): ?><option value="<?= $e($key) ?>"<?= $role === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <label>Tipo<select name="tipo"><option value="">Todos</option><?php foreach ($typeLabels as $key => $label): ?><option value="<?= $e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
      <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
    </form>
  </dialog>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os <?= $e($labels['many']) ?></h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif (!$result['rows']): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('building') ?></span><h3><?= $query === [] ? 'Nenhum ' . $e($labels['one']) . ' cadastrado' : 'Nenhum resultado para esse filtro' ?></h3><p><?= $query === [] ? 'Use o botão acima para cadastrar o primeiro.' : 'Altere os filtros ou limpe a pesquisa.' ?></p></div>
  <?php else: ?>
  <p class="contact-count"><?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? $e($labels['one']) : $e($labels['many']) ?></p>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="<?= $e($labels['title']) ?>">
    <table class="finance-table finance-entries contact-table">
      <thead><tr><th scope="col">Nome</th><th scope="col">Documento</th><th scope="col">Tipo</th><?php if (!$fixedRole): ?><th scope="col">Papel</th><?php endif; ?><th scope="col">Contato</th><th scope="col">Situação</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $row):
          $id = (int) $row['id'];
          $name = $row['trade_name'] ?: ($row['social_name'] ?: $row['legal_name']);
          $roleLabel = $row['is_customer'] && $row['is_supplier'] ? 'Cliente e fornecedor' : ($row['is_customer'] ? 'Cliente' : 'Fornecedor');
          $active = $row['status'] === 'active';
          $summary = $service->financialSummary($id);
          ob_start(); ?>
<dialog class="finance-dialog" id="contact-view-<?= $id ?>" aria-labelledby="contact-view-title-<?= $id ?>">
  <div class="finance-dialog-body">
    <header><h2 id="contact-view-title-<?= $id ?>"><?= $e($name) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <p class="contact-tags"><span class="finance-badge is-<?= $active ? 'paid' : 'overdue' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span> <span class="finance-badge is-open"><?= $e($roleLabel) ?></span></p>
    <div class="contact-summary"><div><span>Recebimentos vinculados</span><strong><?= (int) $summary['entries'] ?></strong></div><div><span>Total</span><strong><?= $e(Finance::money((int) $summary['total_cents'])) ?></strong></div></div>
    <dl class="contact-details">
      <div><dt>Nome ou razão social</dt><dd><?= $e($row['legal_name']) ?></dd></div>
      <div><dt><?= $row['person_type'] === 'company' ? 'CNPJ' : 'CPF' ?></dt><dd><?= $e($mask($row['document_value'])) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= $e($row['email'] ?: 'Não informado') ?></dd></div>
      <div><dt>Telefone</dt><dd><?= $e($row['mobile'] ?: ($row['phone'] ?: 'Não informado')) ?></dd></div>
      <div class="is-wide"><dt>Endereço</dt><dd><?= $e(trim(implode(', ', array_filter([$row['street'] ?? null, $row['address_number'] ?? null, $row['district'] ?? null, $row['city'] ?? null, $row['state'] ?? null]))) ?: 'Não informado') ?></dd></div>
      <div class="is-wide"><dt>Observações</dt><dd><?= $e($row['notes'] ?: 'Sem observações.') ?></dd></div>
    </dl>
    <footer><button type="button" data-finance-close>Fechar</button><button type="button" class="primary" data-finance-close data-finance-open-after="contact-edit-<?= $id ?>">Editar</button></footer>
  </div>
</dialog>
<dialog class="finance-dialog finance-dialog-wide" id="contact-edit-<?= $id ?>" aria-labelledby="contact-edit-title-<?= $id ?>">
  <form method="post" action="/minha-conta/contatos/atualizar" data-finance-form>
    <header><h2 id="contact-edit-title-<?= $id ?>">Editar <?= $e($labels['one']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($labels['back']) ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="finance-dialog-scroll"><?php $contact = $row; $defaultRole = $role; require __DIR__ . '/../components/contact-fields.php'; ?></div>
    <footer><button type="button" data-finance-close>Cancelar</button><button class="primary" type="submit">Salvar</button></footer>
  </form>
</dialog>
<?php $rowDialogs .= (string) ob_get_clean(); ?>
      <tr>
        <td><strong><?= $e($name) ?></strong><?php if ($name !== $row['legal_name']): ?><small class="contact-legal"><?= $e($row['legal_name']) ?></small><?php endif; ?></td>
        <td class="nowrap"><?= $e($mask($row['document_value'])) ?></td>
        <td><?= $row['person_type'] === 'company' ? 'PJ' : 'PF' ?></td>
        <?php if (!$fixedRole): ?><td><?= $e($roleLabel) ?></td><?php endif; ?>
        <td><?= $e($row['email'] ?: ($row['mobile'] ?: ($row['phone'] ?: 'Não informado'))) ?></td>
        <td><span class="finance-badge is-<?= $active ? 'paid' : 'overdue' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span></td>
        <td class="finance-actions">
          <a href="<?= $e(\Moves\Services\AppUrl::section($row['is_customer'] || $role !== 'supplier' ? ($role === 'supplier' ? 'fornecedores' : 'clientes') : 'fornecedores') . '/' . $id) ?>">Ver</a>
          <button type="button" data-finance-open="contact-edit-<?= $id ?>">Editar</button>
          <form method="post" action="/minha-conta/contatos/status"<?= $active ? ' data-finance-confirm="Inativar ' . $e($name) . '?"' : '' ?>><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($labels['back']) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>"><button type="submit"<?= $active ? ' class="is-danger"' : '' ?>><?= $active ? 'Inativar' : 'Reativar' ?></button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($result['pages'] > 1): ?>
  <nav class="receivables-pagination" aria-label="Páginas">
    <?php for ($p = 1; $p <= $result['pages']; $p++): ?><a href="<?= $e($path . '?' . http_build_query($query + ['page' => $p])) ?>"<?= $p === (int) $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a><?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?= $rowDialogs ?>
<dialog class="finance-dialog finance-dialog-wide" id="contact-create" aria-labelledby="contact-create-title">
  <form method="post" action="/minha-conta/contatos/salvar" data-finance-form>
    <header><h2 id="contact-create-title"><?= $e($labels['new']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($labels['back']) ?>">
    <div class="finance-dialog-scroll"><?php $contact = []; $defaultRole = $role; require __DIR__ . '/../components/contact-fields.php'; ?></div>
    <footer><button type="button" data-finance-close>Cancelar</button><button class="primary" type="submit">Cadastrar</button></footer>
  </form>
</dialog>
