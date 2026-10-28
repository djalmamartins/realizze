<?php /** UI-026: indicadores vazios; nunca representar valores ilustrativos como saldos reais. */ ?>
<section aria-label="Indicadores financeiros ainda indisponíveis" class="cards">
  <article class="card balance"><span class="label">Saldo disponível</span><div class="value" aria-label="Saldo não disponível">—</div><span class="muted">Aguardando contas autorizadas</span></article>
  <?php foreach ([
    ['Resultado do período', 'Receitas menos despesas', 'green'],
    ['A receber', 'Lançamentos ainda não integrados', 'green'],
    ['A pagar', 'Lançamentos ainda não integrados', 'red'],
  ] as [$label, $description, $color]): ?>
  <article class="card"><div class="stat"><span class="stat-icon <?= $color === 'red' ? 'red-icon' : '' ?>" aria-hidden="true">—</span><div><span class="label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><div class="value <?= $color ?>" aria-label="Valor não disponível">—</div><span class="muted"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span></div></div></article>
  <?php endforeach; ?>
</section>
<section class="card progress-panel" aria-label="Progresso do período indisponível"><strong>Progresso do período</strong><div class="track" aria-hidden="true"></div><span class="muted">Sem lançamentos integrados</span></section>
<section class="card due" aria-labelledby="next-due-title"><h2 class="section-title" id="next-due-title">Próximos vencimentos</h2><div class="due-grid">
<?php foreach (['Hoje', 'Amanhã', 'Próximos 7 dias', 'Restante do mês'] as $period): ?>
  <div class="due-item"><span class="stat-icon" aria-hidden="true">—</span><div><small><?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></small><strong>—</strong><span class="muted">Não consultado</span></div></div>
<?php endforeach; ?>
  <div class="forecast"><span class="green">Fechamento previsto</span><p class="muted">Projeção disponível após a integração financeira.</p><strong aria-label="Projeção indisponível">—</strong></div>
</div></section>
