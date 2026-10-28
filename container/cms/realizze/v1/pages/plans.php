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
 <div class="cms-table-wrap"><table class="cms-table"><thead><tr><th scope="col">Plano</th><th scope="col">Público</th><th scope="col">Ciclo</th><th scope="col">Situação</th></tr></thead><tbody><tr><td colspan="4" class="cms-empty"><strong>Catálogo indisponível</strong><p>Esta prévia não consulta preços, assinaturas ou informações de clientes.</p></td></tr></tbody></table></div>
</section>
<aside class="cms-card cms-detail-side" aria-labelledby="cms-plans-side-title"><h2 id="cms-plans-side-title">Assinaturas</h2><p>Visão administrativa preparada para os estados de contratação.</p>
 <div class="cms-setting-row"><span>Assinaturas ativas</span><strong>Não consultado</strong></div>
 <div class="cms-setting-row"><span>Renovações</span><strong>Não consultado</strong></div>
 <div class="cms-setting-row"><span>Pagamentos</span><strong>Desabilitados</strong></div>
 <div class="cms-section-divider"></div><h2>Publicação segura</h2><p>Alterações em planos exigirão permissões administrativas, CSRF, trilha de auditoria e integração validada.</p><button type="button" disabled>Salvar plano</button>
</aside></div>
<aside class="cms-notice"><strong>Sem cobrança nesta etapa</strong>Nenhum plano foi publicado e nenhuma assinatura ou cobrança pode ser criada nesta prévia.</aside>
