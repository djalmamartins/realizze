<?php
/**
 * ENT-027: layout empresarial em componentes MovesCode View.
 * Sem consultas financeiras, ações de cadastro ou troca de contexto por GET.
 */
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
$enterpriseGroups = [
    'Relacionamentos e equipe' => [
        ['id' => 'clientes', 'title' => 'Clientes', 'description' => 'Cadastro, contatos e histórico comercial.', 'icon' => 'building', 'columns' => ['Cliente', 'Documento', 'Situação', 'Ações']],
        ['id' => 'fornecedores', 'title' => 'Fornecedores', 'description' => 'Organização dos prestadores e obrigações.', 'icon' => 'building', 'columns' => ['Fornecedor', 'Documento', 'Categoria', 'Situação']],
        ['id' => 'equipes', 'title' => 'Equipe e permissões', 'description' => 'Vínculos, papéis e acesso por organização.', 'icon' => 'settings', 'columns' => ['Membro', 'Papel', 'Permissão', 'Situação']],
    ],
    'Gestão financeira' => [
        ['id' => 'receber', 'title' => 'Contas a receber', 'description' => 'Vencimentos e recebimentos da empresa.', 'icon' => 'arrow-down-left', 'columns' => ['Cliente', 'Vencimento', 'Situação', 'Valor']],
        ['id' => 'pagar', 'title' => 'Contas a pagar', 'description' => 'Compromissos e pagamentos previstos.', 'icon' => 'arrow-up-right', 'columns' => ['Fornecedor', 'Vencimento', 'Situação', 'Valor']],
        ['id' => 'bancos', 'title' => 'Bancos e cartões', 'description' => 'Contas e cartões vinculados à organização.', 'icon' => 'credit-card', 'columns' => ['Instituição', 'Conta', 'Tipo', 'Saldo']],
        ['id' => 'fluxo', 'title' => 'Fluxo de caixa', 'description' => 'Entradas, saídas e projeções por período.', 'icon' => 'arrows-left-right', 'columns' => ['Período', 'Entradas', 'Saídas', 'Resultado']],
        ['id' => 'custos', 'title' => 'Centros de custos', 'description' => 'Análise por projeto, área e unidade.', 'icon' => 'list-filter', 'columns' => ['Centro de custo', 'Responsável', 'Previsto', 'Realizado']],
    ],
    'Análises e obrigações' => [
        ['id' => 'dre', 'title' => 'Relatórios e DRE gerencial', 'description' => 'Indicadores e demonstrativos para gestão.', 'icon' => 'chart-no-axes-column', 'columns' => ['Período', 'Receitas', 'Despesas', 'Resultado']],
        ['id' => 'conciliacao', 'title' => 'Conciliação OFX/CSV', 'description' => 'Futura importação com conferência antes de salvar.', 'icon' => 'arrows-left-right', 'columns' => ['Arquivo', 'Conta', 'Período', 'Situação']],
        ['id' => 'obrigacoes', 'title' => 'Obrigações MEI/PJ', 'description' => 'Calendário e comprovantes, conforme regime validado.', 'icon' => 'calendar-days', 'columns' => ['Obrigação', 'Competência', 'Vencimento', 'Situação']],
    ],
];
?>
<section class="card finance-workspace enterprise-workspace" aria-labelledby="enterprise-title">
  <div class="finance-toolbar">
    <div><h2 id="enterprise-title">Gestão empresarial</h2><p class="muted">Um espaço para MEI e PJ, separado das finanças pessoais.</p></div>
    <span class="preview-chip">Prévia sem operações</span>
  </div>
  <?php if (!$enterpriseEnabled || $enterpriseUnavailable): ?>
    <div class="enterprise-notice" role="status"><strong>Cadastro empresarial ainda não liberado</strong><p>A ativação exige a validação das tabelas, vínculos e permissões em homologação. Nenhuma empresa ou movimentação foi cadastrada nesta página.</p></div>
  <?php else: ?>
    <div class="enterprise-notice" role="status"><strong><?= $enterpriseCurrent ? 'Empresa autorizada: ' . $escapeEnterprise($enterpriseCurrent['name']) : 'Contexto pessoal ativo' ?></strong><p><?= $enterpriseCurrent ? 'A empresa foi validada na sessão. Os módulos financeiros PJ ainda não estão conectados ao banco.' : 'Selecione uma empresa vinculada para preparar a área empresarial. A área pessoal continua independente.' ?></p></div>
    <?php if ($enterpriseOrganizations !== []): ?>
      <div class="enterprise-context-controls">
        <form method="post" action="/minha-conta/contexto">
          <input type="hidden" name="csrf_token" value="<?= $escapeEnterprise($csrf ?? '') ?>">
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
      <p class="enterprise-no-access muted">Nenhuma empresa com permissão de visualização está vinculada à sua conta.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?= $appView->render('components::enterprise-kpis') ?>
  <div class="enterprise-section-intro">
    <div><h3 id="enterprise-modules-title">Áreas de gestão</h3><p>Conheça os módulos planejados. Todas as tabelas abaixo são apenas estruturas visuais, sem dados de clientes ou empresas.</p></div>
    <span class="enterprise-readonly-label">Somente visualização</span>
  </div>
  <div class="enterprise-module-filter" role="search" aria-label="Filtrar áreas empresariais">
    <div class="enterprise-filter-field">
      <label for="enterprise-module-search">Localizar módulo</label>
      <input id="enterprise-module-search" type="search" autocomplete="off" placeholder="Ex.: clientes, fluxo de caixa, DRE" aria-controls="enterprise-module-groups" aria-describedby="enterprise-search-help">
    </div>
    <button id="enterprise-module-clear" type="button" class="enterprise-secondary">Limpar filtro</button>
    <p id="enterprise-search-help" class="muted">A pesquisa filtra apenas os módulos exibidos nesta página. Não consulta empresas ou lançamentos.</p>
    <p id="enterprise-search-status" class="muted" role="status" aria-live="polite" aria-atomic="true">11 módulos disponíveis.</p>
  </div>
  <div id="enterprise-search-empty" class="enterprise-search-empty" hidden aria-labelledby="enterprise-search-empty-title">
    <strong id="enterprise-search-empty-title">Nenhum módulo encontrado</strong>
    <p>Experimente outro termo ou use “Limpar filtro” para ver todas as áreas de gestão.</p>
  </div>
  <nav class="enterprise-jump" aria-label="Ir para módulo empresarial">
    <?php foreach ($enterpriseGroups as $modules): ?>
      <?php foreach ($modules as $module): ?>
        <a href="#enterprise-<?= $escapeEnterprise($module['id']) ?>"><?= $escapeEnterprise($module['title']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div id="enterprise-module-groups">
  <?php foreach ($enterpriseGroups as $group => $modules): ?>
    <section class="enterprise-module-group" aria-label="<?= $escapeEnterprise($group) ?>">
      <h3><?= $escapeEnterprise($group) ?></h3>
      <div class="enterprise-module-grid">
        <?php foreach ($modules as $module): ?>
          <?= $appView->render('components::enterprise-module-panel', ['module' => $module, 'icon' => $icon]) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
  <div class="enterprise-obligations"><strong>Isolamento PF/PJ</strong><p>Esta prévia não consulta nem altera lançamentos. O acesso futuro a cada módulo exigirá vínculo ativo, permissão por operação e consultas escopadas à organização validada no servidor. A importação OFX/CSV não está habilitada.</p></div>
</section>
