<?php /** Prévia de layout sem valores financeiros reais ou gráficos fabricados. */ ?>
<section class="card finance-workspace" aria-labelledby="relatorios-title">
<div class="finance-toolbar"><div><h2 id="relatorios-title">Relatórios financeiros</h2><p class="muted">Visualize resultados, evolução e distribuição das suas finanças.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Exportar relatório</button></div>
<div class="finance-goal-summary" aria-label="Indicadores ainda indisponíveis"><div class="finance-goal-stat"><span>Receitas do período</span><strong>—</strong><small>Aguardando dados autorizados</small></div><div class="finance-goal-stat"><span>Despesas do período</span><strong>—</strong><small>Aguardando dados autorizados</small></div><div class="finance-goal-stat"><span>Resultado líquido</span><strong>—</strong><small>Aguardando dados autorizados</small></div></div>
<div class="finance-search-preview"><div><label for="relatorios-period">Período</label><select id="relatorios-period" disabled><option>Selecionar período</option></select></div><div><label for="relatorios-account">Conta</label><select id="relatorios-account" disabled><option>Todas as contas</option></select></div><div><label for="relatorios-category">Categoria</label><select id="relatorios-category" disabled><option>Todas as categorias</option></select></div></div>
<div class="finance-report-panels"><section class="finance-report-panel" aria-label="Evolução financeira"><h3>Evolução financeira</h3><div class="finance-chart-empty"><span aria-hidden="true"><?= $icon('chart-no-axes-column') ?></span><p>Gráfico disponível após a integração financeira.</p></div></section><section class="finance-report-panel" aria-label="Distribuição por categoria"><h3>Distribuição por categoria</h3><div class="finance-chart-empty"><span aria-hidden="true"><?= $icon('chart-no-axes-column') ?></span><p>Sem dados para exibir neste período.</p></div></section></div>
<div class="finance-toolbar"><div><h3>Detalhamento</h3><p class="muted">Consulte os registros por período e categoria.</p></div></div>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Relatórios financeiros — integração pendente',
    'columns' => ['Período', 'Relatório', 'Formato', 'Situação'],
    'title' => 'Nenhum registro disponível',
    'description' => 'Os resultados serão apresentados quando houver dados reais autorizados.',
    'icon' => 'chart-no-axes-column',
], 'icon' => $icon]) ?>
</section>
