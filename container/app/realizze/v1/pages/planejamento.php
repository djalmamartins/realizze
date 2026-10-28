<?php
/** UI-004: estado vazio até integração com dados autenticados. */
?>
<section class="card finance-workspace" aria-labelledby="planejamento-title">
  <div class="finance-toolbar">
    <div><h2 id="planejamento-title">Planejamento financeiro</h2><p class="muted">Organize seu orçamento e seus próximos meses.</p></div>
    <button type="button" class="primary" disabled title="Aguardando integração financeira">Novo planejamento</button>
  </div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Planejamento</th><th scope="col">Período</th><th scope="col">Previsto</th><th scope="col">Realizado</th><th scope="col">Status</th></tr></thead>
    <tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('calendar-days') ?></span><h3>Nenhum planejamento cadastrado</h3><p>Quando esta funcionalidade estiver disponível, seus dados aparecerão aqui. Nenhum registro foi criado.</p></div></td></tr></tbody>
  </table></div>
</section>
