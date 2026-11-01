<?php $this->layout('default'); use Moves\Services\CmsAuth; use Moves\Services\CmsContent; use Moves\Support\CmsView as V;
$post = $id ? CmsContent::post($id) : null;
if ($id && $post === null) { http_response_code(404); echo V::empty('Conteúdo não encontrado.'); return; }
$p = $post ?? [];
$cats = ['' => 'Selecione']; foreach (CmsContent::categories() as $c) { $cats[$c['id']] = $c['title']; }
$authors = []; foreach (CmsContent::authors() as $a) { $authors[$a['id']] = $a['name']; }
$status = $p['status'] ?? 'draft'; $canPublish = CmsAuth::can($admin, 'content.publish');
$postAt = isset($p['post_at']) ? date('Y-m-d\TH:i', strtotime($p['post_at'])) : date('Y-m-d\TH:i');
$scheduled = $status === 'published' && isset($p['post_at']) && strtotime($p['post_at']) > time();
$base = rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/blog/'; ?>
<div class="cms-page-tools"><a class="cms-btn" href="/conteudos">Voltar</a></div>
<form class="cms-editor-layout" method="post" action="/acao/post-save" enctype="multipart/form-data"><?= V::csrf() ?><input type="hidden" name="id" value="<?= (int) ($id ?? 0) ?: '' ?>">
<div class="cms-card">
 <div class="cms-field"><label for="post-title">Título *</label><input id="post-title" name="title" required maxlength="140" value="<?= V::e($p['title'] ?? '') ?>" data-counter="post-title-count"><small id="post-title-count" aria-live="polite"></small></div>
 <div class="cms-field"><label for="post-uri">Endereço amigável</label><input id="post-uri" name="uri" value="<?= V::e($p['uri'] ?? '') ?>" placeholder="Gerado a partir do título"><small data-slug-preview="<?= V::e($base) ?>"></small></div>
 <div class="cms-field"><label for="post-subtitle">Resumo *</label><textarea id="post-subtitle" name="subtitle" rows="3" required maxlength="300" data-counter="post-subtitle-count"><?= V::e($p['subtitle'] ?? '') ?></textarea><small id="post-subtitle-count" aria-live="polite"></small></div>
 <?= V::field('Conteúdo *', 'content', $p['content'] ?? '', ['type' => 'textarea', 'rows' => 16, 'editor' => true, 'help' => 'Scripts, estilos e atributos perigosos são removidos ao salvar.']) ?>
 <?php $this->insert('components/seo-fields', ['seo' => $p]); ?>
</div>
<aside class="cms-editor-side">
 <section class="cms-card"><div class="cms-section-head"><h2>Status e data</h2><?= $scheduled ? V::badge('pending', 'Agendado') : V::badge($status, $status === 'published' ? 'Publicado' : 'Rascunho') ?></div>
  <?= V::field('Publicar em', 'post_at', $postAt, ['type' => 'datetime-local']) ?>
  <div class="cms-editor-actions"><?php if ($status === 'published'): ?><button class="cms-btn-primary">Salvar alterações</button><?php if ($canPublish): ?><button class="cms-btn" name="publish_after" value="0">Voltar para rascunho</button><?php endif; ?><?php else: ?><button class="cms-btn">Salvar rascunho</button><?php if ($canPublish): ?><button class="cms-btn-primary" name="publish_after" value="1">Publicar</button><?php endif; ?><?php endif; ?></div></section>
 <section class="cms-card"><h2>Classificação</h2><?= V::field('Tipo', 'post_type', $p['post_type'] ?? (V::queryString('type') ?: 'article'), ['type' => 'select', 'choices' => CmsContent::POST_TYPES]) ?><?= V::field('Categoria', 'category', $p['category'] ?? '', ['type' => 'select', 'choices' => $cats]) ?><p><a href="/conteudos/categorias">Criar categoria</a></p><?= V::field('Autor *', 'author', $p['author'] ?? $admin['id'], ['type' => 'select', 'choices' => $authors]) ?></section>
 <section class="cms-card"><h2>Imagem de capa</h2><?php if (!empty($p['cover'])): ?><img class="cms-cover-preview" src="<?= V::e($p['cover']) ?>" alt=""><?php endif; ?><label class="cms-drop" for="post-cover-file"><strong>Enviar nova imagem</strong><small>JPG, PNG ou WebP</small><input id="post-cover-file" type="file" name="cover_file" accept=".jpg,.jpeg,.png,.webp"></label><?= V::mediaField('Ou escolher da biblioteca', 'cover', $p['cover'] ?? null) ?></section>
 <section class="cms-card"><h2>Vídeo relacionado</h2><?= V::field('URL ou ID do YouTube', 'video', $p['video'] ?? '') ?></section>
</aside></form>
<?php if ($post): ?><div class="cms-actions cms-actions-end"><?= V::actionButton('post-delete', 'Excluir conteúdo', ['id' => $id], 'cms-btn-danger', 'Excluir este conteúdo?') ?></div><?php endif; ?>
