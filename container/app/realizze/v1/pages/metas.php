<?php
/** UI-004: estado vazio até integração com dados autenticados. */
?>
<section class="card finance-workspace" aria-labelledby="metas-title">
  <div class="finance-toolbar">
    <div><h2 id="metas-title">Metas financeiras</h2><p class="muted">Acompanhe a evolução dos seus objetivos.</p></div>
    <button type="button" class="primary" disabled title="Aguardando integração financeira">Nova meta</button>
  </div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Objetivo</th><th scope="col">Valor alvo</th><th scope="col">Valor atual</th><th scope="col">Prazo</th><th scope="col">Progresso</th></tr></thead>
    <tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('target') ?></span><h3>Nenhuma meta cadastrada</h3><p>Quando esta funcionalidade estiver disponível, seus dados aparecerão aqui. Nenhum registro foi criado.</p></div></td></tr></tbody>
  </table></div>
</section>
