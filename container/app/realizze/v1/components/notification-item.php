<?php
/** Um item do sininho ou da tela de notificações. Espera $note, $token, $e e $noteCompact. */
use Moves\Services\Notifications;

$noteClass = 'note-item note-' . (in_array($note['priority'], ['warning', 'critical'], true) ? $note['priority'] : 'info') . ($note['read_at'] === null ? ' is-unread' : '');
$noteInner = '<span class="note-dot" aria-hidden="true"></span><span class="note-text"><strong>' . $e($note['title']) . '</strong>'
    . ($note['body'] !== '' ? '<small>' . $e($note['body']) . '</small>' : '')
    . '<span class="note-meta">' . $e(Notifications::categoryLabel($note['category'])) . ($note['created_at'] !== null ? ' · <time datetime="' . $e((new DateTimeImmutable($note['created_at']))->format('c')) . '">' . $e(Notifications::ago($note['created_at'])) . '</time>' : '') . '</span>'
    . ($note['read_at'] === null ? '<span class="sr-only"> (não lida)</span>' : '') . '</span>';
?>
<?php if ($note['id'] > 0): ?>
<form method="post" action="/minha-conta/notificacoes/abrir"><input type="hidden" name="csrf_token" value="<?= $e($token) ?>"><input type="hidden" name="id" value="<?= (int) $note['id'] ?>"><button type="submit" class="<?= $e($noteClass) ?>"><?= $noteInner ?></button></form>
<?php else: ?>
<a class="<?= $e($noteClass) ?>" href="<?= $e($note['url'] ?? Notifications::destination($note)) ?>"><?= $noteInner ?></a>
<?php endif; ?>
