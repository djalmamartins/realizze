<?php
/**
 * CMS-UI-041: tabela vazia reutilizável e acessível.
 * Prévia estática: não consulta banco, não permite ações e não interpreta HTML.
 */
$table = is_array($cmsEmptyTable ?? null) ? $cmsEmptyTable : [];
$columns = $table['columns'] ?? [];
if (!is_array($columns) || $columns === []) {
    throw new InvalidArgumentException('A tabela de prévia exige colunas.');
}
$escapeTable = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="cms-table-wrap" role="region" tabindex="0" aria-label="<?= $escapeTable($table['caption'] ?? 'Registros indisponíveis') ?>">
  <p class="cms-scroll-hint">Role horizontalmente para ver todas as colunas. Com teclado, foque esta região e use as setas.</p>
  <table class="cms-table">
    <caption class="cms-table-caption"><?= $escapeTable($table['caption'] ?? 'Registros indisponíveis') ?></caption>
    <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= $escapeTable($column) ?></th><?php endforeach; ?></tr></thead>
    <tbody><tr><td colspan="<?= count($columns) ?>" class="cms-empty">
      <strong><?= $escapeTable($table['title'] ?? 'Nenhum registro consultado') ?></strong>
      <p><?= $escapeTable($table['description'] ?? 'Nenhum dado é exibido nesta prévia.') ?></p>
    </td></tr></tbody>
  </table>
</div>
