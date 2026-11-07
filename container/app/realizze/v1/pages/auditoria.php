<?php
/** Registro das ações administrativas da empresa ativa. Só leitura; a rota exige team.manage. */
use Moves\Services\AppUrl;
use Moves\Services\OrganizationAudit;
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationTeam;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$active = OrganizationContext::current();
$orgId = (int) ($active['id'] ?? 0);
$page = max(1, (int) filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
$failed = false;
$log = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$actors = [];
try {
    $log = OrganizationAudit::listing($orgId, $_GET, $page);
    $actors = OrganizationAudit::actors($orgId);
} catch (Throwable $exception) {
    error_log('Realizze audit view: ' . $exception->getMessage());
    $failed = true;
}
$roleNames = array_map(static fn (array $role): string => $role[0], OrganizationTeam::ROLES);
$fieldLabels = ['name' => 'Razão social', 'trade_name' => 'Nome fantasia', 'document_value' => 'CNPJ', 'email' => 'E-mail', 'phone' => 'Telefone'];
$detail = static function (array $row) use ($roleNames, $fieldLabels): string {
    $changes = $row['changes'];
    if (isset($changes['role'])) {
        return 'Papel: ' . ($roleNames[$changes['role']] ?? $changes['role']);
    }
    $fields = array_keys(array_intersect_key($fieldLabels, $changes));
    return $fields === [] ? '' : 'Campos: ' . implode(', ', array_map(static fn (string $f): string => $fieldLabels[$f], $fields));
};
$filters = array_intersect_key($_GET, array_flip(['tipo', 'pessoa', 'de', 'ate']));
$pageLink = static fn (int $target): string => AppUrl::section('auditoria', null, $filters + ['p' => $target]);
$filterUrl = static fn (string ...$without): string => AppUrl::section('auditoria', null, array_diff_key($filters, array_flip($without)));
$formatFilterDate = static fn (mixed $value): string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? (new DateTimeImmutable($value))->format('d/m/Y') : '…';
$filterChips = [];
if (!empty($filters['tipo'])) $filterChips['Ação: ' . (OrganizationAudit::EVENTS[$filters['tipo']] ?? $filters['tipo'])] = $filterUrl('tipo');
if (!empty($filters['pessoa'])) {
    foreach ($actors as $actor) if ((string) $actor['id'] === (string) $filters['pessoa']) $filterChips['Pessoa: ' . $actor['name']] = $filterUrl('pessoa');
}
if (!empty($filters['de']) || !empty($filters['ate'])) $filterChips['Período: ' . $formatFilterDate($filters['de'] ?? null) . ' a ' . $formatFilterDate($filters['ate'] ?? null)] = $filterUrl('de', 'ate');
$filterClearUrl = AppUrl::section('auditoria');
?>
<section class="card finance-workspace" aria-labelledby="audit-title">
  <div class="finance-toolbar"><div><h2 id="audit-title">Auditoria</h2><p class="muted">Ações administrativas em <?= $e($active['name'] ?? 'nesta empresa') ?>: equipe, convites e dados cadastrais. O registro não pode ser editado.</p></div></div>
  <div class="finance-filter-bar"><?php $filterPanelId = 'audit-filters'; require __DIR__ . '/../components/filter-button.php'; ?></div>
  <?php require __DIR__ . '/../components/filter-bar.php'; ?>
  <dialog class="finance-dialog filter-dialog" id="audit-filters" aria-labelledby="audit-filters-title"><form method="get" action="<?= $e(AppUrl::section('auditoria')) ?>" data-swap-get>
    <header><h2 id="audit-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?php $periodStart = (string) ($_GET['de'] ?? ''); $periodEnd = (string) ($_GET['ate'] ?? ''); $periodStartName = 'de'; $periodEndName = 'ate'; require __DIR__ . '/../components/filter-period.php'; unset($periodStartName, $periodEndName); ?>
    <label>Ação<select name="tipo"><option value="">Todas</option><?php foreach (OrganizationAudit::EVENTS as $key => $label): ?><option value="<?= $e($key) ?>"<?= ($_GET['tipo'] ?? '') === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
    <label>Pessoa<select name="pessoa"><option value="">Todas</option><?php foreach ($actors as $actor): ?><option value="<?= (int) $actor['id'] ?>"<?= (string) ($_GET['pessoa'] ?? '') === (string) $actor['id'] ? ' selected' : '' ?>><?= $e($actor['name']) ?></option><?php endforeach; ?></select></label>
    <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button class="primary" type="submit">Aplicar</button></footer>
  </form></dialog>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar a auditoria</h3><p>Tente novamente em instantes.</p></div>
  <?php elseif ($log['rows'] === []): ?>
  <div class="finance-empty"><h3>Nenhuma ação encontrada</h3><p><?= $filters === [] ? 'Convites, mudanças de papel e alterações nos dados da empresa aparecem aqui.' : 'Nenhuma ação com estes filtros.' ?></p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Registro de ações">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Data</th><th scope="col">Ação</th><th scope="col">Pessoa</th><th scope="col">Detalhe</th></tr></thead>
      <tbody>
      <?php foreach ($log['rows'] as $row): ?>
      <tr>
        <td><?= $e((new DateTimeImmutable((string) $row['created_at']))->format('d/m/Y H:i')) ?></td>
        <td><strong><?= $e(OrganizationAudit::EVENTS[$row['event_type']] ?? $row['event_type']) ?></strong></td>
        <td><?= $e($row['actor_name'] ?: 'Conta removida') ?><?php if (!empty($row['actor_email'])): ?><small class="contact-legal"><?= $e($row['actor_email']) ?></small><?php endif; ?></td>
        <td><?= $e($detail($row) ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($log['pages'] > 1): ?>
  <nav class="receivables-pagination" aria-label="Páginas da auditoria"><?php for ($number = 1; $number <= $log['pages']; $number++): ?><a href="<?= $e($pageLink($number)) ?>"<?= $number === $log['page'] ? ' aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
