<?php $this->layout('default'); use Moves\Services\CmsContent; use Moves\Support\CmsView as V;
$page = CmsContent::page($id);
if ($page === null) { http_response_code(404); echo V::empty('Página não encontrada.'); return; } ?>
<div class="cms-alert cms-alert-info">Prévia interna: <?= $page['status'] === 'published' ? 'esta versão está publicada.' : 'esta versão ainda não aparece no site.' ?> <a href="/paginas/<?= (int) $id ?>">Voltar à edição</a></div>
<article class="cms-card cms-preview-body"><h2><?= V::e($page['title']) ?></h2><?php if ($page['image_path']): ?><img src="<?= V::e($page['image_path']) ?>" alt=""><?php endif; ?><?= \Moves\Support\HtmlSanitizer::clean($page['content']) ?></article>
