<?php /** CMS access screen: visual preview only. */ ?>
<?php $cmsHeading = ['eyebrow' => 'Plataforma / Acesso', 'title' => 'Usuários e permissões', 'description' => 'Gerencie convites, perfis e acessos administrativos.', 'badge' => 'Prévia visual']; require __DIR__ . '/../components/page-heading.php'; ?>
<div class="cms-detail-layout"><section class="cms-card"><div class="cms-module-head"><div><h2>Equipe administrativa</h2><p>Área preparada para gestão de usuários autorizados.</p></div><button type="button" disabled>Convidar usuário</button></div><div class="cms-form-preview"><div><label class="cms-field-label" for="cms-user-name">Nome</label><input id="cms-user-name" class="cms-field" placeholder="Nome completo" disabled></div><div><label class="cms-field-label" for="cms-user-email">E-mail</label><input id="cms-user-email" class="cms-field" type="email" placeholder="E-mail corporativo" disabled></div><div><label class="cms-field-label" for="cms-user-role">Perfil</label><select id="cms-user-role" class="cms-field" disabled><option>Selecione um perfil</option></select></div></div><div class="cms-section-divider"></div><h2><span class="cms-list-heading-icon" aria-hidden="true"><?= $cmsIcon('users') ?></span>Usuários cadastrados</h2><?php // CMS-UI-081: filtros visuais, sem leitura ou alteração de identidades.
$cmsPreviewFilters = [
    'title' => 'Filtrar usuários administrativos',
    'description' => 'Pesquisa e classificação previstas. A prévia não consulta identidades, convites ou permissões.',
    'fields' => [
        ['id' => 'cms-users-filter-search', 'label' => 'Pesquisar usuário', 'type' => 'search', 'placeholder' => 'Nome ou e-mail'],
        ['id' => 'cms-users-filter-role', 'label' => 'Papel administrativo', 'type' => 'select', 'options' => ['Todos os papéis', 'Administrador', 'Editor', 'Leitor']],
        ['id' => 'cms-users-filter-status', 'label' => 'Situação do acesso', 'type' => 'select', 'options' => ['Todas as situações', 'Ativo', 'Convite pendente', 'Revogado']],
    ],
]; require __DIR__ . '/../components/preview-filters.php'; ?><?php $cmsEmptyTable = [
    'caption' => 'Usuários administrativos',
    'columns' => ['Nome', 'E-mail', 'Papel', 'Situação'],
    'title' => 'Nenhum usuário consultado',
    'description' => 'A prévia não consulta identidades, convites ou permissões reais.',
]; require __DIR__ . '/../components/empty-table.php'; ?></section><aside class="cms-card cms-detail-side"><h2>Políticas de acesso</h2><p>Convites e permissões serão controlados por papéis e auditoria.</p><?php $cmsStatusRows = [
    ['label' => 'Autenticação', 'status' => 'Pendente'],
    ['label' => 'Autorização', 'status' => 'Pendente'],
    ['label' => 'Auditoria', 'status' => 'Pendente'],
]; require __DIR__ . '/../components/status-list.php'; ?>
</aside></div><?php $cmsWorkflow = [
    'title' => 'Fluxo previsto de acesso administrativo',
    'steps' => [
        ['title' => 'Convite', 'description' => 'Solicitar acesso sem expor contas ou convites nesta prévia.'],
        ['title' => 'Identidade', 'description' => 'Validar autenticação administrativa e vínculo ativo.'],
        ['title' => 'Permissões', 'description' => 'Conceder somente papéis autorizados, com proteção CSRF.'],
        ['title' => 'Auditoria', 'description' => 'Registrar concessões, revogações e tentativas negadas.'],
    ],
]; require __DIR__ . '/../components/workflow-preview.php'; ?>
<aside class="cms-notice"><strong>Acesso protegido</strong>Não é possível convidar, alterar ou consultar usuários até a implementação da segurança administrativa.</aside>
