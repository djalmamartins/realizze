<?php
declare(strict_types=1);
use Moves\Services\CmsSite; use Moves\Support\CmsView as V; use Moves\Support\HtmlSanitizer;
require dirname(__DIR__) . '/components/support-shell.php';
$article = CmsSite::helpArticle('uri', $uri);
if ($article === null) { http_response_code(404); $supportOpen('Artigo não encontrado'); echo '<section class="sp-card"><a href="/suporte/ajuda">Voltar à ajuda</a></section>'; $supportClose(); return; }
\Moves\Services\CmsQuery::run('UPDATE posts SET views=views+1 WHERE id=?', [$article['id']]);
$supportOpen($article['title']); $vote = '<input type="hidden" name="csrf_token" value="' . V::e(\Moves\Services\Csrf::token()) . '">'; ?>
<article class="sp-card sp-article"><p class="sp-meta"><?= V::e($article['subtitle']) ?></p><?= HtmlSanitizer::clean($article['content']) ?></article>
<section class="sp-card sp-vote"><strong>Este artigo ajudou?</strong><form method="post" action="/suporte/ajuda/<?= V::e($article['uri']) ?>/votar"><?= $vote ?><button class="sp-btn" name="helpful" value="1">Sim</button> <button class="sp-btn" name="helpful" value="0">Não</button></form></section>
<div class="sp-actions"><a class="sp-btn" href="/suporte/ajuda">Voltar à ajuda</a><a class="sp-btn sp-btn-primary" href="/suporte/novo">Ainda preciso de ajuda</a></div>
<?php $supportClose();
