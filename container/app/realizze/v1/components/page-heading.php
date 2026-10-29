<?php
declare(strict_types=1);
/** Cabeçalho compartilhado das áreas PF/PJ; metadados nunca são HTML. */
$pageHeadingData = is_array($pageHeading ?? null) ? $pageHeading : [];
$pageHeadingTitle = (string) ($pageHeadingData['title'] ?? 'Área financeira');
$pageHeadingDescription = (string) ($pageHeadingData['description'] ?? '');
$pageHeadingContext = ($pageHeadingData['context'] ?? null) === 'enterprise' ? 'enterprise' : 'personal';
$escapePageHeading = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="page-heading" aria-labelledby="app-page-title">
  <div>
    <p class="eyebrow">Meu financeiro / <?= $escapePageHeading($pageHeadingTitle) ?></p>
    <h1 id="app-page-title"><?= $escapePageHeading($pageHeadingTitle) ?></h1>
    <p class="muted"><?= $escapePageHeading($pageHeadingDescription) ?></p>
  </div>
  <div class="page-heading-meta">
    <?= $appView->render('components::context-badge', ['contextType' => $pageHeadingContext]) ?>
    <span class="preview-chip">Prévia de interface</span>
  </div>
</section>
