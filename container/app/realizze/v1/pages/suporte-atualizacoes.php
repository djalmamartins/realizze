<?php
declare(strict_types=1);
use Moves\Services\CmsVersions; use Moves\Support\CmsView as V;
require dirname(__DIR__) . '/components/support-shell.php';
$rows = CmsVersions::listing('app', 30);
$supportOpen('Atualizações'); ?>
<section class="sp-card"><?php if ($rows === []): ?><div class="sp-empty"><strong>Nenhuma atualização publicada ainda.</strong></div><?php endif; foreach ($rows as $v): ?><article class="sp-note"><strong>v<?= V::e($v['version']) ?><?= $v['name'] !== '' ? ' · ' . V::e($v['name']) : '' ?></strong><p><?= V::e($v['notes']) ?></p><small><?= V::date($v['created_at']) ?></small></article><?php endforeach; ?></section>
<?php $supportClose();
