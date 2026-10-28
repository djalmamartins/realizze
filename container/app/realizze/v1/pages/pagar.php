<section class="card finance-workspace" aria-labelledby="pagar-title">
  <div class="finance-toolbar">
    <div><h2 id="pagar-title">Contas a pagar</h2><p class="muted">Acompanhe compromissos, pagamentos e próximos vencimentos.</p></div>
    <button type="button" class="primary" disabled title="Disponível após integração financeira">+ Nova despesa</button>
  </div>
  <div class="finance-filters" aria-label="Filtros de despesas">
    <span class="filter-chip selected">Todas</span><span class="filter-chip">Pendentes</span><span class="filter-chip">Pagas</span><span class="filter-chip">Vencidas</span>
  </div>
  <div class="finance-table-wrap">
    <table class="finance-table"><thead><tr><th scope="col">Descrição</th><th scope="col">Vencimento</th><th scope="col">Categoria</th><th scope="col">Status</th><th scope="col">Valor</th></tr></thead><tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrow-up-right') ?></span><h3>Nenhuma despesa cadastrada</h3><p>Quando o cadastro de despesas estiver disponível, você poderá acompanhá-las por aqui.</p></div></td></tr></tbody></table>
  </div>
</section>
