<?php /** UI-026: somente estados vazios, sem transações, instituições ou categorias inventadas. */ ?>
<section class="content-grid" aria-label="Painéis financeiros">
  <article class="card" id="movimentacoes"><div class="section-head"><h2>Últimas movimentações</h2><a href="/minha-conta?pagina=movimentacoes">Ver todas</a></div>
    <div class="overview-empty"><span class="overview-empty-icon" aria-hidden="true">↔</span><strong>Nenhuma movimentação disponível</strong><p>As entradas e saídas autorizadas aparecerão aqui após a integração.</p></div>
  </article>
  <article class="card" id="categorias"><div class="section-head"><h2>Gastos por categoria</h2><a href="/minha-conta?pagina=relatorios">Ver relatórios</a></div>
    <div class="overview-empty"><span class="overview-empty-icon" aria-hidden="true">◌</span><strong>Distribuição indisponível</strong><p>O gráfico será gerado somente com lançamentos reais do contexto autorizado.</p></div>
  </article>
  <article class="card" id="contas"><div class="section-head"><h2>Contas e cartões</h2><a href="/minha-conta?pagina=contas">Ver todas</a></div>
    <div class="overview-empty"><span class="overview-empty-icon" aria-hidden="true">▣</span><strong>Nenhuma conta disponível</strong><p>Contas e cartões não são consultados nesta prévia.</p></div>
  </article>
</section>
<section class="card insights" aria-label="Análises financeiras futuras"><span class="stat-icon" aria-hidden="true">✧</span><div><strong>Insights financeiros</strong><p>As análises serão exibidas quando houver dados reais, com autorização e separação entre PF e PJ.</p></div><button type="button" disabled title="Disponível após integração financeira">Ver insights</button></section>
