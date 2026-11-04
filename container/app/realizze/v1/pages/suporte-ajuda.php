<?php
declare(strict_types=1);
use Moves\Services\CmsSite; use Moves\Support\CmsView as V;
require dirname(__DIR__) . '/components/support-shell.php';
$q = V::queryString('q'); $result = CmsSite::helpArticles($q, V::queryPage());
$supportOpen('Base de conhecimento'); ?>
<section class="sp-card"><form class="sp-filter" method="get"><label for="sp-help-q">Pesquisar</label><input id="sp-help-q" class="sp-input" type="search" name="q" value="<?= V::e($q) ?>"><button class="sp-btn">Pesquisar</button></form>
<?php if ($result['rows'] === []): ?><div class="sp-empty"><strong>Nenhum artigo encontrado.</strong><p>Se precisar, fale com a equipe.</p><a class="sp-btn sp-btn-primary" href="/suporte/novo">Abrir chamado</a></div><?php else: ?><ul class="sp-list"><?php foreach ($result['rows'] as $a): ?><li><a href="/suporte/ajuda/<?= V::e($a['uri']) ?>"><span><strong><?= V::e($a['title']) ?></strong><small><?= V::e($a['category_title'] ?? 'Geral') ?> · <?= V::e($a['subtitle']) ?></small></span></a></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php $supportClose();
