<?php
declare(strict_types=1);

/** Formulário GET compartilhado para períodos personalizados de relatórios. */
$periodForm = is_array($reportPeriodForm ?? null) ? $reportPeriodForm : [];
$periodAction = (string) ($periodForm['action'] ?? '');
$periodStartValue = (string) ($periodForm['start'] ?? '');
$periodEndValue = (string) ($periodForm['end'] ?? '');
$periodHidden = is_array($periodForm['hidden'] ?? null) ? $periodForm['hidden'] : [];
$periodSubmit = (string) ($periodForm['submit'] ?? 'Aplicar período');
$escapeReportPeriod = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form class="finance-filter-bar report-period-form" method="get" action="<?= $escapeReportPeriod($periodAction) ?>" data-swap-get>
  <label>De<input type="date" name="inicio" value="<?= $escapeReportPeriod($periodStartValue) ?>" required></label>
  <label>Até<input type="date" name="fim" value="<?= $escapeReportPeriod($periodEndValue) ?>" required></label>
  <?php foreach ($periodHidden as $name => $value): ?>
  <input type="hidden" name="<?= $escapeReportPeriod($name) ?>" value="<?= $escapeReportPeriod($value) ?>">
  <?php endforeach; ?>
  <button type="submit"><?= $escapeReportPeriod($periodSubmit) ?></button>
</form>
