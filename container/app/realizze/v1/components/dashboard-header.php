<?php
/** Cabeçalho da visão geral: período real, navegável por mês ou ano. */
$period = \Moves\Services\Finance::period($_GET);
?>
<div class="section-topbar"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
<header class="top">
<div>
<p class="eyebrow"><?= htmlspecialchars(\Moves\Services\OrganizationContext::current()['name'] ?? 'Meu financeiro', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= \Moves\Services\OrganizationContext::current() !== null ? 'Dashboard' : 'Visão Geral' ?></p>
<h1>Olá, <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>!</h1><p>Aqui está o resumo de <strong><?= htmlspecialchars($period['long'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.</p>
</div>
<div class="page-heading-meta">
<?= $appView->render('components::context-badge', ['contextType' => \Moves\Services\OrganizationContext::current() !== null ? 'enterprise' : 'personal']) ?>
<?= $appView->render('components::app-toolbar', ['toolbarSection' => 'overview', 'name' => $name ?? '', 'user' => $user ?? null]) ?>
</div>
</header>
