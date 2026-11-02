<?php
$confirmed = $confirmed ?? null;
$token = $token ?? '';
$this->layout('default', ['title' => 'Confirmar e-mail | Realizze', 'description' => 'Confirmação de e-mail da conta Realizze.']);
$this->start('optout');
$this->stop();
?>
<section class="auth-section">
<div class="auth-card">
<span class="eyebrow">CONFIRMAÇÃO DE E-MAIL</span>
<?php if ($confirmed === true): ?>
<h1>E-mail confirmado!</h1>
<p>Sua conta foi ativada com sucesso.</p>
<a class="btn btn-primary btn-full" href="/entrar">Ir para o login</a>
<?php elseif ($confirmed === false || $token === ''): ?>
<h1>Não foi possível confirmar o e-mail</h1>
<p>O link é inválido, expirou ou já foi utilizado.</p>
<a class="btn btn-primary btn-full" href="/reenviar-confirmacao">Pedir um novo link</a>
<p class="auth-bottom"><a href="/entrar">Voltar para o login</a></p>
<?php else: ?>
<h1>Confirme seu e-mail</h1>
<p>Clique no botão abaixo para ativar sua conta.</p>
<form class="demo-form" method="post" action="/confirmar-email">
<input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<button class="btn btn-primary btn-full" type="submit">Confirmar meu e-mail</button>
</form>
<?php endif; ?>
</div>
</section>
