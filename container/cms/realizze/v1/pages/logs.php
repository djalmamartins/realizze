<?php /** CMS: prévia da trilha de auditoria, sem leitura de eventos privados. */ ?>
<?php $cmsHeading = ['eyebrow' => 'Plataforma / Segurança', 'title' => 'Logs e auditoria', 'description' => 'Consulte futuramente alterações administrativas e eventos de acesso.', 'badge' => 'Prévia visual']; require __DIR__ . '/../components/page-heading.php'; ?>
<div class="cms-detail-layout">
<section class="cms-card" aria-labelledby="cms-audit-title">
 <div class="cms-module-head"><div><h2 id="cms-audit-title">Histórico de eventos</h2><p>Filtros de auditoria disponíveis após autenticação e autorização.</p></div><button type="button" disabled>Exportar registros</button></div>
 <div class="cms-form-preview">
  <div><label class="cms-field-label" for="cms-audit-search">Pesquisar evento</label><input class="cms-field" id="cms-audit-search" type="search" placeholder="Ação ou identificador" disabled></div>
  <div><label class="cms-field-label" for="cms-audit-type">Tipo de evento</label><select class="cms-field" id="cms-audit-type" disabled><option>Todos os tipos</option></select></div>
  <div><label class="cms-field-label" for="cms-audit-date">Período</label><select class="cms-field" id="cms-audit-date" disabled><option>Selecione um período</option></select></div>
  <div><label class="cms-field-label" for="cms-audit-actor">Responsável</label><input class="cms-field" id="cms-audit-actor" placeholder="Usuário autorizado" disabled></div>
 </div>
 <div class="cms-section-divider"></div>
 <div class="cms-table-wrap"><table class="cms-table"><thead><tr><th scope="col">Data</th><th scope="col">Ação</th><th scope="col">Responsável</th><th scope="col">Origem</th><th scope="col">Resultado</th></tr></thead><tbody><tr><td colspan="5" class="cms-empty"><strong>Nenhum evento consultado</strong><p>O CMS não acessa logs privados nesta prévia de layout.</p></td></tr></tbody></table></div>
</section>
<aside class="cms-card cms-detail-side" aria-labelledby="cms-audit-side-title"><h2 id="cms-audit-side-title">Política de auditoria</h2><p>Eventos sensíveis devem ser registrados no servidor, sem exposição de credenciais ou dados pessoais desnecessários.</p>
 <div class="cms-setting-row"><span>Autenticação administrativa</span><strong>Pendente</strong></div>
 <div class="cms-setting-row"><span>Autorização por papel</span><strong>Pendente</strong></div>
 <div class="cms-setting-row"><span>Integridade e retenção</span><strong>Pendente</strong></div>
 <div class="cms-setting-row"><span>Exportação de eventos</span><strong>Bloqueada</strong></div>
 <div class="cms-section-divider"></div><h2>Eventos planejados</h2><p>Login, alterações de conteúdo, permissões, configurações e tentativas de acesso negadas.</p>
</aside></div>
<aside class="cms-notice"><strong>Auditoria protegida</strong>Nenhum evento administrativo é exibido sem autenticação, autorização e política de retenção implementadas.</aside>
