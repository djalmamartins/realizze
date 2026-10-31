<?php /** Interface financeira em prévia: nenhum dado é lido ou gravado. */ ?>
<section class="card finance-workspace" aria-labelledby="pagar-title">
 <div class="finance-toolbar"><div><h2 id="pagar-title">Contas a pagar</h2><p class="muted">Controle compromissos, pagamentos e vencimentos.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova despesa</button></div>
 <div class="finance-goal-summary" aria-label="Resumo financeiro sem dados conectados"><div class="finance-goal-stat"><span>Despesas previstas</span><strong>—</strong><small>Aguardando integração</small></div><div class="finance-goal-stat"><span>Despesas pagas</span><strong>—</strong><small>Sem valores disponíveis</small></div><div class="finance-goal-stat"><span>Vencidas</span><strong>—</strong><small>Dados não carregados</small></div></div>
 <div class="finance-toolbar"><div><h3>Consultar contas a pagar</h3><p class="muted">Filtros e lançamentos ficarão disponíveis após a integração.</p></div><button type="button" disabled>Exportar</button></div>
<?= $appView->render('components::finance-filter-preview', ['financeFilterFields' => [
    ['id' => 'pagar-search', 'label' => 'Pesquisar', 'type' => 'search', 'placeholder' => 'Descrição ou categoria'],
    ['id' => 'pagar-period', 'label' => 'Período', 'type' => 'select', 'option' => 'Todos os períodos'],
    ['id' => 'pagar-status', 'label' => 'Situação', 'type' => 'select', 'option' => 'Todas as situações'],
]]) ?>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Lançamentos a pagar — integração pendente',
    'columns' => ['Descrição', 'Vencimento', 'Categoria', 'Status', 'Valor'],
    'title' => 'Nenhum lançamento disponível',
    'description' => 'Os dados autorizados aparecerão nesta tela após a implementação do módulo. Nenhum valor demonstrativo é apresentado.',
    'icon' => 'arrow-up-right',
], 'icon' => $icon]) ?>
</section>
