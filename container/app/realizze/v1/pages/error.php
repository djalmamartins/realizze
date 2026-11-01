<?php
declare(strict_types=1);
$status = in_array((int) ($status ?? 500), [403, 404, 500], true) ? (int) $status : 500;
$messages = [
    403 => ['Acesso não autorizado', 'Você não possui permissão para acessar esta área.'],
    404 => ['Página não encontrada', 'O endereço pode ter mudado ou não existe no aplicativo.'],
    500 => ['Não foi possível concluir', 'Ocorreu um erro interno. Tente novamente em alguns instantes.'],
];
[$title, $description] = $messages[$status];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg"><title><?= $status ?> · <?= htmlspecialchars($title) ?> | Realizze</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f7f5;color:#1f2937;font:16px/1.5 system-ui,-apple-system,sans-serif}.error-card{width:min(560px,100%);padding:44px;border:1px solid #dfe7e2;border-radius:22px;background:#fff;box-shadow:0 18px 55px #1232;text-align:center}.error-card img{width:154px;height:auto}.error-code{display:block;margin:28px 0 4px;color:#078b4c;font-size:14px;font-weight:800;letter-spacing:.12em}.error-card h1{margin:0;font-size:clamp(26px,5vw,38px)}.error-card p{color:#6b7280}.error-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:28px}.error-actions a,.error-actions button{border:1px solid #cad7cf;border-radius:10px;padding:11px 17px;background:#fff;color:#145c3a;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.error-actions .primary{border-color:#078b4c;background:#078b4c;color:#fff}
</style></head><body><main class="error-card"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze"><span class="error-code">ERRO <?= $status ?></span><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($description) ?></p><div class="error-actions"><a class="primary" href="/p">Ir ao início do App</a><button type="button" onclick="history.length>1?history.back():location.assign('/p')">Voltar</button><a href="/suporte">Falar com o suporte</a></div></main></body></html>
