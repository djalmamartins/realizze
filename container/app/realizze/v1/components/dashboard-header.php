<?php /** UI-028: o período e notificações não simulam dados financeiros. */ ?>
<header class="top">
<div style="display:flex;align-items:center;gap:12px"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><div><h1>Olá, <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>!</h1><p>Seu painel financeiro está sendo preparado. <strong>Sem dados integrados.</strong></p></div></div>
<div class="toolbar" aria-label="Filtros financeiros indisponíveis">
<div class="months"><button type="button" disabled title="Disponível após integração financeira">Selecionar período</button></div>
<div class="period"><button type="button" disabled title="Disponível após integração financeira">Mensal</button><button type="button" disabled title="Disponível após integração financeira">Anual</button></div>
<button class="bell" type="button" data-demo="true" aria-label="Notificações ainda indisponíveis"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></button>
<span class="avatar" aria-hidden="true"><?= htmlspecialchars((string) (require __DIR__ . '/profile-initial.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
</div>
</header>
