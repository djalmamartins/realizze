<?php
/** Perfil do usuário: dados reais; alterações, foto e dois fatores aguardam o backend e ficam indisponíveis. */
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationDirectory;
use Moves\Services\SessionAuth;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$session = SessionAuth::current() ?? [];
$data = null;
try {
    $data = OrganizationDirectory::user((int) ($session['id'] ?? 0));
    $organization = OrganizationContext::current();
} catch (Throwable $exception) {
    error_log('Realizze perfil: ' . $exception->getMessage());
    $organization = null;
}
$first = (string) ($data['first_name'] ?? $session['first_name'] ?? '');
$last = (string) ($data['last_name'] ?? '');
$email = (string) ($data['email'] ?? $session['email'] ?? '');
$since = !empty($data['created_at']) ? (new DateTimeImmutable($data['created_at']))->format('d/m/Y') : 'Não informado';
$active = ($data['status'] ?? 'active') === 'active';
$roles = ['owner' => 'Proprietário'];
$role = $organization ? ($roles[$organization['role'] ?? ''] ?? (string) ($organization['role'] ?? 'Membro')) : 'Conta pessoal';
?>
<section class="card user-hero">
  <div class="user-photo"><span aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1))) ?></span><button type="button" disabled title="Envio de foto ainda não disponível" aria-label="Alterar foto, indisponível"><?= $icon('settings') ?></button></div>
  <div class="user-identity">
    <h2><?= $e(trim($first . ' ' . $last)) ?> <span class="finance-badge is-<?= $active ? 'paid' : 'overdue' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span></h2>
    <p class="muted"><?= $e($email) ?></p>
    <p class="user-meta"><span class="finance-badge is-open"><?= $e($role) ?></span> <span class="muted">Membro desde <?= $e($since) ?></span></p>
  </div>
  <a class="finance-button" href="https://www.realizze.lab/recuperar-senha">Alterar senha</a>
</section>
<div class="user-tabs" data-tabs>
  <div class="user-tablist" role="tablist" aria-label="Seções do perfil">
    <?php foreach (['dados' => 'Dados gerais', 'seguranca' => 'Segurança', 'permissoes' => 'Permissões', 'notificacoes' => 'Notificações', 'atividade' => 'Logs de atividade'] as $key => $label): ?>
    <button type="button" role="tab" data-tab="<?= $key ?>" aria-selected="<?= $key === 'dados' ? 'true' : 'false' ?>"><?= $e($label) ?></button>
    <?php endforeach; ?>
  </div>
  <div data-tab-panel="dados" class="user-grid">
    <section class="card" aria-labelledby="user-info">
      <h2 id="user-info" class="section-title">Informações pessoais</h2>
      <form class="user-form" aria-describedby="user-pending">
        <label>Nome<input value="<?= $e($first) ?>" disabled></label>
        <label>Sobrenome<input value="<?= $e($last) ?>" disabled></label>
        <label>E-mail de acesso<input type="email" value="<?= $e($email) ?>" disabled></label>
        <label>Telefone<input placeholder="Não informado" disabled></label>
        <label>Idioma<select disabled><option>Português (Brasil)</option></select></label>
        <label>Fuso horário<select disabled><option>America/Sao_Paulo</option></select></label>
        <label class="is-wide">Biografia<textarea rows="3" placeholder="Não informada" disabled></textarea></label>
      </form>
      <p class="profile-pending" id="user-pending">A edição dos dados ainda não está disponível.</p>
    </section>
    <div class="user-side">
      <section class="card" aria-labelledby="user-photo-title">
        <h2 id="user-photo-title" class="section-title">Foto do usuário</h2>
        <div class="user-drop" aria-disabled="true"><strong>Envio de foto em breve</strong><small>PNG ou JPG, até 2 MB</small></div>
      </section>
      <section class="card" aria-labelledby="user-cred">
        <h2 id="user-cred" class="section-title">Credenciais</h2>
        <ul class="profile-rows">
          <li><span><strong>Senha</strong><small>Troque pelo link enviado ao seu e-mail.</small></span><a class="finance-button" href="https://www.realizze.lab/recuperar-senha">Alterar</a></li>
          <li><span><strong>Autenticação em dois fatores</strong><small>Ainda não disponível.</small></span><span class="finance-badge is-open">Em breve</span></li>
        </ul>
      </section>
    </div>
  </div>
  <section class="card" data-tab-panel="seguranca" hidden aria-label="Segurança">
    <h2 class="section-title">Sessões e dispositivos</h2>
    <ul class="profile-rows"><li><span><strong>Esta sessão</strong><small>Conectado agora neste navegador.</small></span><span class="finance-badge is-paid">Atual</span></li></ul>
    <p class="profile-pending">A lista de outros dispositivos e o encerramento de sessões ainda não estão disponíveis.</p>
  </section>
  <section class="card" data-tab-panel="permissoes" hidden aria-label="Permissões">
    <h2 class="section-title">Permissões</h2>
    <ul class="profile-rows"><li><span><strong><?= $e($organization['name'] ?? 'Minhas finanças') ?></strong><small>Perfil neste contexto</small></span><span class="finance-badge is-open"><?= $e($role) ?></span></li></ul>
  </section>
  <section class="card" data-tab-panel="notificacoes" hidden aria-label="Notificações"><h2 class="section-title">Notificações</h2><p class="profile-pending">As preferências de notificação ainda não estão disponíveis.</p></section>
  <section class="card" data-tab-panel="atividade" hidden aria-label="Logs de atividade"><h2 class="section-title">Logs de atividade</h2><p class="profile-pending">O histórico de atividade ainda não está disponível.</p></section>
</div>
