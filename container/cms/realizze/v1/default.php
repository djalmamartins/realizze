<?php
declare(strict_types=1);
/** Layout administrativo do CMS. Recebe dados compartilhados pelas rotas. */
use Moves\Support\CmsView as V;

$css = BASE_PATH . '/public/assets/realizze-cms/v1/css/cms.min.css';
$js = BASE_PATH . '/public/assets/realizze-cms/v1/js/cms.min.js';
$unread = isset($admin) ? \Moves\Services\HelpdeskNotifier::unread((int) $admin['id'], 'staff') : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= V::e($title ?? 'CMS') ?> | Realizze CMS</title>
<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg">
<link rel="stylesheet" href="/assets/realizze-cms/v1/css/cms.min.css?v=<?= (int) @filemtime($css) ?>">
</head>
<body>
<a class="cms-skip-link" href="#cms-content">Ir para o conteúdo principal</a>
<div class="cms-shell">
<?php $this->insert('components/sidebar'); ?>
<div class="cms-main">
<header class="cms-topbar">
 <nav class="cms-breadcrumbs" aria-label="Trilha"><a href="/">Início</a><?php if (($group ?? '') !== ''): ?><span aria-hidden="true">/</span><span><?= V::e($group) ?></span><?php endif; ?><span aria-hidden="true">/</span><span aria-current="page"><?= V::e($title ?? '') ?></span></nav>
 <div class="cms-topbar-actions">
  <a class="cms-bell" href="/notificacoes" aria-label="Notificações: <?= (int) $unread ?> não lidas"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 0 0 4 0"/></svg><?php if ($unread > 0): ?><b><?= (int) $unread ?></b><?php endif; ?></a>
  <details class="cms-profile"><summary><span><?= V::e(mb_substr((string) ($admin['first_name'] ?? 'A'), 0, 1)) ?></span><?= V::e($admin['first_name'] ?? '') ?></summary><div><strong><?= V::e(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?></strong><small><?= V::e($admin['role_name'] ?? '') ?></small><small><?= V::e($admin['email'] ?? '') ?></small><form method="post" action="/sair"><?= V::csrf() ?><button class="cms-btn" type="submit">Sair</button></form></div></details>
 </div>
</header>
<main class="cms-content" id="cms-content" tabindex="-1">
<h1 id="cms-page-title"><?= V::e($title ?? '') ?></h1>
<?php if (!empty($flash)): ?><div class="cms-alert cms-alert-<?= $flash[0] === 'success' ? 'ok' : 'bad' ?>" role="<?= $flash[0] === 'success' ? 'status' : 'alert' ?>"><?= V::e($flash[1]) ?></div><?php endif; ?>
<?= $this->section('content', '') ?>
</main>
<footer class="cms-footer">Realizze CMS</footer>
</div></div>
<script src="/assets/realizze-cms/v1/js/cms.min.js?v=<?= (int) @filemtime($js) ?>" defer></script>
</body></html>
