<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0c694e">
<title><?= $this->e($title ?? 'Realizze') ?></title>
<meta name="description" content="<?= $this->e($description ?? '') ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= $this->e($canonical) ?>"><?php endif; ?>
<?php if (!empty($canonical)): ?><meta property="og:url" content="<?= $this->e($canonical) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Realizze">
<meta name="twitter:card" content="<?= !empty($ogImage) ? 'summary_large_image' : 'summary' ?>">
<meta property="og:title" content="<?= $this->e($title ?? 'Realizze') ?>">
<meta property="og:description" content="<?= $this->e($description ?? '') ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= $this->e((getenv('APP_URL') ?: '') . $ogImage) ?>"><?php endif; ?>
<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon-realizze.svg">
<link rel="stylesheet" href="/assets/realizze/v1/css/style.min.css?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/realizze/v1/css/style.min.css') ?>">
<link rel="stylesheet" href="/assets/realizze/v1/css/responsive-fixes.min.css?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/realizze/v1/css/responsive-fixes.min.css') ?>">
