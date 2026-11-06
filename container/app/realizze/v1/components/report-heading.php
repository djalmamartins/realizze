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
$headingLevel = (int) ($heading['level'] ?? 2) === 3 ? 3 : 2;
$headingClass = !empty($heading['subsection']) ? ' report-heading-subsection' : '';
$escapeReportHeading = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="finance-toolbar report-heading<?= $headingClass ?>">
  <div>
    <?php if ($headingLevel === 3): ?><h3 id="<?= $escapeReportHeading($headingId) ?>"><?= $escapeReportHeading($headingTitle) ?></h3><?php else: ?><h2 id="<?= $escapeReportHeading($headingId) ?>"><?= $escapeReportHeading($headingTitle) ?></h2><?php endif; ?>
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
      <button type="button" class="<?= $escapeReportHeading($class) ?>"<?= !empty($action['print']) ? ' data-finance-print' : '' ?><?= isset($action['entry_open']) ? ' data-entry-open="' . $escapeReportHeading($action['entry_open']) . '"' : '' ?><?= !empty($action['disabled']) ? ' disabled' : '' ?><?= isset($action['title']) ? ' title="' . $escapeReportHeading($action['title']) . '"' : '' ?>><?= $escapeReportHeading($label) ?></button>
      <?php else: ?>
      <a class="<?= $escapeReportHeading($class) ?>" href="<?= $escapeReportHeading((string) ($action['href'] ?? '#')) ?>"<?= !empty($action['download']) ? ' download' : '' ?>><?= $escapeReportHeading($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
