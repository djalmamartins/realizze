<?php
use Moves\Services\{Finance, OrganizationContext, OrganizationDirectory, OrganizationObligations, OrganizationTeam, SessionAuth};

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$org = OrganizationContext::current();
if ($org === null) return;
$user = SessionAuth::current();
$canManage = OrganizationTeam::can((int) $user['id'], (int) $org['id'], 'finance.manage');
$validDate = static fn (mixed $value): bool => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
$start = $validDate($_GET['inicio'] ?? null) ? $_GET['inicio'] : date('Y-m-01');
$end = $validDate($_GET['fim'] ?? null) ? $_GET['fim'] : date('Y-m-t', strtotime('+2 months'));
$status = (string) ($_GET['situacao'] ?? '');
$type = (string) ($_GET['tipo'] ?? '');
$service = new OrganizationObligations((int) $org['id']);
try {
    $data = $service->listing(['start' => $start, 'end' => $end, 'status' => $status, 'type' => $type]);
    // Contagem das abas: mesmo período e tipo, todas as situações.
    $all = $status === '' ? $data : $service->listing(['start' => $start, 'end' => $end, 'type' => $type]);
    $tabCounts = array_count_values(array_column($all['rows'], 'display_status'));
    $members = OrganizationDirectory::members((int) $org['id']);
} catch (Throwable $exception) {
    error_log('Obligations page: ' . $exception->getMessage());
    $data = ['rows' => [], 'indicators' => ['pending' => 0, 'upcoming' => 0, 'overdue' => 0, 'completed' => 0]];
    $tabCounts = [];
    $members = [];
}
$flash = $_SESSION['obligations_flash'] ?? null;
unset($_SESSION['obligations_flash']);
$labels = ['pending' => 'Pendente', 'upcoming' => 'Próxima do vencimento', 'overdue' => 'Vencida', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'];
$types = ['tax' => 'Fiscal', 'labor' => 'Trabalhista', 'license' => 'Licença', 'contract' => 'Contrato', 'other' => 'Outra'];
$actions = ['created' => 'Criada', 'updated' => 'Editada', 'completed' => 'Concluída', 'pending' => 'Reaberta', 'cancelled' => 'Cancelada'];
?>
<?php if (is_array($flash)): ?>
  <p class="finance-flash finance-flash-<?= $e($flash[0]) ?>"><?= $e($flash[1]) ?></p>
<?php endif; ?>

<section class="card finance-workspace" aria-labelledby="obligations-title">
  <div class="finance-toolbar">
    <div><h2 id="obligations-title">Calendário de obrigações</h2><p class="muted">Agenda manual. O Realizze não calcula impostos nem gera despesas automaticamente.</p></div>
    <?php if ($canManage): ?><button class="primary" type="button" data-finance-open="obligation-create">+ Nova obrigação</button><?php endif; ?>
  </div>
  <div class="finance-goal-summary is-four">
    <?php foreach (['pending' => 'Pendentes', 'upcoming' => 'Próximas', 'overdue' => 'Vencidas', 'completed' => 'Concluídas'] as $key => $label): ?>
      <div class="finance-goal-stat finance-indicator <?= $key === 'overdue' && $data['indicators'][$key] > 0 ? 'is-overdue' : 'is-neutral' ?>"><span><?= $label ?></span><strong><?= (int) $data['indicators'][$key] ?></strong></div>
    <?php endforeach; ?>
  </div>
  <?php
  // Mesmo padrão de Contas a Receber: abas de situação, modal de filtros, etiquetas e Limpar.
  $defaultStart = date('Y-m-01');
  $defaultEnd = date('Y-m-t', strtotime('+2 months'));
  $filterQuery = array_filter(['inicio' => $start, 'fim' => $end, 'situacao' => $status, 'tipo' => $type], static fn (string $value): bool => $value !== '');
  $query = static fn (array $changes = []): string => \Moves\Services\AppUrl::section('obrigacoes', null, array_filter(array_merge($filterQuery, $changes), static fn (mixed $value): bool => $value !== '' && $value !== null));
  $without = static fn (string ...$keys): string => \Moves\Services\AppUrl::section('obrigacoes', null, array_diff_key($filterQuery, array_flip($keys)));
  ?>
  <div class="finance-filter-bar">
    <nav class="finance-status receivables-tabs" aria-label="Situação">
      <a href="<?= $e($query(['situacao' => ''])) ?>"<?= $status === '' ? ' aria-current="true"' : '' ?>>Todas (<?= array_sum($tabCounts) ?>)</a>
      <?php foreach (['pending' => 'Pendentes', 'upcoming' => 'Próximas', 'overdue' => 'Vencidas', 'completed' => 'Concluídas', 'cancelled' => 'Canceladas'] as $value => $label): ?>
      <a href="<?= $e($query(['situacao' => $value])) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?> (<?= (int) ($tabCounts[$value] ?? 0) ?>)</a>
      <?php endforeach; ?>
    </nav>
    <?php $filterPanelId = 'obligation-filters'; require dirname(__DIR__) . '/components/filter-button.php'; ?>
  </div>
  <?php
  $filterChips = [];
  if ($start !== $defaultStart || $end !== $defaultEnd) $filterChips['Vencimento: ' . date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end))] = $without('inicio', 'fim');
  if (isset($types[$type])) $filterChips['Tipo: ' . $types[$type]] = $without('tipo');
  $filterClearUrl = \Moves\Services\AppUrl::section('obrigacoes', null, $status === '' ? [] : ['situacao' => $status]);
  require dirname(__DIR__) . '/components/filter-bar.php';
  ?>
  <dialog class="finance-dialog filter-dialog" id="obligation-filters" aria-labelledby="obligation-filters-title">
    <form method="get" action="<?= $e(\Moves\Services\AppUrl::section('obrigacoes')) ?>" data-swap-get>
      <header><h2 id="obligation-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
      <?php $periodStart = $start; $periodEnd = $end; require dirname(__DIR__) . '/components/filter-period.php'; ?>
      <?php if ($status !== ''): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
      <label>Tipo<select name="tipo"><option value="">Todos</option><?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
      <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
    </form>
  </dialog>

  <?php if ($data['rows'] === []): ?>
    <div class="finance-empty"><h3>Nenhuma obrigação no período</h3><p>Cadastre compromissos reais da empresa; nenhuma obrigação fiscal é presumida.</p></div>
  <?php else: ?>
    <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Obrigação</th><th>Competência</th><th>Vencimento</th><th>Responsável</th><th>Situação</th><th>Valor</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($data['rows'] as $row): $id = (int) $row['id']; ?>
      <tr id="obrigacao-<?= $id ?>" tabindex="-1">
        <td><strong><?= $e($row['title']) ?></strong><small><?= $e($types[$row['obligation_type']] ?? $row['obligation_type']) ?><?= (int) $row['sequence_no'] > 1 ? ' · recorrência ' . $e($row['sequence_no']) : '' ?></small>
          <details><summary>Comprovantes e histórico</summary>
            <?php foreach ($row['attachments'] as $file): ?><a class="entry-chip is-file" href="/minha-conta/obrigacoes/comprovante/<?= (int) $file['id'] ?>" download><?= $e($file['original_name']) ?></a><?php endforeach; ?>
            <?php if ($row['attachments'] === []): ?><small>Nenhum comprovante.</small><?php endif; ?>
            <?php foreach ($row['history'] as $event): ?><small><?= $e($actions[$event['action']] ?? $event['action']) ?> · <?= $e((new DateTimeImmutable($event['created_at']))->format('d/m/Y H:i')) ?><?= $event['actor_name'] ? ' · ' . $e($event['actor_name']) : '' ?></small><br><?php endforeach; ?>
          </details>
        </td>
        <td><?= $e((new DateTimeImmutable($row['competence_date']))->format('m/Y')) ?></td>
        <td><?= $e((new DateTimeImmutable($row['due_date']))->format('d/m/Y')) ?></td>
        <td><?= $e($row['responsible_name'] ?: 'Não definido') ?></td>
        <td><span class="finance-badge <?= $row['display_status'] === 'overdue' ? 'is-overdue' : ($row['display_status'] === 'completed' ? 'is-paid' : 'is-open') ?>"><?= $e($labels[$row['display_status']] ?? $row['display_status']) ?></span></td>
        <td><?= $row['amount_cents'] !== null ? $e(Finance::money((int) $row['amount_cents'])) : '—' ?></td>
        <td class="finance-actions">
          <?php if ($canManage && $row['status'] !== 'cancelled'): ?><button type="button" data-finance-open="obligation-edit-<?= $id ?>">Editar</button><?php endif; ?>
          <?php if ($canManage && $row['status'] === 'pending'): ?>
            <form method="post" action="/minha-conta/obrigacoes/concluir"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button>Concluir</button></form>
            <form method="post" action="/minha-conta/obrigacoes/cancelar" data-finance-confirm="Cancelar esta obrigação?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="is-danger">Cancelar</button></form>
          <?php elseif ($canManage && $row['status'] === 'completed'): ?>
            <form method="post" action="/minha-conta/obrigacoes/reabrir"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button>Reabrir</button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($canManage && $row['status'] !== 'cancelled'): ?>
        <dialog class="finance-dialog" id="obligation-edit-<?= $id ?>"><form method="post" action="/minha-conta/obrigacoes/atualizar" enctype="multipart/form-data" data-finance-form>
          <header><h2>Editar obrigação</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
          <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
          <?php require __DIR__ . '/../components/obligation-fields.php'; ?>
          <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar alterações</button></footer>
        </form></dialog>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<?php if ($canManage): $row = null; ?>
  <dialog class="finance-dialog" id="obligation-create"><form method="post" action="/minha-conta/obrigacoes/salvar" enctype="multipart/form-data" data-finance-form>
    <header><h2>Nova obrigação</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <?php require __DIR__ . '/../components/obligation-fields.php'; ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar</button></footer>
  </form></dialog>
<?php endif; ?>
