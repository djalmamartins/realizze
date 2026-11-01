<?php $this->layout('default'); use Moves\Services\CmsAuth; use Moves\Services\CmsContent; use Moves\Support\CmsView as V;
$page = $id ? CmsContent::page($id) : null;
if ($id && $page === null) { http_response_code(404); echo V::empty('Página não encontrada.'); return; }
$p = $page ?? []; ?>
<div class="cms-grid-2"><form class="cms-card" method="post" action="/acao/page-save"><?= V::csrf() ?><input type="hidden" name="id" value="<?= (int) ($id ?? 0) ?: '' ?>">
<?= V::field('Título', 'title', $p['title'] ?? '', ['required' => true]) ?><?= V::field('Endereço (slug)', 'slug', $p['slug'] ?? '', ['help' => 'Vazio gera a partir do título. Use sobre, solucoes, contato, termos ou privacidade para substituir o texto dessas páginas.']) ?>
<?= V::field('Resumo', 'excerpt', $p['excerpt'] ?? '', ['type' => 'textarea', 'rows' => 2]) ?><?= V::field('Conteúdo (HTML)', 'content', $p['content'] ?? '', ['type' => 'textarea', 'rows' => 14, 'help' => 'Scripts, estilos e atributos perigosos são removidos ao salvar.']) ?>
<?= V::mediaField('Imagem', 'image_path', $p['image_path'] ?? null) ?>
<?php $this->insert('components/seo-fields', ['seo' => $p]); ?><button class="cms-btn-primary">Salvar</button></form>
<aside><?php if ($page): ?><div class="cms-card"><h2>Publicação</h2><p><?= V::badge($page['status'], $page['status'] === 'published' ? 'Publicada' : 'Rascunho') ?></p><div class="cms-actions"><a class="cms-btn" href="/paginas/<?= (int) $id ?>/previa">Prévia</a>
<?php if (CmsAuth::can($admin, 'content.publish')): ?><?= $page['status'] === 'published' ? V::actionButton('page-publish', 'Despublicar', ['id' => $id, 'publish' => 0]) : V::actionButton('page-publish', 'Publicar', ['id' => $id, 'publish' => 1], 'cms-btn-primary') ?><?php endif; ?></div></div>
<div class="cms-card"><h2>Histórico</h2><?php foreach (CmsContent::revisions($id) as $i => $rev): ?><p><small><?= V::date($rev['created_at']) ?> · <?= V::e($rev['note']) ?> · <?= V::e($rev['author'] ?? '') ?></small><?php if ($i > 0): ?> <?= V::actionButton('page-restore', 'Restaurar', ['id' => $id, 'revision_id' => $rev['id']], 'cms-btn', 'Restaurar esta versão?') ?><?php endif; ?></p><?php endforeach; ?></div><?php else: ?><div class="cms-card"><p>Toda página nova começa como rascunho. O site só mostra páginas publicadas.</p></div><?php endif; ?></aside></div>
