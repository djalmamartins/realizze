<?php /** CMS: prévia de planos e assinaturas, sem acesso a dados ou cobranças. */ ?>
<?php $cmsHeading = ['eyebrow' => 'Plataforma / Comercial', 'title' => 'Planos e assinaturas', 'description' => 'Estruture ofertas, recursos e ciclos de contratação sem executar cobranças.', 'badge' => 'Prévia visual']; require __DIR__ . '/../components/page-heading.php'; ?>
<div class="cms-detail-layout">
<section class="cms-card" aria-labelledby="cms-plans-title">
 <div class="cms-module-head"><div><h2 id="cms-plans-title">Catálogo de planos</h2><p>Configure as informações de apresentação antes de publicar ofertas.</p></div><button type="button" disabled>Novo plano</button></div>
 <div class="cms-form-preview">
  <div><label class="cms-field-label" for="cms-plan-name">Nome do plano</label><input class="cms-field" id="cms-plan-name" placeholder="Ex.: Nome comercial" disabled></div>
  <div><label class="cms-field-label" for="cms-plan-cycle">Ciclo</label><select class="cms-field" id="cms-plan-cycle" disabled><option>Selecione um ciclo</option></select></div>
  <div><label class="cms-field-label" for="cms-plan-audience">Público</label><select class="cms-field" id="cms-plan-audience" disabled><option>Pessoa física ou empresarial</option></select></div>
  <div><label class="cms-field-label" for="cms-plan-status">Publicação</label><select class="cms-field" id="cms-plan-status" disabled><option>Indisponível</option></select></div>
 </div>
 <div class="cms-section-divider"></div>
 <div class="cms-module-head"><div><h2>Ofertas cadastradas</h2><p>Os planos autorizados aparecerão aqui.</p></div></div>
 <?php // CMS-UI-081: catálogo somente visual, sem assinaturas ou cobrança.
$cmsPreviewFilters = [
    'title' => 'Filtrar catálogo de planos',
    'description' => 'Organização visual das ofertas previstas, sem consultar assinaturas, preços ou clientes.',
    'fields' => [
        ['id' => 'cms-plans-filter-search', 'label' => 'Pesquisar plano', 'type' => 'search', 'placeholder' => 'Nome do plano'],
        ['id' => 'cms-plans-filter-audience', 'label' => 'Público', 'type' => 'select', 'options' => ['Todos os públicos', 'Pessoa física', 'Empresarial']],
        ['id' => 'cms-plans-filter-cycle', 'label' => 'Ciclo', 'type' => 'select', 'options' => ['Todos os ciclos', 'Mensal', 'Anual']],
        ['id' => 'cms-plans-filter-status', 'label' => 'Situação', 'type' => 'select', 'options' => ['Todas as situações', 'Rascunho', 'Publicado', 'Arquivado']],
    ],
]; require __DIR__ . '/../components/preview-filters.php'; ?>
 <?php $cmsEmptyTable = [
    'caption' => 'Ofertas cadastradas',
    'columns' => ['Plano', 'Público', 'Ciclo', 'Situação'],
    'title' => 'Catálogo indisponível',
    'description' => 'Esta prévia não consulta preços, assinaturas ou informações de clientes.',
]; require __DIR__ . '/../components/empty-table.php'; ?>
</section>
<aside class="cms-card cms-detail-side" aria-labelledby="cms-plans-side-title"><h2 id="cms-plans-side-title">Assinaturas</h2><p>Visão administrativa preparada para os estados de contratação.</p>
 <?php $cmsStatusRows = [
    ['label' => 'Assinaturas ativas', 'status' => 'Não consultado'],
    ['label' => 'Renovações', 'status' => 'Não consultado'],
    ['label' => 'Pagamentos', 'status' => 'Desabilitados'],
]; require __DIR__ . '/../components/status-list.php'; ?>
<div class="cms-section-divider"></div><h2>Publicação segura</h2><p>Alterações em planos exigirão permissões administrativas, CSRF, trilha de auditoria e integração validada.</p><button type="button" disabled>Salvar plano</button>
</aside></div>
<?php $cmsWorkflow = [
    'title' => 'Fluxo previsto de planos e assinaturas',
    'steps' => [
        ['title' => 'Rascunho', 'description' => 'Definir público, recursos e condições sem cadastrar cobranças.'],
        ['title' => 'Revisão', 'description' => 'Validar oferta, transparência e regras de contratação.'],
        ['title' => 'Autorização', 'description' => 'Exigir papel administrativo, CSRF e trilha de auditoria.'],
        ['title' => 'Publicação futura', 'description' => 'Disponibilizar apenas após testes e liberação formal, sem cobrança automática.'],
    ],
]; require __DIR__ . '/../components/workflow-preview.php'; ?>
<aside class="cms-notice"><strong>Sem cobrança nesta etapa</strong>Nenhum plano foi publicado e nenhuma assinatura ou cobrança pode ser criada nesta prévia.</aside>
