<?php
declare(strict_types=1);
/** Layout administrativo do CMS. Recebe dados compartilhados pelas rotas. */
use Moves\Support\CmsView as V;

$css = BASE_PATH . '/public/assets/realizze-cms/v1/css/cms.min.css';
$js = BASE_PATH . '/public/assets/realizze-cms/v1/js/cms.min.js';
$subtitles = [
    'pages' => 'Crie, publique e acompanhe as páginas do site.', 'contents' => 'Artigos, notícias, ajuda e conteúdos institucionais.',
    'media' => 'Imagens e documentos usados no site.', 'menus' => 'Navegação do cabeçalho e do rodapé.', 'seo' => 'Títulos, descrições e indexação de cada rota.',
    'users' => 'Contas da plataforma, sem dados financeiros privados.', 'companies' => 'Empresas cadastradas, responsáveis e membros.', 'plans' => 'Ofertas, preços e histórico de alterações.',
    'subscriptions' => 'Contratos, renovações e histórico.', 'charges' => 'Cobranças da assinatura Realizze.', 'tickets' => 'Todos os atendimentos em um só lugar.', 'queue' => 'O que precisa de atenção, por prioridade.',
    'ticket-categories' => 'Como os chamados são classificados.', 'quick-replies' => 'Textos prontos para responder mais rápido.', 'ticket-team' => 'Quem atende e quanto há em aberto.', 'ticket-reports' => 'Tempos, volumes e avaliações.',
    'ticket-notifications' => 'Canais de aviso e metas de atendimento.', 'admins' => 'Quem acessa o CMS e com qual papel.', 'permissions' => 'O que cada papel pode fazer.', 'audit' => 'Registro das ações administrativas.', 'settings' => 'Dados institucionais e preferências.', 'reports' => 'Desempenho do site, sem identificar visitantes.', 'broadcasts' => 'Envie avisos para os usuários e acompanhe as entregas.', 'slides' => 'Imagens e chamadas principais do site.', 'testimonials' => 'Relatos e avaliações exibidos no site.', 'knowledge' => 'Tutoriais usados como ajuda no App e no CMS.', 'versions' => 'Histórico independente de cada produto.', 'system-logs' => 'Falhas técnicas registradas pelo sistema.',
];
$unread = isset($admin) ? \Moves\Services\HelpdeskNotifier::unread((int) $admin['id'], 'staff') : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= V::e($title ?? 'CMS') ?> | Realizze CMS</title>
<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg">
<script>try{if(localStorage.getItem('cms-theme')==='dark')document.documentElement.dataset.theme='dark'}catch(e){}</script>
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
  <button type="button" class="cms-top-icon" id="cms-theme" title="Alternar modo escuro" aria-label="Alternar modo escuro" aria-pressed="false"><?= V::icon('moon') ?></button>
  <a class="cms-top-icon" href="/ajuda?q=<?= V::e(rawurlencode((string) ($title ?? ''))) ?>" title="Ajuda" aria-label="Ajuda"><?= V::icon('help') ?></a>
  <a class="cms-top-icon" href="<?= V::e(getenv('APP_URL') ?: '/') ?>" target="_blank" rel="noopener" title="Visualizar site" aria-label="Visualizar site"><?= V::icon('globe') ?></a>
  <a class="cms-bell" href="/notificacoes" aria-label="Notificações: <?= (int) $unread ?> não lidas"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 0 0 4 0"/></svg><?php if ($unread > 0): ?><b><?= (int) $unread ?></b><?php endif; ?></a>
  <details class="cms-profile"><summary><span><?= V::e(mb_substr((string) ($admin['first_name'] ?? 'A'), 0, 1)) ?></span><?= V::e($admin['first_name'] ?? '') ?></summary><div><strong><?= V::e(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?></strong><small><?= V::e($admin['role_name'] ?? '') ?></small><small><?= V::e($admin['email'] ?? '') ?></small><form method="post" action="/sair"><?= V::csrf() ?><button class="cms-btn" type="submit">Sair</button></form></div></details>
 </div>
</header>
<main class="cms-content" id="cms-content" tabindex="-1">
<header class="cms-page-head"><div><?php if (($group ?? '') !== ''): ?><span class="cms-eyebrow"><?= V::e($group) ?></span><?php endif; ?><h1 id="cms-page-title"><?= V::e($title ?? '') ?></h1><?php if (isset($subtitles[$section ?? '']) && !isset($id)): ?><p><?= V::e($subtitles[$section]) ?></p><?php endif; ?></div><?php $pageActions = $this->section('actions', ''); if ($pageActions !== ''): ?><div class="cms-page-actions"><?= $pageActions ?></div><?php endif; ?></header>
<?php if (!empty($flash)): ?><div class="cms-alert cms-alert-<?= $flash[0] === 'success' ? 'ok' : 'bad' ?>" role="<?= $flash[0] === 'success' ? 'status' : 'alert' ?>"><?= V::e($flash[1]) ?></div><?php endif; ?>
<?= $this->section('content', '') ?>
</main>
<footer class="cms-footer">Copyright © <?= date('Y') ?> Realizze. Todos os direitos reservados.</footer>
<a class="cms-help" href="/ajuda"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.7M12 17h.01"/></svg>Ajuda</a>
</div></div>
<script src="/assets/realizze-cms/v1/js/cms.min.js?v=<?= (int) @filemtime($js) ?>" defer></script>
</body></html>
