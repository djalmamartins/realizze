<?php
/** ENT-003: seleção de empresa validada no servidor. Sem dados financeiros reais. */
$enterpriseEnabled = \Moves\Services\OrganizationContext::enabled();
$enterpriseOrganizations = [];
$enterpriseCurrent = null;
$enterpriseUnavailable = false;
if ($enterpriseEnabled) {
    try {
        $enterpriseCurrent = \Moves\Services\OrganizationContext::current();
        $enterpriseOrganizations = \Moves\Services\OrganizationAccess::accessibleForCurrentUser();
    } catch (\Throwable $exception) {
        error_log('Realizze: estrutura empresarial indisponível.');
        $enterpriseUnavailable = true;
        $enterpriseCurrent = null;
        $enterpriseOrganizations = [];
    }
}
$escapeEnterprise = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="card finance-workspace" aria-labelledby="enterprise-title">
  <div class="finance-toolbar"><div><h2 id="enterprise-title">Gestão empresarial</h2><p class="muted">Organize o financeiro do seu negócio sem misturar com a conta pessoal.</p></div><span class="preview-chip">Em desenvolvimento</span></div>
  <?php if (!$enterpriseEnabled || $enterpriseUnavailable): ?>
    <div class="enterprise-notice" role="status"><strong>Cadastro empresarial ainda não liberado</strong><p>A ativação exige a validação das tabelas, vínculos e permissões no ambiente de homologação. Nenhuma empresa ou movimentação foi cadastrada nesta página.</p></div>
  <?php else: ?>
    <div class="enterprise-notice" role="status"><strong><?= $enterpriseCurrent ? 'Empresa autorizada: ' . $escapeEnterprise($enterpriseCurrent['name']) : 'Contexto pessoal ativo' ?></strong><p><?= $enterpriseCurrent ? 'A empresa foi validada na sessão. Os módulos financeiros PJ ainda não estão conectados ao banco.' : 'Selecione uma empresa vinculada para preparar a área empresarial. A área pessoal continua independente.' ?></p></div>
    <?php if ($enterpriseOrganizations !== []): ?>
      <div class="enterprise-context-controls">
        <form method="post" action="/minha-conta/contexto">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?? '' ?>">
          <input type="hidden" name="context_type" value="organization">
          <label for="enterprise-organization">Empresa vinculada</label>
          <select name="organization_id" id="enterprise-organization" required>
            <option value="">Selecione uma empresa</option>
            <?php foreach ($enterpriseOrganizations as $organization): ?>
              <option value="<?= (int) $organization['id'] ?>" <?= $enterpriseCurrent !== null && (int) $enterpriseCurrent['id'] === (int) $organization['id'] ? 'selected' : '' ?>><?= $escapeEnterprise($organization['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Selecionar empresa</button>
        </form>
        <?php if ($enterpriseCurrent !== null): ?>
          <form method="post" action="/minha-conta/contexto">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?? '' ?>">
            <input type="hidden" name="context_type" value="personal">
            <button type="submit" class="enterprise-secondary">Voltar ao pessoal</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="muted">Nenhuma empresa com permissão de visualização está vinculada à sua conta.</p>
    <?php endif; ?>
  <?php endif; ?>
  <div class="enterprise-kpis" aria-label="Indicadores ainda não integrados">
    <?php foreach (['Saldo disponível', 'A receber', 'A pagar', 'Resultado do mês'] as $metric): ?>
      <article class="enterprise-kpi"><span><?= $escapeEnterprise($metric) ?></span><strong>—</strong><small>Aguardando integração</small></article>
    <?php endforeach; ?>
  </div>
  <h3 class="enterprise-group-title">Módulos empresariais</h3>
  <div class="enterprise-grid">
    <?php foreach ([
      ['Fluxo de caixa', 'Entradas, saídas e projeções por empresa'],
      ['Clientes e fornecedores', 'Cadastros e histórico financeiro'],
      ['Centros de custos', 'Classificação por projeto e unidade'],
      ['Relatórios e DRE', 'Demonstrativos gerenciais por período'],
      ['Equipe e permissões', 'Papéis, vínculos e auditoria'],
      ['Conciliação bancária', 'Importação e revisão de movimentações'],
      ['Obrigações fiscais', 'Guias, comprovantes e calendário tributário'],
      ['Contas a pagar e receber', 'Vencimentos, parcelas e recorrências'],
    ] as $feature): ?>
      <article class="enterprise-feature"><h3><?= $escapeEnterprise($feature[0]) ?></h3><p><?= $escapeEnterprise($feature[1]) ?></p><small>Planejado</small></article>
    <?php endforeach; ?>
  </div>
  <div class="enterprise-obligations"><strong>Central de obrigações</strong><p>O acompanhamento fiscal depende da configuração do regime tributário e de fontes de confirmação. Nenhum pagamento é presumido como realizado.</p></div>
</section>
