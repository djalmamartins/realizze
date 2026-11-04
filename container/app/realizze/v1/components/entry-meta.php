<?php /** Repetição, tags, anexos e observação de um lançamento. Espera $e, $detail e, opcional, $metaHideNotes. */ ?>
<?php if ($detail !== null && (($detail['notes'] !== null && empty($metaHideNotes)) || $detail['tags'] !== [] || $detail['attachments'] !== [] || $detail['series_type'] === 'fixed')): ?>
<div class="entry-meta">
  <?php if ($detail['series_type'] === 'fixed'): ?><span class="entry-chip is-series">Fixa <?= (int) $detail['series_index'] ?>/<?= (int) $detail['series_total'] ?></span><?php endif; ?>
  <?php foreach ($detail['tags'] as $tag): ?><span class="entry-chip"><?= $e($tag) ?></span><?php endforeach; ?>
  <?php foreach ($detail['attachments'] as $attachment): ?><a class="entry-chip is-file" href="/minha-conta/financeiro/anexo?id=<?= (int) $attachment['id'] ?>" download><?= $e($attachment['name']) ?></a><?php endforeach; ?>
  <?php if ($detail['notes'] !== null && empty($metaHideNotes)): ?><p class="entry-note"><?= $e($detail['notes']) ?></p><?php endif; ?>
</div>
<?php endif; ?>
