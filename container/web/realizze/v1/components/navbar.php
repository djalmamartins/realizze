<nav class="site-navigation" id="main-navigation" aria-label="Navegação principal">
<?php $cmsMenu = \Moves\Services\CmsSite::publicMenu('header'); if ($cmsMenu !== []): foreach ($cmsMenu as $item): ?>
  <a href="<?= $this->e($item['url']) ?>"><?= $this->e($item['label']) ?></a>
<?php endforeach; else: ?>
  <a href="/#para-voce">Para você</a>
  <a href="/#empresas">Para empresas</a>
  <a href="/#funcionalidades">Funcionalidades</a>
  <a href="/#como-funciona">Como funciona</a>
  <a href="/#perguntas">Dúvidas</a>
  <a href="/blog">Conteúdos</a>
<?php endif; ?>
</nav>
