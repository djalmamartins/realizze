<?php
declare(strict_types=1);

/**
 * Cabeçalho compartilhado dos relatórios.
 *
 * Espera $reportHeading com id, title e description e uma lista estruturada
 * $reportActions. Os valores são sempre tratados como texto; atributos livres
 * não são aceitos para evitar HTML arbitrário nas páginas consumidoras.
 */
$heading = is_array($reportHeading ?? null) ? $reportHeading : [];
$actions = is_array($reportActions ?? null) ? $reportActions : [];
$headingId = (string) ($heading['id'] ?? 'report-title');
$headingTitle = (string) ($heading['title'] ?? 'Relatório');
$headingDescription = (string) ($heading['description'] ?? '');
$escapeReportHeading = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="finance-toolbar report-heading">
  <div>
    <h2 id="<?= $escapeReportHeading($headingId) ?>"><?= $escapeReportHeading($headingTitle) ?></h2>
    <?php if ($headingDescription !== ''): ?><p class="muted"><?= $escapeReportHeading($headingDescription) ?></p><?php endif; ?>
  </div>
  <?php if ($actions !== []): ?>
  <div class="finance-toolbar-actions" aria-label="Ações do relatório">
    <?php foreach ($actions as $action):
        if (!is_array($action) || trim((string) ($action['label'] ?? '')) === '') continue;
        $type = ($action['type'] ?? 'link') === 'button' ? 'button' : 'link';
        $class = trim((string) ($action['class'] ?? 'finance-button'));
        $label = (string) $action['label'];
    ?>
      <?php if ($type === 'button'): ?>
      <button type="button" class="<?= $escapeReportHeading($class) ?>"<?= !empty($action['print']) ? ' data-finance-print' : '' ?>><?= $escapeReportHeading($label) ?></button>
      <?php else: ?>
      <a class="<?= $escapeReportHeading($class) ?>" href="<?= $escapeReportHeading((string) ($action['href'] ?? '#')) ?>"<?= !empty($action['download']) ? ' download' : '' ?>><?= $escapeReportHeading($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
