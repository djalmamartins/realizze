<?php
/** Metas: layout visual sem consulta ou persistência financeira. */
?>
<section class="card finance-workspace" aria-labelledby="metas-title">
  <div class="finance-toolbar"><div><h2 id="metas-title">Metas financeiras</h2><p class="muted">Defina objetivos, prazos e acompanhe sua evolução.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova meta</button></div>
  <div class="finance-goal-summary" aria-label="Resumo de metas não conectado">
    <div class="finance-goal-stat"><span>Metas ativas</span><strong>—</strong><small>Dados indisponíveis</small></div>
    <div class="finance-goal-stat"><span>Valor planejado</span><strong>—</strong><small>Sem dados financeiros</small></div>
    <div class="finance-goal-stat"><span>Objetivos concluídos</span><strong>—</strong><small>Aguardando integração</small></div>
  </div>
  <?= $appView->render('components::finance-filter-preview', ['financeFilterFields' => [
    ['id' => 'metas-search', 'label' => 'Buscar objetivo', 'type' => 'search', 'placeholder' => 'Nome do objetivo'],
    ['id' => 'metas-status', 'label' => 'Situação', 'type' => 'select', 'option' => 'Todas as situações'],
    ['id' => 'metas-deadline', 'label' => 'Prazo', 'type' => 'select', 'option' => 'Todos os prazos'],
]]) ?>
  <div class="finance-toolbar"><div><h3>Seus objetivos</h3><p class="muted">As metas cadastradas aparecerão nesta lista.</p></div></div>
  <?= $appView->render('components::finance-empty-table', ['financeEmptyTable' => [
    'caption' => 'Metas financeiras — integração pendente',
    'columns' => ['Objetivo', 'Valor alvo', 'Valor atual', 'Prazo', 'Progresso'],
    'title' => 'Nenhuma meta cadastrada',
    'description' => 'Crie metas de reserva, viagens ou investimentos quando o módulo estiver disponível. Nenhum valor fictício é exibido.',
    'icon' => 'target',
], 'icon' => $icon]) ?>
</section>
