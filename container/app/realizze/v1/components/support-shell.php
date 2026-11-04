<?php
/** Moldura das telas de suporte. Use $supportShell('início'|'fim', título). */
use Moves\Support\CmsView as V;

$supportCss = BASE_PATH . '/public/assets/realizze-app/v1/css/support.min.css';
$supportJs = BASE_PATH . '/public/assets/realizze-app/v1/js/support.min.js';
$supportFlash = $_SESSION['support_flash'] ?? null;
unset($_SESSION['support_flash']);
header('Cache-Control: no-store');
$supportOpen = static function (string $title) use ($supportCss, $supportFlash): void { ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title><?= V::e($title) ?> | Suporte Realizze</title><link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon-realizze.svg"><link rel="stylesheet" href="/assets/realizze-app/v1/css/support.min.css?v=<?= (int) @filemtime($supportCss) ?>"></head>
<body class="sp-page"><header class="sp-top"><a href="/minha-conta" aria-label="Voltar ao Realizze"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="130" height="33"></a><nav aria-label="Suporte"><a href="/suporte">Meus chamados</a><a href="/suporte/ajuda">Ajuda</a><a href="/suporte/notificacoes">Notificações</a><a href="/suporte/atualizacoes">Atualizações</a><a href="/minha-assinatura">Assinatura</a><a class="sp-btn sp-btn-primary" href="/suporte/novo">Abrir chamado</a><a href="/minha-conta">Voltar ao app</a></nav></header><main class="sp-main"><h1><?= V::e($title) ?></h1><?php if (is_array($supportFlash)): ?><div class="sp-alert sp-alert-<?= $supportFlash[0] === 'success' ? 'ok' : 'bad' ?>" role="<?= $supportFlash[0] === 'success' ? 'status' : 'alert' ?>"><?= V::e($supportFlash[1]) ?></div><?php endif;
};
$supportClose = static function (): void { ?></main></body></html><?php };
