<?php
$confirmed = $confirmed ?? null;
$token = $token ?? '';
?>

<main style="max-width:560px;margin:80px auto;padding:24px;text-align:center">
    <?php if ($confirmed === true): ?>
        <h1>E-mail confirmado!</h1>
        <p>Sua conta foi ativada com sucesso.</p>
        <a href="/entrar">Ir para o login</a>

    <?php elseif ($confirmed === false || $token === ''): ?>
        <h1>Não foi possível confirmar o e-mail</h1>
        <p>O link é inválido, expirou ou já foi utilizado.</p>
        <a href="/entrar">Voltar para o login</a>

    <?php else: ?>
        <h1>Confirme seu e-mail</h1>
        <p>Clique no botão abaixo para ativar sua conta.</p>

        <form method="post" action="/confirmar-email">
            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
            ><input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        \Moves\Services\Csrf::token(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
            >
            <button type="submit">Confirmar meu e-mail</button>
        </form>
    <?php endif; ?>
</main>
