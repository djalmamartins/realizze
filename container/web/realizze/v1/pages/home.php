<?php $this->layout('default', [
 'title' => 'Início | Realizze',
 'description' => 'Realizze: uma experiência mais clara, moderna e conectada para a gestão de condomínios.',
]); ?>
<section class="hero">
 <div class="container hero-grid">
  <div class="hero-copy">
   <span class="pill"><span class="pill-dot"></span> CONDOMÍNIOS MAIS CONECTADOS</span>
   <h1>Uma gestão mais <em>simples.</em> Uma rotina mais <em>leve.</em></h1>
   <p>Organize informações, aproxime pessoas e tenha uma visão mais clara do dia a dia do seu condomínio.</p>
   <div class="hero-actions"><a class="btn btn-primary" href="/cadastrar">Começar agora <span aria-hidden="true">↗</span></a><a class="btn btn-outline" href="/solucoes">Conhecer soluções <span aria-hidden="true">→</span></a></div>
   <div class="hero-note"><span class="note-mark">✓</span> Uma plataforma pensada para simplificar o cotidiano.</div>
  </div>
  <div class="hero-visual" aria-label="Ilustração conceitual do painel Realizze">
    <div class="visual-glow"></div>
    <div class="dashboard-mock">
      <div class="mock-top"><span class="mock-logo">realizze<span class="mock-dot">.</span></span><span class="mock-avatar">R</span></div>
      <div class="mock-greeting">Bom dia! <span>✳</span></div>
      <div class="mock-sub">Seu condomínio em uma visão mais clara.</div>
      <div class="mock-stats"><div><span>Solicitações</span><strong>12</strong><small>Em acompanhamento</small></div><div><span>Comunicados</span><strong>08</strong><small>Últimas atualizações</small></div></div>
      <div class="mock-chart"><div class="mock-chart-head"><strong>Visão geral</strong><span>Esta semana</span></div><div class="bars"><i style="height:38%"></i><i style="height:57%"></i><i style="height:46%"></i><i style="height:75%"></i><i style="height:61%"></i><i style="height:91%"></i><i style="height:72%"></i></div></div>
    </div>
    <div class="floating-card"><span class="float-icon">✓</span><div><strong>Tudo organizado</strong><small>Mais clareza na rotina</small></div></div>
  </div>
 </div>
</section>
<section class="trust-strip"><div class="container"><span>MENOS COMPLEXIDADE</span><span>MAIS ORGANIZAÇÃO</span><span>COMUNICAÇÃO CLARA</span><span>EVOLUÇÃO CONTÍNUA</span></div></section>
<section class="section" id="beneficios"><div class="container"><div class="section-heading centered"><span class="eyebrow">POR QUE REALIZZE?</span><h2>O que importa, no lugar certo.</h2><p>Uma experiência desenhada para deixar as tarefas mais simples e as informações mais acessíveis.</p></div>
<div class="feature-grid">
 <article class="feature-card"><span class="feature-icon">▦</span><h3>Organização no dia a dia</h3><p>Uma visão clara para acompanhar informações e atividades importantes.</p><a href="/solucoes">Saiba mais <span aria-hidden="true">→</span></a></article>
 <article class="feature-card"><span class="feature-icon">◎</span><h3>Comunicação próxima</h3><p>Mais contexto e transparência para conectar gestão e moradores.</p><a href="/solucoes">Saiba mais <span aria-hidden="true">→</span></a></article>
 <article class="feature-card"><span class="feature-icon">✦</span><h3>Experiência intuitiva</h3><p>Interfaces pensadas para facilitar o acesso em diferentes dispositivos.</p><a href="/solucoes">Saiba mais <span aria-hidden="true">→</span></a></article>
</div></div></section>
<section class="section section-soft"><div class="container split-grid"><div class="illustration-panel"><div class="illustration-orb"></div><div class="illustration-note"><span>✦</span><strong>Mais tempo para o que importa</strong><small>Rotinas claras, decisões melhores.</small></div><div class="illustration-lines"><span></span><span></span><span></span></div></div><div class="split-copy"><span class="eyebrow">FEITO PARA O COTIDIANO</span><h2>Menos esforço para acompanhar. Mais tranquilidade para administrar.</h2><p>Uma plataforma de gestão precisa ser útil para quem administra e simples para quem acompanha.</p><ul class="check-list"><li>Informações apresentadas com clareza</li><li>Navegação simples e responsiva</li><li>Uma base preparada para evoluir</li></ul><a class="btn btn-primary" href="/sobre">Conheça o Realizze <span aria-hidden="true">→</span></a></div></div></section>
<section class="section"><div class="container"><div class="section-heading between"><div><span class="eyebrow">CONTEÚDOS</span><h2>Ideias para uma gestão melhor.</h2></div><a class="text-link" href="/blog">Ver todos os artigos →</a></div><div class="blog-grid">
<?php $posts = require __DIR__ . '/../components/posts-data.php'; foreach ($posts as $slug => $post): ?>
 <article class="post-card"><div class="post-art post-art-<?= $this->e($post['category'] === 'Gestão' ? 'one' : ($post['category'] === 'Comunicação' ? 'two' : 'three')) ?>"><span><?= $this->e($post['category']) ?></span></div><div class="post-body"><span class="post-tag"><?= $this->e($post['category']) ?></span><h3><a href="/blog/<?= $this->e($slug) ?>"><?= $this->e($post['title']) ?></a></h3><p><?= $this->e($post['excerpt']) ?></p><a class="text-link" href="/blog/<?= $this->e($slug) ?>">Ler artigo →</a></div></article>
<?php endforeach; ?>
</div></div></section>
