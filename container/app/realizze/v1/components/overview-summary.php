<?php /** UI-026: indicadores vazios; nunca representar valores ilustrativos como saldos reais. */ ?>
<section aria-label="Indicadores financeiros ainda indisponíveis" class="cards">
  <article class="card balance"><span class="label">Saldo disponível</span><div class="value" aria-label="Saldo não disponível">—</div><span class="muted">Aguardando contas autorizadas</span></article>
  <?php foreach ([
    ['Resultado do período', 'Receitas menos despesas', 'green', '<path d="M3 12h4l3-7 4 14 3-7h4"/>'],
    ['A receber', 'Lançamentos ainda não integrados', 'green', '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>'],
    ['A pagar', 'Lançamentos ainda não integrados', 'red', '<path d="M7 17 17 7m0 0H9m8 0v8"/>'],
  ] as [$label, $description, $color, $iconPath]): ?>
  <article class="card"><div class="stat"><span class="stat-icon <?= $color === 'red' ? 'red-icon' : '' ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?= $iconPath ?></svg></span><div><span class="label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><div class="value <?= $color ?>" aria-label="Valor não disponível">—</div><span class="muted"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span></div></div></article>
  <?php endforeach; ?>
</section>
<section class="card progress-panel" aria-label="Progresso do período indisponível"><strong>Progresso do período</strong><div class="track" aria-hidden="true"></div><span class="muted">Sem lançamentos integrados</span></section>
<section class="card due" aria-labelledby="next-due-title"><h2 class="section-title" id="next-due-title">Próximos vencimentos</h2><div class="due-grid">
<?php foreach (['Hoje', 'Amanhã', 'Próximos 7 dias', 'Restante do mês'] as $period): ?>
  <div class="due-item"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4m8-4v4"/></svg></span><div><small><?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></small><strong>—</strong><span class="muted">Não consultado</span></div></div>
<?php endforeach; ?>
  <div class="forecast"><span class="green">Fechamento previsto</span><p class="muted">Projeção disponível após a integração financeira.</p><strong aria-label="Projeção indisponível">—</strong></div>
</div></section>
