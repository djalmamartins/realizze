<?php
/** UI-005: área empresarial informativa; nenhum contexto PJ é ativado sem autorização no servidor. */
?>
<section class="card finance-workspace" aria-labelledby="enterprise-title">
  <div class="finance-toolbar"><div><h2 id="enterprise-title">Gestão empresarial</h2><p class="muted">Estrutura inicial para MEI e empresas. Sua conta pessoal permanece separada.</p></div><span class="preview-chip">Em desenvolvimento</span></div>
  <div class="enterprise-notice" role="status"><strong>Nenhuma empresa selecionada</strong><p>O cadastro e a alternância entre empresas serão liberados somente após a implementação de vínculos, permissões e isolamento de dados no servidor. Esta tela não exibe informações empresariais reais.</p></div>
  <div class="enterprise-kpis" aria-label="Indicadores empresariais sem dados">
<?php foreach (['Saldo disponível', 'A receber', 'A pagar', 'Resultado do mês'] as $metric): ?>
<article class="enterprise-kpi"><span><?= htmlspecialchars($metric, ENT_QUOTES, 'UTF-8') ?></span><strong>R$ 0,00</strong><small>Sem dados cadastrados</small></article>
<?php endforeach; ?>
</div>
<h3 class="enterprise-group-title">Módulos empresariais</h3>
<div class="enterprise-grid">
    <?php foreach ([
      ['Fluxo de caixa', 'Entradas, saídas e projeções por empresa'],
      ['Clientes e fornecedores', 'Cadastros e histórico de recebimentos e pagamentos'],
      ['Centros de custos', 'Classificação por projeto, área e unidade'],
      ['Relatórios e DRE', 'Demonstrativos gerenciais por período'],
      ['Equipe e permissões', 'Acessos específicos por organização'],
      ['Conciliação bancária', 'Importação e revisão de movimentações'],
      ['Obrigações fiscais', 'DAS, guias, comprovantes e calendário tributário'],
      ['Contas a pagar e receber', 'Vencimentos, parcelas e recorrências'],
    ] as $feature): ?>
      <article class="enterprise-feature"><h3><?= htmlspecialchars($feature[0], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($feature[1], ENT_QUOTES, 'UTF-8') ?></p><small>Planejado</small></article>
    <?php endforeach; ?>
  </div>
<div class="enterprise-obligations"><strong>Central de obrigações</strong><p>Nenhuma obrigação cadastrada. O acompanhamento fiscal será habilitado após a configuração do regime tributário e das permissões.</p></div>
</section>
