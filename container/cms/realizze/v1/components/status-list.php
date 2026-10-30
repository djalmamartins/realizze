<?php
declare(strict_types=1);

/** CMS-UI-078: semantic read-only administrative statuses; no data or session access. */
$rows = $cmsStatusRows ?? null;
if (!is_array($rows) || $rows === []) {
    throw new InvalidArgumentException('A lista de status exige itens.');
}
foreach ($rows as $row) {
    if (!is_array($row) || !is_string($row['label'] ?? null)
        || trim($row['label']) === '' || !is_string($row['status'] ?? null)
        || trim($row['status']) === '') {
        throw new InvalidArgumentException('Item de status administrativo inválido.');
    }
}
$escapeStatus = static fn (string $value): string =>
    htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<dl class="cms-status-list">
  <?php foreach ($rows as $row): ?>
    <div class="cms-setting-row">
      <dt><?= $escapeStatus($row['label']) ?></dt>
      <dd><?= $escapeStatus($row['status']) ?></dd>
    </div>
  <?php endforeach; ?>
</dl>
