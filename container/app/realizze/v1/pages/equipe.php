<?php
/** Membros, convites e papéis da empresa ativa. A autorização real fica em OrganizationTeam. */
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationTeam as Team;
use Moves\Services\SessionAuth;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$active = OrganizationContext::current();
$userId = (int) (SessionAuth::current()['id'] ?? 0);
$orgId = (int) ($active['id'] ?? 0);
$members = $invitations = [];
$failed = false;
try {
    $members = Team::members($orgId);
    $invitations = Team::pendingInvitations($orgId);
    $canManage = Team::can($userId, $orgId, 'team.manage');
    $own = Team::permissionsFor($userId, $orgId);
} catch (Throwable $exception) {
    error_log('Realizze team view: ' . $exception->getMessage());
    $failed = true;
    $canManage = false;
    $own = [];
}
$assignable = array_filter(Team::ROLES, static fn (array $role): bool => array_diff($role[2], $own) === []);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$inviteLink = $_SESSION['team_invite_link'] ?? null;
unset($_SESSION['team_invite_link']);
$activeCount = count(array_filter($members, static fn (array $m): bool => $m['status'] === 'active'));
$field = static fn (string $name, mixed $value): string => '<input type="hidden" name="' . $e($name) . '" value="' . $e($value) . '">';
$token = $field('csrf_token', $csrf);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<?php if (is_string($inviteLink)): ?><p class="finance-flash finance-flash-success team-link">Link do convite, visível só agora: <code><?= $e($inviteLink) ?></code></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="team-title">
  <div class="finance-toolbar">
    <div><h2 id="team-title">Equipe e permissões</h2><p class="muted">Quem acessa os dados de <?= $e($active['name'] ?? 'esta empresa') ?>.</p></div>
    <?php if ($canManage): ?><button type="button" class="primary" data-finance-open="team-invite">+ Convidar pessoa</button><?php endif; ?>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo da equipe">
    <div class="finance-goal-stat"><span>Membros</span><strong><?= count($members) ?></strong><small>Com acesso à empresa</small></div>
    <div class="finance-goal-stat"><span>Ativos</span><strong class="green"><?= $activeCount ?></strong><small>Podem entrar agora</small></div>
    <div class="finance-goal-stat"><span>Convites pendentes</span><strong><?= count($invitations) ?></strong><small>Expiram em até 7 dias</small></div>
  </div>
  <?php if ($failed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar a equipe</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Membros da equipe">
    <table class="finance-table finance-entries team-table">
      <thead><tr><th scope="col">Nome</th><th scope="col">Papel</th><th scope="col">Situação</th><th scope="col">Desde</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($members as $member):
          $target = (int) $member['user_id'];
          $locked = !$canManage || $target === $userId || $member['role_key'] === 'owner' || array_diff(Team::ROLES[$member['role_key']][2] ?? ['*'], $own) !== [];
          $isActive = $member['status'] === 'active'; ?>
      <tr>
        <td><strong><?= $e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></strong><small class="contact-legal"><?= $e($member['email']) ?></small></td>
        <td><?php if ($locked): ?><?= $e($member['role']) ?><?php else: ?><form method="post" action="/minha-conta/equipe/papel" class="team-role"><?= $token . $field('id', $target) ?><label class="sr-only" for="team-role-<?= $target ?>">Papel</label><select id="team-role-<?= $target ?>" name="role"><?php foreach ($assignable as $key => $role): ?><option value="<?= $e($key) ?>"<?= $key === $member['role_key'] ? ' selected' : '' ?>><?= $e($role[0]) ?></option><?php endforeach; ?></select><button type="submit">Salvar</button></form><?php endif; ?></td>
        <td><span class="finance-badge is-<?= $isActive ? 'paid' : 'overdue' ?>"><?= $isActive ? 'Ativo' : 'Suspenso' ?></span></td>
        <td class="nowrap"><?= $e((new DateTimeImmutable($member['joined_at']))->format('d/m/Y')) ?></td>
        <td class="finance-actions"><?php if (!$locked): ?>
          <form method="post" action="/minha-conta/equipe/<?= $isActive ? 'suspender' : 'reativar' ?>"><?= $token . $field('id', $target) ?><button type="submit"><?= $isActive ? 'Suspender' : 'Reativar' ?></button></form>
          <form method="post" action="/minha-conta/equipe/remover" data-finance-confirm="Revogar o acesso de <?= $e($member['first_name']) ?>?"><?= $token . $field('id', $target) ?><button type="submit" class="is-danger">Revogar</button></form>
        <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($invitations !== []): ?>
  <h3 class="team-subtitle">Convites pendentes</h3>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Convites pendentes">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">E-mail</th><th scope="col">Papel</th><th scope="col">Expira em</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($invitations as $invitation): ?>
      <tr><td><?= $e($invitation['email']) ?></td><td><?= $e($invitation['role']) ?></td><td class="nowrap"><?= $e((new DateTimeImmutable($invitation['expires_at']))->format('d/m/Y')) ?></td>
        <td class="finance-actions"><?php if ($canManage): ?>
          <form method="post" action="/minha-conta/equipe/reenviar"><?= $token . $field('id', $invitation['id']) ?><button type="submit">Reenviar</button></form>
          <form method="post" action="/minha-conta/equipe/cancelar-convite" data-finance-confirm="Cancelar este convite?"><?= $token . $field('id', $invitation['id']) ?><button type="submit" class="is-danger">Cancelar</button></form>
        <?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <div class="role-notes" aria-label="Papéis disponíveis">
    <div><strong>Proprietário</strong><small>Acesso total, inclusive à equipe.</small></div>
    <?php foreach (Team::ROLES as $role): ?><div><strong><?= $e($role[0]) ?></strong><small><?= $e($role[1]) ?></small></div><?php endforeach; ?>
  </div>
</section>
<?php if ($canManage): ?>
<dialog class="finance-dialog" id="team-invite" aria-labelledby="team-invite-title">
  <form method="post" action="/minha-conta/equipe/convidar">
    <header><h2 id="team-invite-title">Convidar pessoa</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $token ?>
    <label>E-mail<input type="email" name="email" maxlength="255" required></label>
    <label>Papel<select name="role" required><?php foreach ($assignable as $key => $role): ?><option value="<?= $e($key) ?>"<?= $key === 'viewer' ? ' selected' : '' ?>><?= $e($role[0]) ?> · <?= $e($role[1]) ?></option><?php endforeach; ?></select></label>
    <p class="muted">O convite vale por 7 dias e só funciona na conta com este e-mail.</p>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Enviar convite</button></footer>
  </form>
</dialog>
<?php endif; ?>
