<?php
$changed = $changed ?? null;
$pending = $pending ?? null;
$token = $token ?? '';
$this->layout('default', ['title' => 'Confirmar novo e-mail | Realizze', 'description' => 'Confirmação da troca de e-mail da conta Realizze.']);
$this->start('optout');
$this->stop();
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="auth-section">
<div class="auth-card">
<span class="eyebrow">TROCA DE E-MAIL</span>
<?php if ($changed === true): ?>
<h1>E-mail alterado</h1>
<p>Use o novo endereço para entrar. Por segurança, as sessões abertas foram encerradas.</p>
<a class="btn btn-primary btn-full" href="/entrar">Ir para o login</a>
<?php elseif ($changed === false || $pending === null): ?>
<h1>Não foi possível trocar o e-mail</h1>
<p>O link é inválido, expirou, já foi usado ou o endereço passou a pertencer a outra conta.</p>
<p class="auth-bottom"><a href="/entrar">Voltar para o login</a></p>
<?php else: ?>
<h1>Confirme o novo e-mail</h1>
<p>O e-mail de acesso passará a ser <strong><?= $e($pending['new_email']) ?></strong>.</p>
<form class="demo-form" method="post" action="/confirmar-novo-email">
<input type="hidden" name="token" value="<?= $e($token) ?>">
<input type="hidden" name="csrf_token" value="<?= $e(\Moves\Services\Csrf::token()) ?>">
<button class="btn btn-primary btn-full" type="submit">Confirmar novo e-mail</button>
</form>
<?php endif; ?>
</div>
</section>
