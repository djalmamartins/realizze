<?php
/** Cabeçalho da visão geral: período real, navegável por mês ou ano. */
$period = \Moves\Services\Finance::period($_GET);
?>
<header class="top">
<div style="display:flex;align-items:center;gap:12px"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><div><h1>Olá, <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>!</h1><p>Aqui está o resumo de <strong><?= htmlspecialchars($period['long'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.</p><?= $appView->render('components::context-badge', ['contextType' => \Moves\Services\OrganizationContext::current() !== null ? 'enterprise' : 'personal']) ?></div></div>
<div class="toolbar">
<nav class="months months-live" aria-label="Período">
<a href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['previous']), ENT_QUOTES, 'UTF-8') ?>" aria-label="Período anterior">‹</a>
<a class="months-side" href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['previous']), ENT_QUOTES, 'UTF-8') ?>" tabindex="-1"><?= htmlspecialchars($period['previousLabel'], ENT_QUOTES, 'UTF-8') ?></a>
<strong><?= htmlspecialchars($period['label'], ENT_QUOTES, 'UTF-8') ?></strong>
<a class="months-side" href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['next']), ENT_QUOTES, 'UTF-8') ?>" tabindex="-1"><?= htmlspecialchars($period['nextLabel'], ENT_QUOTES, 'UTF-8') ?></a>
<a href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['next']), ENT_QUOTES, 'UTF-8') ?>" aria-label="Próximo período">›</a>
</nav>
<nav class="period period-live" aria-label="Visão">
<a href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['view'] === 'mensal' ? [] : $period['other']), ENT_QUOTES, 'UTF-8') ?>"<?= $period['view'] === 'mensal' ? ' aria-current="true"' : '' ?>>Mensal</a>
<a href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('overview', null, $period['view'] === 'anual' ? ['visao' => 'anual', 'ano' => $period['start']->format('Y')] : $period['other']), ENT_QUOTES, 'UTF-8') ?>"<?= $period['view'] === 'anual' ? ' aria-current="true"' : '' ?>>Anual</a>
</nav>
<a class="bell" href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('notificacoes'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Notificações"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></a>
<span class="avatar" aria-hidden="true"><?= htmlspecialchars((string) (require __DIR__ . '/profile-initial.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
</div>
</header>
