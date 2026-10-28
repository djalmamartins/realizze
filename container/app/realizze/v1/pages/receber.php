<?php /** Interface financeira em prévia: nenhum dado é lido ou gravado. */ ?>
<section class="card finance-workspace" aria-labelledby="receber-title">
 <div class="finance-toolbar"><div><h2 id="receber-title">Contas a receber</h2><p class="muted">Organize receitas, vencimentos e recebimentos futuros.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Novo recebimento</button></div>
 <div class="finance-goal-summary" aria-label="Resumo financeiro sem dados conectados"><div class="finance-goal-stat"><span>Recebimentos previstos</span><strong>—</strong><small>Aguardando integração</small></div><div class="finance-goal-stat"><span>Recebimentos confirmados</span><strong>—</strong><small>Sem valores disponíveis</small></div><div class="finance-goal-stat"><span>Em atraso</span><strong>—</strong><small>Dados não carregados</small></div></div>
 <div class="finance-toolbar"><div><h3>Consultar contas a receber</h3><p class="muted">Filtros e lançamentos ficarão disponíveis após a integração.</p></div><button type="button" disabled>Exportar</button></div>
 <div class="finance-search-preview"><div><label for="receber-search">Pesquisar</label><input id="receber-search" type="search" placeholder="Descrição ou categoria" disabled></div><div><label for="receber-period">Período</label><select id="receber-period" disabled><option>Todos os períodos</option></select></div><div><label for="receber-status">Situação</label><select id="receber-status" disabled><option>Todas as situações</option></select></div></div>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Lançamentos a receber — integração pendente',
    'columns' => ['Descrição', 'Vencimento', 'Categoria', 'Status', 'Valor'],
    'title' => 'Nenhum lançamento disponível',
    'description' => 'Os dados autorizados aparecerão nesta tela após a implementação do módulo. Nenhum valor demonstrativo é apresentado.',
    'icon' => 'arrow-down-left',
], 'icon' => $icon]) ?>
</section>
