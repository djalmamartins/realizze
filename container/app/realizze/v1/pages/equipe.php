<?php
/** Membros reais da empresa ativa. Convites e alterações aguardam o backend. */
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationDirectory;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$active = OrganizationContext::current();
$members = $active ? OrganizationDirectory::members((int) $active['id']) : [];
?>
<section class="card finance-workspace" aria-labelledby="team-title">
  <div class="finance-toolbar">
    <div><h2 id="team-title">Equipe e permissões</h2><p class="muted">Quem acessa os dados de <?= $e($active['name'] ?? 'esta empresa') ?>.</p></div>
    <button type="button" class="primary" disabled title="Convites ainda não disponíveis">+ Convidar pessoa</button>
  </div>
  <?php if ($members === []): ?>
  <div class="finance-empty"><h3>Nenhum membro encontrado</h3><p>Selecione uma empresa no menu.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Membros da equipe">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Perfil</th><th scope="col">Situação</th><th scope="col">Desde</th></tr></thead>
      <tbody>
      <?php foreach ($members as $member): ?>
      <tr><td><strong><?= $e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></strong></td><td><?= $e($member['email']) ?></td><td><?= $e($member['role']) ?></td><td><span class="finance-badge is-<?= $member['status'] === 'active' ? 'paid' : 'overdue' ?>"><?= $member['status'] === 'active' ? 'Ativo' : 'Suspenso' ?></span></td><td><?= $e((new DateTimeImmutable($member['joined_at']))->format('d/m/Y')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="profile-pending team-pending">Convites, troca de perfil e remoção de acesso ainda não estão disponíveis.</p>
</section>
