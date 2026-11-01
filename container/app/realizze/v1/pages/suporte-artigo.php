<?php
declare(strict_types=1);
use Moves\Services\CmsSite; use Moves\Support\CmsView as V; use Moves\Support\HtmlSanitizer;
require dirname(__DIR__) . '/components/support-shell.php';
$article = CmsSite::helpArticle('uri', $uri);
if ($article === null) { http_response_code(404); $supportOpen('Artigo não encontrado'); echo '<section class="sp-card"><a href="/suporte/ajuda">Voltar à ajuda</a></section>'; $supportClose(); return; }
$supportOpen($article['title']); ?>
<article class="sp-card sp-article"><p class="sp-meta"><?= V::e($article['subtitle']) ?></p><?= HtmlSanitizer::clean($article['content']) ?></article>
<div class="sp-actions"><a class="sp-btn" href="/suporte/ajuda">Voltar à ajuda</a><a class="sp-btn sp-btn-primary" href="/suporte/novo">Ainda preciso de ajuda</a></div>
<?php $supportClose();
