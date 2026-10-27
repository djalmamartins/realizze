<?php $this->layout('default', ['title'=>'Recuperar senha | Realizze']); $this->start('optout'); $this->stop(); ?>
<section class="auth-section"><div class="auth-card"><h1>Recuperar senha</h1>
<?php if ($requested ?? false): ?>
<p role="status">Se o e-mail estiver cadastrado e ativo, você receberá instruções para redefinir sua senha.</p>
<?php else: ?>
<form method="post" action="/recuperar-senha">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<label for="forgot-email">E-mail</label><input id="forgot-email" name="email" type="email" autocomplete="email" required maxlength="254">
<button class="btn btn-primary btn-full" type="submit">Solicitar recuperação</button></form>
<?php endif; ?><p><a href="/entrar">Voltar ao login</a></p></div></section>
