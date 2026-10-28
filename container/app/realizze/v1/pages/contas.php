<?php
/** UI-004: estado vazio até integração com dados autenticados. */
?>
<section class="card finance-workspace" aria-labelledby="contas-title">
  <div class="finance-toolbar">
    <div><h2 id="contas-title">Contas e cartões</h2><p class="muted">Gerencie suas contas e cartões em um só lugar.</p></div>
    <button type="button" class="primary" disabled title="Aguardando integração financeira">Adicionar conta</button>
  </div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Instituição</th><th scope="col">Tipo</th><th scope="col">Conta</th><th scope="col">Saldo</th><th scope="col">Status</th></tr></thead>
    <tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('credit-card') ?></span><h3>Nenhuma conta cadastrada</h3><p>Quando esta funcionalidade estiver disponível, seus dados aparecerão aqui. Nenhum registro foi criado.</p></div></td></tr></tbody>
  </table></div>
</section>
