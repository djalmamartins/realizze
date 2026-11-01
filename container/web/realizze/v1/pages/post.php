<?php $this->layout('default', ['title' => $post['title'] . ' | Realizze', 'description' => $post['subtitle']]); ?>
<article class="article-page"><div class="container article-width rz-cms-content"><a class="back-link" href="/blog">← Voltar aos conteúdos</a><?php if ($post['category_title']): ?><span class="eyebrow"><?= $this->e($post['category_title']) ?></span><?php endif; ?><h1><?= $this->e($post['title']) ?></h1><p class="article-intro"><?= $this->e($post['subtitle']) ?></p><div class="article-divider"></div><?php if ($post['cover']): ?><img src="<?= $this->e($post['cover']) ?>" alt=""><?php endif; ?>
<?= \Moves\Support\HtmlSanitizer::clean($post['content']) ?>
<div class="article-end"><a class="btn btn-outline" href="/blog">Ver mais conteúdos →</a></div></div></article>
