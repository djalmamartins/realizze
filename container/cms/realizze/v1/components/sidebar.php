<?php
/** Navegação do CMS: cada item só aparece com a permissão exigida pela rota. */
use Moves\Services\CmsAuth;
use Moves\Support\CmsView as V;

require __DIR__ . '/icons.php';
$menu = [
    'Visão geral' => [['dashboard', '/', 'Dashboard', 'overview', 'dashboard.view']],
    'Site' => [
        ['pages', '/paginas', 'Páginas', 'pages', 'pages.manage'], ['contents', '/conteudos', 'Conteúdos', 'edit', 'content.manage'],
        ['media', '/midia', 'Mídia', 'media', 'media.manage'], ['menus', '/menus', 'Menus', 'menu', 'menus.manage'], ['seo', '/seo', 'SEO', 'search', 'seo.manage'],
    ],
    'Plataforma' => [
        ['users', '/usuarios', 'Usuários', 'users', 'users.view'], ['companies', '/empresas', 'Empresas', 'building', 'companies.view'],
        ['plans', '/planos', 'Planos e preços', 'tag', 'plans.view'], ['subscriptions', '/assinaturas', 'Assinaturas', 'repeat', 'subscriptions.view'], ['charges', '/cobrancas', 'Cobranças', 'card', 'charges.view'],
    ],
    'Atendimento' => [
        ['tickets', '/chamados', 'Chamados', 'chat', 'tickets.view'], ['queue', '/chamados/fila', 'Fila de atendimento', 'clock', 'tickets.view'],
        ['ticket-categories', '/chamados/categorias', 'Categorias', 'list', 'tickets.categories'], ['ticket-team', '/chamados/equipe', 'Equipe', 'users', 'tickets.view'],
        ['ticket-reports', '/chamados/relatorios', 'Relatórios de atendimento', 'chart', 'tickets.reports'], ['ticket-notifications', '/chamados/notificacoes', 'Configurações de notificações', 'bell', 'notifications.configure'],
    ],
    'Administração' => [
        ['admins', '/administradores', 'Administradores', 'shield', 'admins.manage'], ['permissions', '/permissoes', 'Permissões', 'key', 'permissions.manage'],
        ['audit', '/auditoria', 'Auditoria', 'clock', 'audit.view'], ['settings', '/configuracoes', 'Configurações', 'settings', 'settings.manage'],
    ],
];
?>
<aside class="cms-sidebar" id="cms-sidebar" aria-label="Navegação do CMS">
 <div class="cms-brand-row">
  <a class="cms-brand" href="/" aria-label="Realizze CMS, dashboard"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="138" height="35"><small>CMS</small></a>
  <button type="button" class="cms-menu-toggle" id="cms-menu-toggle" aria-label="Abrir navegação" aria-controls="cms-navigation" aria-expanded="false"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
 </div>
 <nav class="cms-nav" id="cms-navigation" aria-label="Módulos">
 <?php foreach ($menu as $name => $items):
    $visible = array_filter($items, static fn (array $item): bool => CmsAuth::can($admin ?? null, $item[4]));
    if ($visible === []) { continue; }
    $isOpen = ($group ?? '') === $name || $name === 'Visão geral'; ?>
  <details class="cms-nav-group"<?= $isOpen ? ' open' : '' ?>><summary><?= V::e($name) ?></summary>
  <?php foreach ($visible as [$key, $href, $label, $symbol]): ?>
   <a href="<?= V::e($href) ?>"<?= $key === ($section ?? '') ? ' class="selected" aria-current="page"' : '' ?>><?= $cmsIcon($symbol) ?><span><?= V::e($label) ?></span></a>
  <?php endforeach; ?>
  </details>
 <?php endforeach; ?>
 </nav>
</aside>
