<?php
/** Atalhos e datas do período no modal de filtros. Espera $e, $periodStart e $periodEnd (AAAA-MM-DD). */
$periodToday = new DateTimeImmutable('today');
$periodPresets = [
    'Este mês' => [$periodToday->format('Y-m-01'), $periodToday->format('Y-m-t')],
    'Mês passado' => [$periodToday->modify('first day of last month')->format('Y-m-d'), $periodToday->modify('last day of last month')->format('Y-m-d')],
    'Próximos 30 dias' => [$periodToday->format('Y-m-d'), $periodToday->modify('+30 days')->format('Y-m-d')],
    'Este ano' => [$periodToday->format('Y-01-01'), $periodToday->format('Y-12-31')],
];
$periodStartName = $periodStartName ?? 'inicio';
$periodEndName = $periodEndName ?? 'fim';
?>
<fieldset class="filter-period" data-filter-period data-start-name="<?= $e($periodStartName) ?>" data-end-name="<?= $e($periodEndName) ?>">
  <legend>Período</legend>
  <div class="filter-presets">
    <?php foreach ($periodPresets as $presetLabel => [$presetStart, $presetEnd]): ?>
    <button type="button" data-filter-preset data-start="<?= $e($presetStart) ?>" data-end="<?= $e($presetEnd) ?>" aria-pressed="<?= $presetStart === $periodStart && $presetEnd === $periodEnd ? 'true' : 'false' ?>"><?= $e($presetLabel) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="finance-dialog-row">
    <label>Início<input type="date" name="<?= $e($periodStartName) ?>" value="<?= $e($periodStart) ?>"></label>
    <label>Fim<input type="date" name="<?= $e($periodEndName) ?>" value="<?= $e($periodEnd) ?>"></label>
  </div>
</fieldset>
