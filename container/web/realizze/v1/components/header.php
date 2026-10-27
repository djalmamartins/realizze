<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="Realizze, página inicial">
      <img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="152" height="38">
    </a>
    <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="main-navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <?php $this->insert('components/navbar'); ?>
    <div class="header-actions">
      <a class="login-link" href="/entrar">Entrar</a>
      <a class="btn btn-primary btn-sm" href="/cadastrar">Criar conta <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</header>
