<?php $this->layout('default'); use Moves\Services\CmsAuth; use Moves\Services\CmsContent; use Moves\Support\CmsView as V;
$page = $id ? CmsContent::page($id) : null;
if ($id && $page === null) { http_response_code(404); echo V::empty('Página não encontrada.'); return; }
$p = $page ?? []; $status = $p['status'] ?? 'draft'; $canPublish = CmsAuth::can($admin, 'content.publish');
$base = rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/'; ?>
<?php $this->start('actions'); ?><a class="cms-btn" href="/paginas">Voltar</a><?php $this->stop(); ?>
<form class="cms-editor-layout" method="post" action="/acao/page-save"><?= V::csrf() ?><input type="hidden" name="id" value="<?= (int) ($id ?? 0) ?: '' ?>">
<div class="cms-card">
 <div class="cms-field"><label for="page-title">Título *</label><input id="page-title" name="title" required maxlength="190" value="<?= V::e($p['title'] ?? '') ?>" data-counter="page-title-count"><small id="page-title-count" aria-live="polite"></small></div>
 <div class="cms-field"><label for="page-slug">Endereço amigável</label><input id="page-slug" name="slug" value="<?= V::e($p['slug'] ?? '') ?>" placeholder="Gerado a partir do título"><small data-slug-preview="<?= V::e($base) ?>" data-slug-field="slug"></small><small>Use sobre, solucoes, contato, termos ou privacidade para substituir o texto dessas páginas.</small></div>
 <?= V::field('Resumo', 'excerpt', $p['excerpt'] ?? '', ['type' => 'textarea', 'rows' => 2]) ?>
 <?= V::field('Conteúdo *', 'content', $p['content'] ?? '', ['type' => 'textarea', 'rows' => 16, 'editor' => true, 'help' => 'Scripts, estilos e atributos perigosos são removidos ao salvar.']) ?>
 <p class="cms-seo-note">SEO automático: título, descrição, URL e imagem social são gerados deste conteúdo ao publicar.</p>
</div>
<aside class="cms-editor-side">
 <section class="cms-card"><div class="cms-section-head"><h2>Publicação</h2><?= V::badge($status, $status === 'published' ? 'Publicada' : 'Rascunho') ?></div>
  <div class="cms-editor-actions"><?php if ($status === 'published'): ?><button class="cms-btn-primary">Salvar alterações</button><?php if ($canPublish): ?><button class="cms-btn" name="publish_after" value="0">Voltar para rascunho</button><?php endif; ?><?php else: ?><button class="cms-btn">Salvar rascunho</button><?php if ($canPublish): ?><button class="cms-btn-primary" name="publish_after" value="1">Publicar</button><?php endif; ?><?php endif; ?></div><?php if ($page): ?><p><a href="/paginas/<?= (int) $id ?>/previa">Ver prévia</a></p><?php endif; ?></section>
 <section class="cms-card"><h2>Imagem</h2><?= V::mediaField('Escolher da biblioteca', 'image_path', $p['image_path'] ?? null) ?></section>
</aside></form>
<?php if ($page): ?><section class="cms-card"><h2>Histórico</h2><?php foreach (CmsContent::revisions($id) as $i => $rev): ?><div class="cms-row"><small><?= V::date($rev['created_at']) ?> · <?= V::e($rev['note']) ?> · <?= V::e($rev['author'] ?? '') ?></small><?php if ($i > 0): ?><?= V::actionButton('page-restore', 'Restaurar', ['id' => $id, 'revision_id' => $rev['id']], 'cms-btn', 'Restaurar esta versão?') ?><?php endif; ?></div><?php endforeach; ?></section><?php endif; ?>
