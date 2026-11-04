<?php
/** Aceite do convite de empresa. O token nunca aparece no HTML: fica na sessão desde a abertura do link. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$publishedCss = BASE_PATH . '/public/assets/realizze-app/v1/css/dashboard.min.css';
$user = \Moves\Services\SessionAuth::current();
$matches = $user !== null && $invitation !== null && hash_equals((string) $invitation['email_hash'], hash('sha256', mb_strtolower(trim((string) $user['email']))));
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Convite | Realizze</title>
<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg"><link rel="stylesheet" href="/assets/realizze-app/v1/css/dashboard.min.css?v=<?= is_file($publishedCss) ? (int) filemtime($publishedCss) : 0 ?>"></head>
<body class="app-error-body team-invite-page"><main class="card team-invite-card">
<img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="152" height="38">
<?php if ($invitation === null): ?>
  <h1>Convite indisponível</h1><p class="muted">Este convite expirou, foi cancelado ou já foi usado. Peça um novo a quem convidou você.</p><a class="primary team-invite-link" href="/">Ir para o Realizze</a>
<?php elseif ($user === null): ?>
  <h1>Você foi convidado</h1><p>Entre com a conta do e-mail <strong><?= $e($invitation['email']) ?></strong>. Depois de entrar, você volta para este convite e aceita o acesso a <strong><?= $e($invitation['organization']) ?></strong>.</p><p class="muted">Se ainda não tem conta, crie uma com esse mesmo e-mail.</p><a class="primary team-invite-link" href="/entrar">Entrar</a>
<?php elseif (!$matches): ?>
  <h1>Convite de outra conta</h1><p>Este convite foi enviado para outro e-mail. Saia e entre com a conta certa para aceitar.</p><a class="primary team-invite-link" href="/p">Voltar ao Realizze</a>
<?php else: ?>
  <h1>Convite para <?= $e($invitation['organization']) ?></h1><p>Você entrará como <strong><?= $e($invitation['role']) ?></strong>. Suas finanças pessoais continuam separadas.</p>
  <div class="team-invite-actions">
    <form method="post" action="/convite-empresa/recusar"><input type="hidden" name="csrf_token" value="<?= $e(\Moves\Services\Csrf::token()) ?>"><button type="submit">Recusar</button></form>
    <form method="post" action="/convite-empresa/aceitar"><input type="hidden" name="csrf_token" value="<?= $e(\Moves\Services\Csrf::token()) ?>"><button type="submit" class="primary">Aceitar convite</button></form>
  </div>
<?php endif; ?>
</main></body></html>
