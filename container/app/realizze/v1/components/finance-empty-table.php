<?php
declare(strict_types=1);

/**
 * APP-UI-044: tabela financeira vazia compartilhada.
 * Prévia estática: sem consultas, dados inventados ou operações persistentes.
 */
$table = is_array($financeEmptyTable ?? null) ? $financeEmptyTable : [];
$columns = $table['columns'] ?? [];
if (!is_array($columns) || $columns === []) {
    throw new InvalidArgumentException('A tabela financeira exige ao menos uma coluna.');
}
$escapeFinance = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$iconName = $table['icon'] ?? null;
?>
<div class="finance-table-wrap" role="region" tabindex="0" aria-label="<?= $escapeFinance($table['caption'] ?? 'Registros financeiros indisponíveis') ?>">
  <table class="finance-table">
    <caption class="finance-table-caption"><?= $escapeFinance($table['caption'] ?? 'Registros financeiros indisponíveis') ?></caption>
    <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= $escapeFinance($column) ?></th><?php endforeach; ?></tr></thead>
    <tbody><tr><td colspan="<?= count($columns) ?>">
      <div class="finance-empty">
        <?php if (is_string($iconName) && $iconName !== '' && is_callable($icon ?? null)): ?>
          <span class="empty-icon" aria-hidden="true"><?= $icon($iconName) ?></span>
        <?php endif; ?>
        <h3><?= $escapeFinance($table['title'] ?? 'Nenhum registro disponível') ?></h3>
        <p><?= $escapeFinance($table['description'] ?? 'A integração financeira ainda não está disponível.') ?></p>
      </div>
    </td></tr></tbody>
  </table>
</div>
