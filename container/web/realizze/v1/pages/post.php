<?php $this->layout('default', ['title'=>$post['title'].' | Realizze','description'=>$post['excerpt']]); ?>
<article class="article-page"><div class="container article-width"><a class="back-link" href="/blog">← Voltar ao blog</a><span class="eyebrow"><?= $this->e($post['category']) ?></span><h1><?= $this->e($post['title']) ?></h1><p class="article-intro"><?= $this->e($post['excerpt']) ?></p><div class="article-divider"></div>
<?php foreach ($post['body'] as $paragraph): ?><p><?= $this->e($paragraph) ?></p><?php endforeach; ?>
<div class="article-end"><a class="btn btn-outline" href="/blog">Ver mais artigos →</a></div></div></article>
