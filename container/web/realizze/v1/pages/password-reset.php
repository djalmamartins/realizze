<?php $this->layout('default', ['title' => 'Redefinir senha | Realizze']); $this->start('optout'); $this->stop(); ?>
<section class="auth-section"><div class="auth-card">
<h1>Redefinir senha</h1>
<?php if ($resetDone ?? false): ?>
<p role="status">Senha atualizada. Você já pode entrar.</p><p><a href="/entrar">Ir para o login</a></p>
<?php elseif (!($tokenValid ?? false)): ?>
<p role="alert">Este link é inválido ou expirou.</p><p><a href="/recuperar-senha">Solicitar outro link</a></p>
<?php else: ?>
<?php if ($resetError ?? false): ?><p role="alert">Não foi possível redefinir a senha. Confira os dados e tente novamente.</p><?php endif; ?>
<form method="post" action="/redefinir-senha">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">
<label for="new-password">Nova senha (mínimo 12 caracteres)</label>
<input id="new-password" name="password" type="password" minlength="12" autocomplete="new-password" required>
<label for="confirm-password">Confirmar senha</label>
<input id="confirm-password" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
<button class="btn btn-primary btn-full" type="submit">Salvar nova senha</button>
</form>
<?php endif; ?>
</div></section>
