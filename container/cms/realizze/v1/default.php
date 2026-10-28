<?php
declare(strict_types=1);
/** CMS Realizze: prévia visual isolada. Nenhuma operação administrativa disponível. */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><meta name="color-scheme" content="light">
<title>Realizze CMS — Layout em desenvolvimento</title>
<style><?php require __DIR__ . '/assets/css/cms.css'; ?></style>
<style><?php require __DIR__ . '/assets/css/responsive-fixes.css'; ?></style>
</head>
<body><a class="cms-skip-link" href="#cms-content">Ir para o conteúdo principal</a><div class="cms-shell"><?php require __DIR__ . '/components/icons.php'; require __DIR__ . '/components/sidebar.php'; ?><div class="cms-main"><header class="cms-topbar"><strong>Gerenciamento de conteúdo</strong><span class="cms-preview-status">Prévia local · Operações administrativas indisponíveis</span></header><main class="cms-content" id="cms-content" tabindex="-1"><?php
// UI preview selection is deliberately local: no routes, authentication or data access.
$cmsPreviews = ['overview' => 'Visão geral', 'pages' => 'Páginas', 'contents' => 'Conteúdos', 'media' => 'Mídia', 'users' => 'Usuários', 'plans' => 'Planos e assinaturas', 'logs' => 'Logs e auditoria', 'settings' => 'Configurações'];
$cmsSection = isset($cmsSection) && is_string($cmsSection) && array_key_exists($cmsSection, $cmsPreviews) ? $cmsSection : 'overview';
$slug = $cmsSection;
$label = $cmsPreviews[$slug];
?>
<section class="cms-preview-panel" id="cms-preview-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"<?= $slug === $cmsSection ? '' : ' hidden' ?>>
<?php if ($slug === 'overview'): require __DIR__ . '/components/overview.php'; else: require __DIR__ . '/pages/' . $slug . '.php'; endif; ?>
</section>
<footer class="cms-footer">Realizze CMS · Interface demonstrativa</footer></main></div></div><script><?php require __DIR__ . '/assets/js/cms.js'; ?></script></body></html>
