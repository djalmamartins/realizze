<?php
/** Seletor de contexto no menu lateral: a troca passa pelo POST validado em /minha-conta/contexto. */
use Moves\Services\Csrf;
use Moves\Services\OrganizationAccess;
use Moves\Services\OrganizationContext;
use Moves\Services\PersonalAccess;
use Moves\Services\PersonalAccessContext;
use Moves\Services\SessionAuth;

if (!OrganizationContext::enabled()) {
    return;
}
$contextEscape = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
try {
    $active = OrganizationContext::current();
    $list = OrganizationAccess::accessibleForCurrentUser();
    $token = Csrf::token();
    $personalOwner = PersonalAccessContext::ownerId();
    $sharedPersonal = PersonalAccess::sharedWith((int) (SessionAuth::current()['id'] ?? 0));
} catch (Throwable $exception) {
    error_log('Realizze: seletor de contexto indisponível.');
    return;
}
?>
<details class="context-switch">
  <?php $sharedActive = $personalOwner ? array_values(array_filter($sharedPersonal, static fn(array $x):bool=>(int)$x['id']===$personalOwner))[0] ?? null : null; ?>
  <summary aria-label="Trocar perfil"><span class="context-avatar" aria-hidden="true"><?= $contextEscape(mb_strtoupper(mb_substr($active['name'] ?? ($sharedActive['name'] ?? 'PF'), 0, 2))) ?></span><span><small><?= $active ? 'Empresa' : ($sharedActive ? 'PF compartilhada' : 'Pessoal') ?></small><strong><?= $contextEscape($active['name'] ?? ($sharedActive['name'] ?? 'Minhas finanças')) ?></strong></span></summary>
  <div class="context-menu">
    <form method="post" action="/minha-conta/contexto"><input type="hidden" name="csrf_token" value="<?= $contextEscape($token) ?>"><input type="hidden" name="context_type" value="personal"><button type="submit"<?= $active === null && $personalOwner === null ? ' aria-current="true"' : '' ?>>Minhas finanças</button></form>
    <?php if ($sharedPersonal !== []): ?><small class="context-group">Contas pessoais compartilhadas</small><?php foreach ($sharedPersonal as $shared): ?><form method="post" action="/minha-conta/contexto"><input type="hidden" name="csrf_token" value="<?= $contextEscape($token) ?>"><input type="hidden" name="context_type" value="shared"><input type="hidden" name="owner_user_id" value="<?= (int)$shared['id'] ?>"><button type="submit"<?= $personalOwner === (int)$shared['id'] ? ' aria-current="true"' : '' ?>><?= $contextEscape($shared['name']) ?></button></form><?php endforeach; endif; ?>
    <?php
    $ownedOrganizations = array_values(array_filter($list, static fn (array $o): bool => ($o['role'] ?? '') === 'owner'));
    $sharedOrganizations = array_values(array_filter($list, static fn (array $o): bool => ($o['role'] ?? '') !== 'owner'));
    foreach ([['Minhas empresas', $ownedOrganizations], ['Empresas compartilhadas', $sharedOrganizations]] as [$groupLabel, $groupList]):
        if ($groupList === []) continue; ?>
    <small class="context-group"><?= $contextEscape($groupLabel) ?></small>
    <?php foreach ($groupList as $organization): ?>
    <form method="post" action="/minha-conta/contexto"><input type="hidden" name="csrf_token" value="<?= $contextEscape($token) ?>"><input type="hidden" name="context_type" value="organization"><input type="hidden" name="organization_id" value="<?= (int) $organization['id'] ?>"><button type="submit"<?= (int) ($active['id'] ?? 0) === (int) $organization['id'] ? ' aria-current="true"' : '' ?>><?= $contextEscape($organization['name']) ?></button></form>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <button type="button" class="context-add" data-finance-open="company-dialog">+ Adicionar empresa</button>
  </div>
</details>
