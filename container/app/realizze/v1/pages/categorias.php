<?php
/** Prévia de Categorias; sem dados financeiros persistidos. */
?>
<section class="card finance-workspace" aria-labelledby="categorias-title">
  <div class="finance-toolbar"><div><h2 id="categorias-title">Categorias</h2><p class="muted">Organize suas receitas e despesas por categoria.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova categoria</button></div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Categoria</th><th scope="col">Tipo</th><th scope="col">Movimentações</th><th scope="col">Valor</th></tr></thead><tbody><tr><td colspan="4"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('list-filter') ?></span><h3>Nenhuma categoria cadastrada</h3><p>Esta página está preparada para receber os dados reais da sua conta. Nenhum lançamento de demonstração será salvo.</p></div></td></tr></tbody></table></div>
</section>
