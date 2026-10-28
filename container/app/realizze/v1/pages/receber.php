<section class="card finance-workspace" aria-labelledby="receber-title">
  <div class="finance-toolbar">
    <div><h2 id="receber-title">Contas a receber</h2><p class="muted">Organize entradas, vencimentos e recebimentos futuros.</p></div>
    <button type="button" class="primary" disabled title="Disponível após integração financeira">+ Novo recebimento</button>
  </div>
  <div class="finance-filters" aria-label="Filtros de recebimentos">
    <span class="filter-chip selected">Todos</span><span class="filter-chip">Pendentes</span><span class="filter-chip">Recebidos</span><span class="filter-chip">Vencidos</span>
  </div>
  <div class="finance-table-wrap">
    <table class="finance-table"><thead><tr><th scope="col">Descrição</th><th scope="col">Vencimento</th><th scope="col">Categoria</th><th scope="col">Status</th><th scope="col">Valor</th></tr></thead><tbody><tr><td colspan="5"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('arrow-down-left') ?></span><h3>Nenhum recebimento cadastrado</h3><p>Quando a gestão de receitas estiver disponível, seus lançamentos aparecerão aqui.</p></div></td></tr></tbody></table>
  </div>
</section>
