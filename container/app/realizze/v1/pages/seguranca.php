<?php
/**
 * Segurança da empresa ativa, com o que existe de verdade: 2FA e sessões da conta de cada pessoa
 * (configurados em Meu perfil) e as ações de acesso registradas na auditoria. Não há política por empresa.
 */
use Moves\Services\AppUrl;
use Moves\Services\OrganizationAudit;
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationTeam as Team;
use Moves\Services\SessionAuth;
use Moves\Services\TwoFactorAuth;
use Moves\Services\UserSessions;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$active = OrganizationContext::current();
$orgId = (int) ($active['id'] ?? 0);
$userId = (int) (SessionAuth::current()['id'] ?? 0);
$failed = false;
$own = ['enabled' => false];
$sessions = [];
$members = [];
$events = [];
$canManage = false;
try {
    $own = TwoFactorAuth::status($userId);
    $sessions = array_values(array_filter(UserSessions::listing($userId), static fn (array $s): bool => empty($s['revoked_at'])));
    $canManage = Team::can($userId, $orgId, 'team.manage');
    if ($canManage) {
        foreach (Team::members($orgId) as $member) {
            $member['two_factor'] = TwoFactorAuth::status((int) $member['user_id'])['enabled'];
            $members[] = $member;
        }
        foreach (['team.suspended', 'team.removed', 'team.role_changed', 'team.invite_accepted'] as $type) {
            $events = array_merge($events, OrganizationAudit::listing($orgId, ['tipo' => $type], 1, 5)['rows']);
        }
        usort($events, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);
        $events = array_slice($events, 0, 5);
    }
} catch (Throwable $exception) {
    error_log('Realizze security view: ' . $exception->getMessage());
    $failed = true;
}
$withFactor = count(array_filter($members, static fn (array $m): bool => $m['status'] === 'active' && $m['two_factor']));
$activeMembers = count(array_filter($members, static fn (array $m): bool => $m['status'] === 'active'));
$statusLabels = ['active' => 'Ativo', 'suspended' => 'Suspenso'];
?>
<?php if ($failed): ?>
<section class="card"><div class="finance-empty"><h3>Não foi possível carregar a segurança</h3><p>Tente novamente em instantes.</p></div></section>
<?php return; endif; ?>
<section class="card finance-workspace" aria-labelledby="security-own">
  <div class="finance-toolbar"><div><h2 id="security-own">Sua conta</h2><p class="muted">Senha, autenticação em dois fatores e sessões são da sua conta e valem para todas as empresas que você acessa.</p></div><a class="finance-button" href="<?= $e(AppUrl::personal('perfil') . '?aba=seguranca') ?>">Abrir segurança do perfil</a></div>
  <div class="finance-goal-summary">
    <div class="finance-goal-stat finance-indicator <?= $own['enabled'] ? 'is-realized' : 'is-overdue' ?>"><span>Autenticação em dois fatores</span><strong><?= $own['enabled'] ? 'Ativada' : 'Desativada' ?></strong><small><?= $own['enabled'] ? 'Código pedido a cada novo acesso' : 'Recomendada para quem acessa dados da empresa' ?></small></div>
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Sessões abertas</span><strong><?= count($sessions) ?></strong><small><a href="<?= $e(AppUrl::personal('perfil') . '?aba=sessoes') ?>">Ver dispositivos</a></small></div>
  </div>
</section>
<?php if ($canManage): ?>
<section class="card finance-workspace" aria-labelledby="security-team">
  <div class="finance-toolbar"><div><h2 id="security-team">Equipe</h2><p class="muted"><?= $withFactor ?> de <?= $activeMembers ?> pessoa<?= $activeMembers === 1 ? '' : 's' ?> com acesso ativo usa<?= $withFactor === 1 ? '' : 'm' ?> dois fatores. A empresa ainda não pode exigir 2FA dos membros.</p></div><a class="finance-button" href="<?= $e(AppUrl::section('equipe')) ?>">Gerenciar equipe</a></div>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Segurança da equipe">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Pessoa</th><th scope="col">Papel</th><th scope="col">Acesso</th><th scope="col">Dois fatores</th></tr></thead>
      <tbody>
      <?php foreach ($members as $member): ?>
      <tr>
        <td><strong><?= $e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></strong><small class="contact-legal"><?= $e($member['email']) ?></small></td>
        <td><?= $e($member['role']) ?></td>
        <td><span class="finance-badge is-<?= $member['status'] === 'active' ? 'paid' : 'overdue' ?>"><?= $e($statusLabels[$member['status']] ?? $member['status']) ?></span></td>
        <td><span class="finance-badge is-<?= $member['two_factor'] ? 'paid' : 'open' ?>"><?= $member['two_factor'] ? 'Ativada' : 'Desativada' ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<section class="card" aria-labelledby="security-events">
  <div class="profile-heading"><div><h2 id="security-events" class="section-title">Mudanças de acesso recentes</h2><p class="muted">Entradas, mudanças de papel, suspensões e remoções.</p></div><a class="finance-button" href="<?= $e(AppUrl::section('auditoria')) ?>">Ver auditoria</a></div>
  <?php if ($events === []): ?>
  <p class="muted">Nenhuma mudança de acesso registrada.</p>
  <?php else: ?>
  <ul class="profile-rows">
    <?php foreach ($events as $row): ?>
    <li><span><strong><?= $e(OrganizationAudit::EVENTS[$row['event_type']] ?? $row['event_type']) ?></strong><small><?= $e($row['actor_name'] ?: 'Conta removida') ?> · <?= $e((new DateTimeImmutable((string) $row['created_at']))->format('d/m/Y H:i')) ?></small></span></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
