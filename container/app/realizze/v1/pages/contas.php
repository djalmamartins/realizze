<?php /** Contas e cartões: apresentação sem leitura ou alteração de dados financeiros. */ ?>
<section class="card finance-workspace" aria-labelledby="contas-title">
 <div class="finance-toolbar"><div><h2 id="contas-title">Contas e cartões</h2><p class="muted">Acompanhe suas contas, carteiras e cartões em um só lugar.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Adicionar conta</button></div>
 <div class="finance-goal-summary" aria-label="Resumo de contas ainda indisponível"><div class="finance-goal-stat"><span>Saldo total</span><strong>—</strong><small>Aguardando dados autorizados</small></div><div class="finance-goal-stat"><span>Contas conectadas</span><strong>—</strong><small>Nenhuma integração ativa</small></div><div class="finance-goal-stat"><span>Faturas em aberto</span><strong>—</strong><small>Sem dados financeiros</small></div></div>
 <div class="finance-toolbar"><div><h3>Suas contas</h3><p class="muted">Contas bancárias, dinheiro e carteiras digitais.</p></div><button type="button" disabled>Gerenciar carteiras</button></div>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Contas e carteiras — integração pendente',
    'columns' => ['Instituição', 'Tipo', 'Conta', 'Saldo', 'Status'],
    'title' => 'Nenhuma conta cadastrada',
    'description' => 'As contas autorizadas aparecerão aqui quando o cadastro estiver disponível.',
    'icon' => 'credit-card',
], 'icon' => $icon]) ?>
 <div class="finance-toolbar"><div><h3>Cartões de crédito</h3><p class="muted">Limites, fechamentos e vencimentos das faturas.</p></div><button type="button" disabled>+ Adicionar cartão</button></div>
 <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Cartões de crédito — integração pendente',
    'columns' => ['Cartão', 'Limite', 'Fatura atual', 'Vencimento'],
    'title' => 'Nenhum cartão cadastrado',
    'description' => 'Os cartões serão exibidos aqui após a implementação segura do módulo.',
    'icon' => 'credit-card',
], 'icon' => $icon]) ?>
</section>
