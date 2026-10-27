<?php $this->layout('default', ['title'=>'Blog | Realizze','description'=>'Artigos e ideias sobre organização, comunicação e tecnologia para condomínios.']); ?>
<section class="page-hero"><div class="container narrow"><span class="eyebrow">BLOG REALIZZE</span><h1>Boas ideias para o dia a dia.</h1><p>Conteúdos sobre organização, comunicação e tecnologia na rotina condominial.</p></div></section>
<section class="section"><div class="container blog-grid">
<?php $posts = require __DIR__ . '/../components/posts-data.php'; foreach ($posts as $slug => $post): ?>
<article class="post-card"><div class="post-art post-art-<?= $this->e($post['category'] === 'Gestão' ? 'one' : ($post['category'] === 'Comunicação' ? 'two' : 'three')) ?>"><span><?= $this->e($post['category']) ?></span></div><div class="post-body"><span class="post-tag"><?= $this->e($post['category']) ?></span><h2><a href="/blog/<?= $this->e($slug) ?>"><?= $this->e($post['title']) ?></a></h2><p><?= $this->e($post['excerpt']) ?></p><a class="text-link" href="/blog/<?= $this->e($slug) ?>">Ler artigo →</a></div></article>
<?php endforeach; ?>
</div><p class="demo-note container">Artigos demonstrativos para apresentação do tema. Substitua por conteúdo editorial aprovado antes da publicação.</p></section>
