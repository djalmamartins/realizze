<?php /** CMS-UI-048: configuração administrativa apenas visual, sem operações. */ ?>
<?php $cmsHeading = ['eyebrow' => 'Plataforma / Preferências', 'title' => 'Configurações', 'description' => 'Organize identidade, publicação e governança do CMS.', 'badge' => 'Prévia visual']; require __DIR__ . '/../components/page-heading.php'; ?>
<div class="cms-detail-layout">
  <section class="cms-card" aria-labelledby="cms-settings-identity-title">
    <div class="cms-module-head">
      <div><h2 id="cms-settings-identity-title">Identidade do site</h2><p>Os valores reais não são carregados nesta prévia.</p></div>
      <span class="cms-status">Somente visualização</span>
    </div>
    <div class="cms-form-preview">
      <div><label class="cms-field-label" for="cms-settings-name">Nome do site</label><input id="cms-settings-name" class="cms-field" placeholder="Nome público do site" disabled></div>
      <div><label class="cms-field-label" for="cms-settings-url">Domínio principal</label><input id="cms-settings-url" class="cms-field" placeholder="Domínio autorizado" disabled></div>
      <div><label class="cms-field-label" for="cms-settings-language">Idioma</label><select id="cms-settings-language" class="cms-field" disabled><option>Português (Brasil)</option></select></div>
    </div>
    <div class="cms-section-divider"></div>
    <h2 id="cms-settings-editorial-title">SEO e publicação</h2>
    <p>Metadados, indexação e aprovação editorial dependerão de autorização administrativa.</p>
    <div class="cms-form-preview">
      <div><label class="cms-field-label" for="cms-settings-description">Descrição institucional</label><input id="cms-settings-description" class="cms-field" placeholder="Descrição para mecanismos de busca" disabled></div>
      <div><label class="cms-field-label" for="cms-settings-indexing">Indexação</label><select id="cms-settings-indexing" class="cms-field" disabled><option>Não consultado</option></select></div>
    </div>
    <button type="button" disabled>Salvar preferências</button>
  </section>
  <aside class="cms-card cms-detail-side" aria-labelledby="cms-settings-governance-title">
    <h2 id="cms-settings-governance-title">Segurança e governança</h2>
    <p>Nenhuma condição abaixo representa o estado de segurança em produção.</p>
    <?php $cmsStatusRows = [
    ['label' => 'Autenticação administrativa', 'status' => 'Pendente'],
    ['label' => 'Autorização por papéis', 'status' => 'Pendente'],
    ['label' => 'CSRF nas operações', 'status' => 'Obrigatório'],
]; require __DIR__ . '/../components/status-list.php'; ?>
<div class="cms-section-divider"></div>
    <h2>Auditoria e privacidade</h2>
    <p>Alterações de configuração precisarão registrar autor, ação e resultado sem expor segredos.</p>
    <?php $cmsStatusRows = [
    ['label' => 'Trilha de auditoria', 'status' => 'Pendente'],
    ['label' => 'Retenção de eventos', 'status' => 'Não definida'],
]; require __DIR__ . '/../components/status-list.php'; ?>
</aside>
</div>
<?php $cmsWorkflow = [
    'title' => 'Fluxo previsto de alterações de configuração',
    'steps' => [
        ['title' => 'Solicitação', 'description' => 'Preparar a alteração sem carregar ou modificar preferências reais nesta prévia.'],
        ['title' => 'Validação', 'description' => 'Conferir domínio, metadados, impactos de publicação e valores permitidos.'],
        ['title' => 'Autorização', 'description' => 'Exigir sessão administrativa, papel adequado e token CSRF válido.'],
        ['title' => 'Registro e aplicação', 'description' => 'Auditar autor e resultado antes de aplicar qualquer mudança autorizada.'],
    ],
]; require __DIR__ . '/../components/workflow-preview.php'; ?>
<aside class="cms-notice"><strong>Configurações bloqueadas</strong>Nenhuma preferência é lida ou modificada nesta prévia. O CMS permanece restrito à visualização local até autenticação, CSRF, autorização por papéis e auditoria estarem prontos.</aside>
