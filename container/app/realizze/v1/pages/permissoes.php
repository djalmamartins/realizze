<?php
/** O que cada papel pode fazer. Leitura direta do catálogo usado na autorização. */
use Moves\Services\OrganizationTeam as Team;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="card finance-workspace" aria-labelledby="permissions-title">
  <div class="finance-toolbar"><div><h2 id="permissions-title">Permissões por papel</h2><p class="muted">Cada empresa tem seus próprios membros. Altere o papel de uma pessoa em Equipe.</p></div></div>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Permissões por papel">
    <table class="finance-table finance-entries permission-table">
      <thead><tr><th scope="col">Permissão</th><th scope="col">Proprietário</th><?php foreach (Team::ROLES as $role): ?><th scope="col"><?= $e($role[0]) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (Team::PERMISSIONS as $key => [$group, $label]): ?>
      <tr><td><strong><?= $e($label) ?></strong><small class="contact-legal"><?= $e($group) ?></small></td><td><span aria-label="Sim">✓</span></td><?php foreach (Team::ROLES as $role): $has = in_array($key, $role[2], true); ?><td><span aria-label="<?= $has ? 'Sim' : 'Não' ?>"><?= $has ? '✓' : '—' ?></span></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
