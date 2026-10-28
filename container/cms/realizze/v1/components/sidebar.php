<?php
/** Navegação da prévia local do CMS: links GET, sem ações administrativas. */
$cmsMenu = [
    'Gerenciamento' => [
        ['overview', 'Visão geral', 'overview'],
        ['pages', 'Páginas', 'pages'],
        ['contents', 'Conteúdos', 'edit'],
        ['media', 'Mídia', 'media'],
    ],
    'Plataforma' => [
        ['users', 'Usuários', 'users'],
        ['plans', 'Planos e assinaturas', 'card'],
        ['logs', 'Logs e auditoria', 'clock'],
        ['settings', 'Configurações', 'settings'],
    ],
];
$cmsPaths = [
    'overview' => '/', 'pages' => '/paginas', 'contents' => '/conteudos',
    'media' => '/midia', 'users' => '/usuarios', 'plans' => '/planos',
    'logs' => '/logs', 'settings' => '/configuracoes',
];
$cmsSection = isset($cmsSection) && is_string($cmsSection) && isset($cmsPaths[$cmsSection]) ? $cmsSection : 'overview';
$cmsIcons = [
    'overview' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'pages' => '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h4M10 12h6M10 16h6"/>',
    'edit' => '<path d="M4 20h16M6 16l10-10 3 3-10 10-4 1z"/>',
    'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1"/><path d="m3 17 6-6 4 4 3-3 5 5"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 6a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>',
    'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7m10 10 2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
];
$cmsIcon = static function (string $name) use ($cmsIcons): string {
    return '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $cmsIcons[$name] . '</svg>';
};
?>
<aside class="cms-sidebar" id="cms-sidebar" aria-label="Navegação demonstrativa do CMS">
 <div class="cms-brand-row">
  <a class="cms-brand" href="/" aria-label="Realizze CMS, visão geral"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="138" height="35"><small>CMS</small></a>
  <button type="button" class="cms-menu-toggle" id="cms-menu-toggle" aria-label="Abrir navegação" aria-controls="cms-navigation" aria-expanded="false"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
 </div>
 <nav class="cms-nav" id="cms-navigation" aria-label="Seções planejadas">
 <?php foreach ($cmsMenu as $group => $items): ?>
  <b><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></b>
  <?php foreach ($items as [$slug, $label, $symbol]): ?>
   <a href="<?= htmlspecialchars($cmsPaths[$slug], ENT_QUOTES, 'UTF-8') ?>"<?= $slug === $cmsSection ? ' class="selected" aria-current="page"' : '' ?>><?= $cmsIcon($symbol) ?><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></a>
  <?php endforeach; ?>
 <?php endforeach; ?>
 </nav>
 <div class="cms-bottom">Realizze 2.0 · Prévia visual</div>
</aside>
