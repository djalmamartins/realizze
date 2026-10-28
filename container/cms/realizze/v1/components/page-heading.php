<?php
/**
 * CMS preview heading. Static per-page metadata only; no database or authorization logic.
 * Include from a preview template after assigning $cmsHeading.
 */
$heading = is_array($cmsHeading ?? null) ? $cmsHeading : [];
$escapeHeading = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<header class="cms-heading">
  <div>
    <span class="cms-eyebrow"><?= $escapeHeading($heading['eyebrow'] ?? 'Realizze CMS') ?></span>
    <h1 id="cms-page-title"><?= $escapeHeading($heading['title'] ?? 'Prévia administrativa') ?></h1>
    <p><?= $escapeHeading($heading['description'] ?? 'Funcionalidade indisponível.') ?></p>
  </div>
  <span class="cms-chip"><?= $escapeHeading($heading['badge'] ?? 'Prévia visual') ?></span>
</header>
