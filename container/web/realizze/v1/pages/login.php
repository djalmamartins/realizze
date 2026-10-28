<?php
$this->layout('default', ['title' => 'Entrar | Realizze', 'description' => 'Área de acesso do Realizze.']);
$this->start('optout');
$this->stop();
?>
<section class="auth-section">
<div class="auth-card">
<span class="eyebrow">BEM-VINDO DE VOLTA</span>
<h1>Acesse sua conta</h1>
<p>Entre para continuar sua experiência.</p>
<?php if (($loginError ?? false) === true): ?>
<p class="form-feedback" role="alert">Não foi possível entrar. Confira suas credenciais e a confirmação do e-mail.</p>
<?php endif; ?>
<form class="demo-form" method="post" action="/entrar">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<label for="login-email">E-mail</label>
<input id="login-email" name="email" type="email" autocomplete="email" maxlength="254" required placeholder="voce@exemplo.com">
<label for="login-password">Senha</label>
<input id="login-password" name="password" type="password" autocomplete="current-password" required placeholder="Sua senha">
<div class="form-side"><a href="/recuperar-senha">Esqueceu sua senha?</a></div>
<button class="btn btn-primary btn-full" type="submit">Entrar →</button>
</form>
<p class="auth-bottom">Ainda não tem conta? <a href="/cadastrar">Cadastre-se</a></p>
</div>
</section>
