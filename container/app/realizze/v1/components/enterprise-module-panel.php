<?php
/** Painel reutilizável de módulo PJ: apresentação sem dados ou operações persistentes. */
$moduleId = (string) ($module['id'] ?? '');
$moduleTitle = (string) ($module['title'] ?? '');
$moduleDescription = (string) ($module['description'] ?? '');
$moduleColumns = $module['columns'] ?? [];
$moduleIcon = (string) ($module['icon'] ?? 'building');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="enterprise-module" id="enterprise-<?= $escape($moduleId) ?>" tabindex="-1" aria-labelledby="enterprise-title-<?= $escape($moduleId) ?>">
  <header class="enterprise-module-header">
    <span class="enterprise-module-icon" aria-hidden="true"><?= $icon($moduleIcon) ?></span>
    <div>
      <h4 id="enterprise-title-<?= $escape($moduleId) ?>"><?= $escape($moduleTitle) ?></h4>
      <p><?= $escape($moduleDescription) ?></p>
    </div>
    <span class="enterprise-module-status">Prévia</span>
  </header>
  <div class="enterprise-module-table-wrap" role="region" aria-label="Estrutura da tabela de <?= $escape($moduleTitle) ?>" tabindex="0">
    <p class="enterprise-scroll-hint">Role horizontalmente para ver todas as colunas. Com teclado, foque esta região e use as setas.</p>
    <table class="enterprise-module-table">
      <caption class="enterprise-module-caption">Estrutura de <?= $escape($moduleTitle) ?> — prévia sem dados</caption>
      <thead><tr><?php foreach ($moduleColumns as $column): ?><th scope="col"><?= $escape((string) $column) ?></th><?php endforeach; ?></tr></thead>
      <tbody><tr><td colspan="<?= count($moduleColumns) ?>">
        <div class="enterprise-module-empty"><strong>Nenhum registro consultado</strong><span>Integração indisponível. Nenhum dado financeiro ou cadastral é exibido.</span></div>
      </td></tr></tbody>
    </table>
  </div>
</section>
