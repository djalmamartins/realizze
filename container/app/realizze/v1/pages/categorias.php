<?php /** Layout de categorias sem operações ou dados financeiros reais. */ ?>
<section class="card finance-workspace" aria-labelledby="categorias-title">
<div class="finance-toolbar"><div><h2 id="categorias-title">Categorias</h2><p class="muted">Organize receitas, despesas e transferências por categoria.</p></div><button type="button" class="primary" disabled title="Cadastro disponível após integração">+ Nova categoria</button></div>
<div class="finance-goal-summary" aria-label="Resumo de categorias indisponível"><div class="finance-goal-stat"><span>Categorias de receitas</span><strong>—</strong><small>Aguardando dados reais</small></div><div class="finance-goal-stat"><span>Categorias de despesas</span><strong>—</strong><small>Sem consulta financeira</small></div><div class="finance-goal-stat"><span>Categorias personalizadas</span><strong>—</strong><small>Cadastro ainda indisponível</small></div></div>
<div class="finance-search-preview"><div><label for="category-search">Buscar categoria</label><input id="category-search" type="search" placeholder="Nome da categoria" disabled></div><div><label for="category-type">Tipo</label><select id="category-type" disabled><option>Todos os tipos</option></select></div><div><label for="category-status">Origem</label><select id="category-status" disabled><option>Todas as origens</option></select></div></div>
<?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Categorias — integração pendente',
    'columns' => ['Categoria', 'Tipo', 'Movimentações', 'Valor'],
    'title' => 'Nenhuma categoria disponível',
    'description' => 'As categorias autorizadas serão exibidas aqui quando o módulo estiver integrado.',
    'icon' => 'list-filter',
], 'icon' => $icon]) ?>
</section>
