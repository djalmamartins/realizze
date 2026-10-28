<?php /** Prévia de layout sem valores financeiros reais ou gráficos fabricados. */ ?>
<section class="card finance-workspace" aria-labelledby="planejamento-title">
<div class="finance-toolbar"><div><h2 id="planejamento-title">Planejamento financeiro</h2><p class="muted">Defina limites mensais e acompanhe o orçamento planejado.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Novo orçamento</button></div>
<div class="finance-goal-summary" aria-label="Indicadores ainda indisponíveis"><div class="finance-goal-stat"><span>Orçamento previsto</span><strong>—</strong><small>Aguardando dados autorizados</small></div><div class="finance-goal-stat"><span>Valor realizado</span><strong>—</strong><small>Aguardando dados autorizados</small></div><div class="finance-goal-stat"><span>Saldo disponível</span><strong>—</strong><small>Aguardando dados autorizados</small></div></div>
<div class="finance-search-preview"><div><label for="planejamento-period">Período</label><select id="planejamento-period" disabled><option>Selecionar período</option></select></div><div><label for="planejamento-account">Conta</label><select id="planejamento-account" disabled><option>Todas as contas</option></select></div><div><label for="planejamento-category">Categoria</label><select id="planejamento-category" disabled><option>Todas as categorias</option></select></div></div>
<div class="finance-report-panels"><section class="finance-report-panel" aria-label="Previsto x realizado"><h3>Previsto x realizado</h3><div class="finance-chart-empty"><span aria-hidden="true"><?= $icon('calendar-days') ?></span><p>Gráfico disponível após a integração financeira.</p></div></section><section class="finance-report-panel" aria-label="Orçamento por categoria"><h3>Orçamento por categoria</h3><div class="finance-chart-empty"><span aria-hidden="true"><?= $icon('calendar-days') ?></span><p>Sem dados para exibir neste período.</p></div></section></div>
<div class="finance-toolbar"><div><h3>Detalhamento</h3><p class="muted">Consulte os registros por período e categoria.</p></div></div>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Planejamento financeiro — integração pendente',
    'columns' => ['Planejamento', 'Período', 'Previsto', 'Realizado', 'Status'],
    'title' => 'Nenhum registro disponível',
    'description' => 'Os resultados serão apresentados quando houver dados reais autorizados.',
    'icon' => 'calendar-days',
], 'icon' => $icon]) ?>
</section>
