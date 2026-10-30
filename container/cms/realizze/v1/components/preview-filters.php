<?php
declare(strict_types=1);

/**
 * CMS-UI-080: filtros visuais compartilhados, sem formulário, consultas ou mutações.
 * Todos os controles permanecem desabilitados até autenticação, RBAC, CSRF e auditoria.
 */
$config = is_array($cmsPreviewFilters ?? null) ? $cmsPreviewFilters : [];
$fields = $config['fields'] ?? null;
if (!is_array($fields) || !array_is_list($fields) || $fields === []) {
    throw new InvalidArgumentException('A prévia de filtros exige uma lista de campos.');
}
$heading = $config['title'] ?? 'Filtros previstos';
$description = $config['description'] ?? 'Filtros indisponíveis até a integração administrativa segura.';
if (!is_string($heading) || trim($heading) === '' ||
    !is_string($description) || trim($description) === '') {
    throw new InvalidArgumentException('Título e descrição dos filtros são obrigatórios.');
}
$seen = [];
foreach ($fields as $field) {
    if (!is_array($field)) {
        throw new InvalidArgumentException('Campo de filtro inválido.');
    }
    $id = $field['id'] ?? null;
    $label = $field['label'] ?? null;
    $type = $field['type'] ?? null;
    if (!is_string($id) || preg_match('/^cms-[a-z0-9-]+$/D', $id) !== 1 ||
        isset($seen[$id]) || !is_string($label) || trim($label) === '' ||
        !in_array($type, ['search', 'text', 'select'], true)) {
        throw new InvalidArgumentException('Identificador, rótulo ou tipo de filtro inválido.');
    }
    $seen[$id] = true;
    if ($type === 'select') {
        if (!isset($field['options']) || !is_array($field['options']) ||
            !array_is_list($field['options']) || $field['options'] === []) {
            throw new InvalidArgumentException('Seleção de prévia exige opções.');
        }
        foreach ($field['options'] as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new InvalidArgumentException('Opção de filtro inválida.');
            }
        }
    } elseif (isset($field['placeholder']) && !is_string($field['placeholder'])) {
        throw new InvalidArgumentException('Placeholder de filtro inválido.');
    }
}
$escapeFilter = static fn (string $value): string =>
    htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="cms-preview-filters" aria-label="<?= $escapeFilter($heading) ?>">
  <div class="cms-preview-filters-head">
    <h3><?= $escapeFilter($heading) ?></h3>
    <span class="cms-status">Somente prévia · filtros bloqueados</span>
  </div>
  <p class="cms-preview-filters-help"><?= $escapeFilter($description) ?></p>
  <div class="cms-preview-filter-grid">
    <?php foreach ($fields as $field): ?>
      <div class="cms-preview-filter-field">
        <label class="cms-field-label" for="<?= $escapeFilter($field['id']) ?>"><?= $escapeFilter($field['label']) ?></label>
        <?php if ($field['type'] === 'select'): ?>
          <select class="cms-field" id="<?= $escapeFilter($field['id']) ?>" disabled>
            <?php foreach ($field['options'] as $option): ?>
              <option><?= $escapeFilter($option) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="cms-field" id="<?= $escapeFilter($field['id']) ?>" type="<?= $escapeFilter($field['type']) ?>" placeholder="<?= $escapeFilter($field['placeholder'] ?? '') ?>" autocomplete="off" disabled>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
