<?php
declare(strict_types=1);
use Moves\Services\Csrf; use Moves\Services\HelpdeskNotifier as N; use Moves\Services\SessionAuth; use Moves\Support\CmsView as V;
require dirname(__DIR__) . '/components/support-shell.php';
$userId = (int) SessionAuth::current()['id']; $rows = N::inbox($userId, 'customer', 60); $unread = N::unread($userId, 'customer');
$supportOpen('Notificações'); ?>
<section class="sp-card"><?php if ($unread > 0): ?><form method="post" action="/suporte/notificacoes/lidas" class="sp-actions"><input type="hidden" name="csrf_token" value="<?= V::e(Csrf::token()) ?>"><button class="sp-btn">Marcar todas como lidas</button></form><?php endif; ?>
<?php if ($rows === []): ?><div class="sp-empty"><strong>Nenhuma notificação.</strong></div><?php endif; foreach ($rows as $n): ?><article class="sp-note sp-note-<?= V::e($n['priority']) ?>"><strong><?= $n['link'] !== '' && $n['link'] !== '/suporte/notificacoes' ? '<a href="' . V::e($n['link']) . '">' . V::e($n['title']) . '</a>' : V::e($n['title']) ?><?= $n['read_at'] ? '' : ' · Nova' ?></strong><?php if ($n['body'] !== ''): ?><p><?= V::e($n['body']) ?></p><?php endif; ?><small><?= V::date($n['created_at']) ?></small></article><?php endforeach; ?></section>
<?php $supportClose();
