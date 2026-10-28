<section aria-label="Indicadores financeiros" class="cards">
<article class="card balance"><span class="label">Saldo disponível ◉</span><div class="value">R$ 17.992,37</div><span class="muted">Em contas e carteiras</span></article>
<article class="card"><div class="stat"><span class="stat-icon">⌁</span><div><span class="label">Resultado de agosto</span><div class="value green">R$ 17.992,37</div><span class="muted">Receitas − Despesas</span><div><span class="pill">↗ 12,3% vs julho</span></div></div></div></article>
<article class="card" id="receber"><div class="stat"><span class="stat-icon">⇩</span><div><span class="label">A receber</span><div class="value green">R$ 15.320,00</div><span class="muted">Previsto até 31/08</span><div><span class="pill">3 títulos</span></div></div></div></article>
<article class="card" id="pagar"><div class="stat"><span class="stat-icon red-icon">↗</span><div><span class="label">A pagar</span><div class="value red">R$ 11.870,00</div><span class="muted">Previsto até 31/08</span><div><span class="pill danger">7 títulos</span></div></div></div></article>
</section>
<section class="card progress-panel" aria-label="Progresso do mês"><strong>Agosto: 58% concluído</strong><div class="track"><div class="fill" style="width:58%">58%</div></div><span class="muted">17 dias restantes</span></section>
<section class="card due" aria-label="Próximos vencimentos"><h2 class="section-title">Próximos vencimentos</h2><div class="due-grid">
<?php foreach ([['Hoje','R$ 470,00','2 títulos'],['Amanhã','R$ 150,00','1 título'],['Próximos 7 dias','R$ 1.525,00','3 títulos'],['Restante do mês','R$ 9.725,00','7 títulos']] as $due): ?>
<div class="due-item"><span class="stat-icon">▣</span><div><small><?= $due[0] ?></small><strong><?= $due[1] ?></strong><span class="muted"><?= $due[2] ?></span></div></div>
<?php endforeach; ?>
<div class="forecast"><span class="green">Fechamento previsto ⓘ</span><p class="muted">Mantendo o previsto, você encerra o mês com</p><strong>R$ 19.050,00</strong></div>
</div></section>
