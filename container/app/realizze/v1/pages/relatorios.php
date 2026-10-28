<?php
/** Prévia de Relatórios; sem dados financeiros persistidos. */
?>
<section class="card finance-workspace" aria-labelledby="relatorios-title">
  <div class="finance-toolbar"><div><h2 id="relatorios-title">Relatórios</h2><p class="muted">Visualize a evolução das suas finanças.</p></div><button type="button" class="primary" disabled title="Disponível após integração financeira">+ Exportar relatório</button></div>
  <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th scope="col">Período</th><th scope="col">Relatório</th><th scope="col">Formato</th><th scope="col">Situação</th></tr></thead><tbody><tr><td colspan="4"><div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('chart-no-axes-column') ?></span><h3>Nenhum relatório disponível</h3><p>Esta página está preparada para receber os dados reais da sua conta. Nenhum lançamento de demonstração será salvo.</p></div></td></tr></tbody></table></div>
</section>
