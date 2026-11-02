<?php
$this->layout('default', ['title' => 'Reenviar confirmação | Realizze', 'description' => 'Peça um novo link de confirmação do e-mail.']);
$this->start('optout');
$this->stop();
?>
<section class="auth-section">
<div class="auth-card">
<span class="eyebrow">CONFIRMAÇÃO DE E-MAIL</span>
<h1>Reenviar confirmação</h1>
<?php if (($requested ?? false) === true): ?>
<p class="form-feedback is-success" role="status">Se houver uma conta aguardando confirmação neste e-mail, enviamos um novo link. Ele vale por 24 horas.</p>
<?php else: ?>
<p>Informe o e-mail do cadastro para receber um novo link.</p>
<form class="demo-form" method="post" action="/reenviar-confirmacao">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<label for="resend-email">E-mail</label>
<input id="resend-email" name="email" type="email" autocomplete="email" maxlength="254" required placeholder="voce@exemplo.com">
<button class="btn btn-primary btn-full" type="submit">Enviar novo link</button>
</form>
<?php endif; ?>
<p class="auth-bottom"><a href="/entrar">Voltar para o login</a></p>
</div>
</section>
