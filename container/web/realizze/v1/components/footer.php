<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/" aria-label="Realizze, página inicial"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="160" height="40"></a>
        <p><?= $this->e((\Moves\Services\CmsSite::publicSettings()['public_footer_text'] ?? '') ?: 'Controle · Planeje · Realize. Mais clareza para organizar suas finanças pessoais e empresariais.') ?></p>
      </div>
      <div><h3>Plataforma</h3><a href="/#para-voce">Para você</a><a href="/#empresas">Para empresas</a><a href="/#funcionalidades">Funcionalidades</a><a href="/cadastrar">Criar conta</a></div>
      <div><h3>Institucional</h3><?php $cmsFooter = \Moves\Services\CmsSite::publicMenu('footer'); if ($cmsFooter !== []): foreach ($cmsFooter as $item): ?><a href="<?= $this->e($item['url']) ?>"><?= $this->e($item['label']) ?></a><?php endforeach; else: ?><a href="/sobre">Sobre nós</a><a href="/blog">Conteúdos</a><a href="/contato">Contato</a><?php endif; ?></div>
      <div><h3>Informações</h3><a href="/#seguranca">Segurança</a><a href="/#perguntas">Perguntas frequentes</a><a href="/termos">Termos de uso</a><a href="/privacidade">Privacidade</a></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Realizze. Todos os direitos reservados.</span><span>Controle · Planeje · Realize.</span></div>
  </div>
</footer>
