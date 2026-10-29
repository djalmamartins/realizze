<?php /** Interface financeira em prévia: nenhum dado é lido ou gravado. */ ?>
<section class="card finance-workspace" aria-labelledby="movimentacoes-title">
 <div class="finance-toolbar"><div><h2 id="movimentacoes-title">Movimentações</h2><p class="muted">Consulte entradas e saídas em todas as suas contas.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova movimentação</button></div>
 <div class="finance-goal-summary" aria-label="Resumo financeiro sem dados conectados"><div class="finance-goal-stat"><span>Entradas</span><strong>—</strong><small>Aguardando integração</small></div><div class="finance-goal-stat"><span>Saídas</span><strong>—</strong><small>Sem valores disponíveis</small></div><div class="finance-goal-stat"><span>Saldo do período</span><strong>—</strong><small>Dados não carregados</small></div></div>
 <div class="finance-toolbar"><div><h3>Consultar movimentações</h3><p class="muted">Filtros e lançamentos ficarão disponíveis após a integração.</p></div><button type="button" disabled>Exportar</button></div>
<?= $appView->render('components::finance-filter-preview', ['financeFilterFields' => [
    ['id' => 'movimentacoes-search', 'label' => 'Pesquisar', 'type' => 'search', 'placeholder' => 'Descrição ou categoria'],
    ['id' => 'movimentacoes-period', 'label' => 'Período', 'type' => 'select', 'option' => 'Todos os períodos'],
    ['id' => 'movimentacoes-status', 'label' => 'Situação', 'type' => 'select', 'option' => 'Todas as situações'],
]]) ?>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Movimentações — integração pendente',
    'columns' => ['Data', 'Descrição', 'Categoria', 'Conta', 'Valor'],
    'title' => 'Nenhum lançamento disponível',
    'description' => 'Os dados autorizados aparecerão nesta tela após a implementação do módulo. Nenhum valor demonstrativo é apresentado.',
    'icon' => 'arrows-left-right',
], 'icon' => $icon]) ?>
</section>
