<?php use Moves\Support\CmsView as V; ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Entrar | Realizze CMS</title>
<link rel="stylesheet" href="/assets/realizze-cms/v1/css/cms.min.css?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/realizze-cms/v1/css/cms.min.css') ?>"></head>
<body><main class="cms-login"><form class="cms-login-card" method="post" action="/entrar"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="150" height="38"><h1>Entrar no CMS</h1>
<?php if ($loginError ?? false): ?><div class="cms-alert cms-alert-bad" role="alert">E-mail ou senha incorretos, ou conta sem acesso administrativo.</div><?php endif; ?>
<?= V::csrf() ?><?= V::field('E-mail', 'email', '', ['type' => 'email', 'required' => true]) ?><?= V::field('Senha', 'password', '', ['type' => 'password', 'required' => true]) ?>
<button class="cms-btn-primary" type="submit">Entrar</button></form></main></body></html>
