<?php
$this->layout('default', ['title' => 'Verificação em duas etapas | Realizze', 'description' => 'Confirme o segundo fator para acessar sua conta.']);
$this->start('optout'); $this->stop();
?>
<section class="auth-section"><div class="auth-card">
<span class="eyebrow">PROTEÇÃO DA CONTA</span><h1>Confirme seu acesso</h1>
<p>Digite o código de 6 dígitos do seu autenticador ou um código de recuperação.</p>
<?php if (($twoFactorError ?? false) === true): ?><p class="form-feedback" role="alert">Código inválido, expirado ou temporariamente bloqueado.</p><?php endif; ?>
<form class="demo-form" method="post" action="/entrar/2fa">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<label for="login-2fa-code">Código de verificação</label>
<input id="login-2fa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus>
<button class="btn btn-primary btn-full" type="submit">Verificar e entrar</button>
</form><p class="auth-bottom"><a href="/entrar">Voltar ao login</a></p>
</div></section>
