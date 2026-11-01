<?php $this->layout('default'); ?>
<section class="page-hero"><div class="container narrow"><h1><?= $this->e($page['title']) ?></h1><?php if ($page['excerpt'] !== ''): ?><p><?= $this->e($page['excerpt']) ?></p><?php endif; ?></div></section>
<article class="article-page"><div class="container article-width rz-cms-content"><?php if ($page['image_path']): ?><img src="<?= $this->e($page['image_path']) ?>" alt=""><?php endif; ?><?= \Moves\Support\HtmlSanitizer::clean($page['content']) ?></div></article>
