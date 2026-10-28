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
