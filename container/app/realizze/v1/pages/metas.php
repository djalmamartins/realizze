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
  <div class="finance-toolbar"><div><h3>Seus objetivos</h3><p class="muted">As metas cadastradas aparecerão nesta lista.</p></div></div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Objetivo</th><th scope="col">Valor alvo</th><th scope="col">Valor atual</th><th scope="col">Prazo</th><th scope="col">Progresso</th></tr></thead><tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('target') ?></span><h3>Nenhuma meta cadastrada</h3><p>Crie metas de reserva, viagens ou investimentos quando o módulo estiver disponível. Nenhum valor fictício é exibido.</p></div></td></tr></tbody></table></div>
</section>
