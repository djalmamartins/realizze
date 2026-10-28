<?php
/** CMS-UI-005: local preview navigation only; no admin endpoints. */
$cmsMenu = [
 'Gerenciamento' => [['overview','Visão geral','▦'],['pages','Páginas','▤'],['contents','Conteúdos','✎'],['media','Mídia','▧']],
 'Plataforma' => [['users','Usuários','♙'],['plans','Planos e assinaturas','▤'],['logs','Logs e auditoria','◷'],['settings','Configurações','⚙']],
];
$cmsPaths = ['overview' => '/', 'pages' => '/paginas', 'contents' => '/conteudos', 'media' => '/midia', 'users' => '/usuarios', 'plans' => '/planos', 'logs' => '/logs', 'settings' => '/configuracoes'];
$cmsSection = isset($cmsSection) && is_string($cmsSection) && isset($cmsPaths[$cmsSection]) ? $cmsSection : 'overview';
?>
<aside class="cms-sidebar" aria-label="Navegação demonstrativa do CMS">
 <div class="cms-brand">realizze<small>CMS</small></div>
 <nav class="cms-nav" aria-label="Seções planejadas">
 <?php foreach ($cmsMenu as $group => $items): ?>
  <b><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></b>
  <?php foreach ($items as [$slug, $label, $symbol]): ?>
   <a href="<?= htmlspecialchars($cmsPaths[$slug], ENT_QUOTES, 'UTF-8') ?>"<?= $slug === $cmsSection ? ' class="selected" aria-current="page"' : '' ?>><span aria-hidden="true"><?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
  <?php endforeach; ?>
 <?php endforeach; ?>
 </nav>
 <div class="cms-bottom">Realizze 2.0 · Prévia visual</div>
</aside>
