<?php
/** CMS-UI-002: menu de navegação demonstrativo, sem links administrativos ativos. */
$cmsMenu = [
 'Gerenciamento' => [['Visão geral','▦'],['Páginas','▤'],['Conteúdos','✎'],['Mídia','▧']],
 'Plataforma' => [['Usuários','♙'],['Planos e assinaturas','▤'],['Logs e auditoria','◷'],['Configurações','⚙']],
];
?>
<aside class="cms-sidebar" aria-label="Navegação demonstrativa do CMS">
 <div class="cms-brand">realizze<small>CMS</small></div>
 <nav class="cms-nav" aria-label="Seções planejadas">
 <?php foreach ($cmsMenu as $group => $items): ?>
  <b><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></b>
  <?php foreach ($items as $item): ?>
   <span<?= $item[0] === 'Visão geral' ? ' class="selected"' : '' ?>><span aria-hidden="true"><?= htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') ?></span> <?= htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') ?></span>
  <?php endforeach; ?>
 <?php endforeach; ?>
 </nav>
 <div class="cms-bottom">Realizze 2.0 · Prévia visual</div>
</aside>
