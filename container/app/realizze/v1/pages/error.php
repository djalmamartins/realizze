<?php
declare(strict_types=1);
$status = in_array((int) ($status ?? 500), [403, 404, 500], true) ? (int) $status : 500;
$messages = [
    403 => ['Acesso não autorizado', 'Você não tem permissão para acessar esta área. Volte para uma área autorizada ou fale com o suporte.'],
    404 => ['Página não encontrada', 'A página que você está procurando não existe ou foi movida.'],
    500 => ['Não conseguimos carregar esta página', 'Ocorreu um problema inesperado. Tente novamente em alguns instantes.'],
];
[$title, $description] = $messages[$status];
$publishedCss = BASE_PATH . '/public/assets/realizze-app/v1/css/dashboard.min.css';
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#16A34A"><link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon-realizze.svg"><link rel="stylesheet" href="/assets/realizze-app/v1/css/dashboard.min.css?v=<?= (int) @filemtime($publishedCss) ?>"><title><?= $status ?> · <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Realizze</title></head>
<body class="app-error-body" data-app-error-status="<?= $status ?>">
<main class="app-error-page" id="overview" tabindex="-1" aria-labelledby="app-error-title">
  <section class="app-error-card">
    <a class="app-error-logo" href="/p" aria-label="Realizze, voltar ao início"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="166" height="42"></a>
    <p class="app-error-code" aria-hidden="true"><?= $status ?></p>
    <h1 id="app-error-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="app-error-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="app-error-actions">
      <?php if ($status === 500): ?>
      <button class="app-error-primary" type="button" onclick="location.reload()">Tentar novamente</button>
      <a href="/p">Voltar ao início</a>
      <?php elseif ($status === 403): ?>
      <a class="app-error-primary" href="/p">Voltar à área autorizada</a>
      <?php else: ?>
      <a class="app-error-primary" href="/p">Voltar ao início</a>
      <button type="button" onclick="history.length&gt;1?history.back():location.assign('/p')">Voltar</button>
      <?php endif; ?>
      <a class="app-error-support" href="/suporte">Falar com o suporte</a>
    </div>
  </section>
</main>
</body></html>
