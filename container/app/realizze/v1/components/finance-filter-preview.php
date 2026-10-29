<?php
declare(strict_types=1);

/**
 * APP-UI-075: filtros de prévia PF compartilhados.
 * Somente interface desabilitada; nunca consulta, submete ou altera dados.
 */
$fields = $financeFilterFields ?? null;
if (!is_array($fields) || $fields === []) {
    throw new InvalidArgumentException('A prévia de filtros exige campos.');
}
$seenIds = [];
foreach ($fields as $field) {
    if (!is_array($field)) {
        throw new InvalidArgumentException('Campo de filtro inválido.');
    }
    $id = $field['id'] ?? null;
    $type = $field['type'] ?? null;
    $label = $field['label'] ?? null;
    if (!is_string($id) || preg_match('/\A[a-z][a-z0-9-]*\z/D', $id) !== 1
        || isset($seenIds[$id]) || !in_array($type, ['search', 'select'], true)
        || !is_string($label) || trim($label) === '') {
        throw new InvalidArgumentException('Identificador, tipo ou rótulo de filtro inválido.');
    }
    $seenIds[$id] = true;
}
$escapeFilter = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="finance-search-preview" role="group" aria-label="Filtros financeiros indisponíveis nesta prévia">
  <?php foreach ($fields as $field): ?>
    <div>
      <label for="<?= $escapeFilter($field['id']) ?>"><?= $escapeFilter($field['label']) ?></label>
      <?php if ($field['type'] === 'search'): ?>
        <input id="<?= $escapeFilter($field['id']) ?>" type="search" placeholder="<?= $escapeFilter($field['placeholder'] ?? '') ?>" disabled>
      <?php else: ?>
        <select id="<?= $escapeFilter($field['id']) ?>" disabled><option><?= $escapeFilter($field['option'] ?? 'Indisponível') ?></option></select>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <p class="finance-filter-help muted">Filtros desabilitados até a integração segura. Nenhuma consulta ou alteração é realizada.</p>
</div>
