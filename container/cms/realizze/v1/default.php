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
</head>
<body><div class="cms-shell"><?php require __DIR__ . '/components/sidebar.php'; ?><div class="cms-main"><header class="cms-topbar"><strong>Gerenciamento de conteúdo</strong><span>Ambiente administrativo · Indisponível</span></header><main class="cms-content"><?php
// UI preview selection is deliberately local: no routes, authentication or data access.
$cmsPreviews = ['overview' => 'Visão geral', 'pages' => 'Páginas', 'contents' => 'Conteúdos', 'media' => 'Mídia', 'users' => 'Usuários', 'plans' => 'Planos e assinaturas', 'logs' => 'Logs e auditoria', 'settings' => 'Configurações'];
foreach ($cmsPreviews as $slug => $label):
?>
<section class="cms-preview-panel" id="cms-preview-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"<?= $slug === 'overview' ? '' : ' hidden' ?>>
<?php if ($slug === 'overview'): require __DIR__ . '/components/overview.php'; else: require __DIR__ . '/pages/' . $slug . '.php'; endif; ?>
</section>
<?php endforeach; ?><footer class="cms-footer">Realizze CMS · Interface demonstrativa</footer></main></div></div><script>
(function(){'use strict';const nav=document.querySelector('.cms-nav');if(!nav)return;nav.addEventListener('click',function(event){const button=event.target.closest('button[data-cms-preview]');if(!button||!nav.contains(button))return;const slug=button.dataset.cmsPreview;const target=document.getElementById('cms-preview-'+slug);if(!target)return;document.querySelectorAll('.cms-preview-panel').forEach(function(panel){panel.hidden=panel!==target;});nav.querySelectorAll('button[data-cms-preview]').forEach(function(item){const selected=item===button;item.classList.toggle('selected',selected);item.setAttribute('aria-current',selected?'page':'false');});const heading=target.querySelector('h1');if(heading)heading.setAttribute('tabindex','-1'),heading.focus();});})();
</script></body></html>
