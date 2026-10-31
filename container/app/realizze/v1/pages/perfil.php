<?php
/** Perfil do usuário: mostra dados reais; edição, foto e dois fatores aguardam o backend. */
use Moves\Services\SessionAuth;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$user = SessionAuth::current() ?? [];
$name = (string) ($user['first_name'] ?? '');
?>
<section class="profile-hero card">
  <span class="profile-photo" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
  <div><h2><?= $e($name) ?></h2><p class="muted"><?= $e($user['email'] ?? '') ?></p></div>
  <button type="button" disabled title="Envio de foto ainda não disponível">Alterar foto</button>
</section>
<div class="profile-grid">
  <section class="card" aria-labelledby="profile-data">
    <h2 id="profile-data" class="section-title">Dados pessoais</h2>
    <dl class="contact-details">
      <div><dt>Nome</dt><dd><?= $e($name) ?></dd></div>
      <div><dt>E-mail de acesso</dt><dd><?= $e($user['email'] ?? '') ?></dd></div>
    </dl>
    <p class="profile-pending">A edição dos dados ainda não está disponível.</p>
  </section>
  <section class="card" aria-labelledby="profile-security">
    <h2 id="profile-security" class="section-title">Segurança</h2>
    <ul class="profile-rows">
      <li><span><strong>Senha</strong><small>Troque pelo link enviado ao seu e-mail.</small></span><a class="finance-button" href="https://www.realizze.lab/recuperar-senha">Trocar senha</a></li>
      <li><span><strong>Verificação em duas etapas</strong><small>Ainda não disponível.</small></span><span class="finance-badge is-open">Em breve</span></li>
    </ul>
  </section>
</div>
