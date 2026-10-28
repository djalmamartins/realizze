<header class="top">
<div style="display:flex;align-items:center;gap:12px"><button class="sidebar-toggle" id="sidebar-toggle" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><div><h1>Bom dia, <?= $name ?>! 👋</h1><p>Aqui está o resumo de <strong id="month-label">Agosto de 2026</strong>.</p></div></div>
<div class="toolbar">
<div class="months"><button type="button" id="prev-month" aria-label="Mês anterior">‹</button><button type="button" id="previous-month-label">Julho</button><button type="button" class="selected" id="current-month-label">Agosto 2026</button><button type="button" id="next-month" aria-label="Próximo mês">›</button></div>
<div class="period"><button type="button" class="selected" data-period="mensal">Mensal</button><button type="button" data-period="anual">Anual</button></div>
<button class="bell" type="button" data-demo="true" aria-label="Notificações">♧<span class="dot">3</span></button><span class="avatar" aria-label="Perfil"><?= htmlspecialchars(strtoupper(substr((string)($user['first_name'] ?? 'R'),0,1)),ENT_QUOTES,'UTF-8') ?></span>
</div>
</header>
