<aside class="sidebar" aria-label="Navegação principal">
<a href="/minha-conta" class="brand" aria-label="Realizze"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="152" height="38"></a>
<nav class="nav" aria-label="Menu principal">
<?php foreach ($items as $index => $item): ?>
<a href="<?= $item[2] ?>" <?= $section === array_keys($sections)[$index] ? 'class="active" aria-current="page"' : '' ?>><span class="symbol" aria-hidden="true"><?= $icon($item[0]) ?></span><?= htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
<div class="sidebar-bottom">
<div class="connect"><strong>Conecte seu banco</strong><p>Importe suas transações e tenha tudo em um só lugar.</p><button type="button" data-demo="true">Conectar agora</button></div>
<div class="profile"><span class="avatar"><?= htmlspecialchars(strtoupper(substr((string)($user['first_name'] ?? 'R'),0,1)),ENT_QUOTES,'UTF-8') ?></span><div><strong><?= $name ?></strong><small>Minha conta</small></div></div>
<div class="sidebar-actions"><button type="button" id="theme-toggle" aria-label="Alternar tema">☾</button><button type="button" data-demo="true" aria-label="Ajuda">ⓘ</button></div>
<form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" style="border:0;background:transparent;width:100%;padding:10px">⇥ Sair</button></form>
</div>
</aside>