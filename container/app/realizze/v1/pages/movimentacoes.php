<?php
/** Prévia de Movimentações; sem dados financeiros persistidos. */
?>
<section class="card finance-workspace" aria-labelledby="movimentacoes-title">
  <div class="finance-toolbar"><div><h2 id="movimentacoes-title">Movimentações</h2><p class="muted">Acompanhe todas as entradas e saídas.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova movimentação</button></div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Data</th><th scope="col">Descrição</th><th scope="col">Categoria</th><th scope="col">Conta</th><th scope="col">Valor</th></tr></thead><tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrows-left-right') ?></span><h3>Nenhuma movimentação cadastrada</h3><p>Esta página está preparada para receber os dados reais da sua conta. Nenhum lançamento de demonstração será salvo.</p></div></td></tr></tbody></table></div>
</section>
