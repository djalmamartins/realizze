<?php
/** Chips dos filtros ativos e "Limpar". Espera $e, $filterChips (rótulo => link sem o filtro) e $filterClearUrl. */
if ($filterChips === []) return;
?>
<div class="filter-bar">
  <?php foreach ($filterChips as $chipLabel => $chipUrl): ?>
  <span class="filter-chip"><?= $e($chipLabel) ?><a href="<?= $e($chipUrl) ?>" aria-label="Remover filtro <?= $e($chipLabel) ?>">×</a></span>
  <?php endforeach; ?>
  <a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar</a>
</div>
