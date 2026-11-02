<?php
declare(strict_types=1);
// UI-001: demonstração visual. Nenhum valor financeiro representa dados do usuário.
$user = \Moves\Services\SessionAuth::current();
if ($user === null) {
    \Moves\Services\AppError::render(403);
    return;
}
$name = (string) ($user['first_name'] ?? 'Cliente');
$csrf = \Moves\Services\Csrf::token();
try {
    $activeOrganization = \Moves\Services\OrganizationContext::current();
    $organizations = \Moves\Services\OrganizationContext::enabled() ? \Moves\Services\OrganizationAccess::accessibleForCurrentUser() : [];
} catch (\Throwable $exception) {
    error_log('Realizze: contexto empresarial indisponível.');
    $activeOrganization = null;
    $organizations = [];
}
$publishedAssets = BASE_PATH . '/public/assets/realizze-app/v1';
$publishedCss = $publishedAssets . '/css/dashboard.min.css';
$publishedJs = $publishedAssets . '/js/dashboard.min.js';
$sections = [
    'overview' => ['Visão Geral', 'Resumo das suas finanças'],
    'receber' => ['Receber', 'Acompanhe seus recebimentos'],
    'pagar' => ['Pagar', 'Organize suas contas a pagar'],
    'movimentacoes' => ['Movimentações', 'Consulte entradas e saídas'],
    'contas' => ['Contas e Cartões', 'Gerencie suas contas e cartões'],
    'relatorios' => ['Relatórios', 'Acompanhe seus resultados'],
    'planejamento' => ['Planejamento', 'Planeje seu futuro financeiro'],
    'metas' => ['Metas', 'Acompanhe seus objetivos'],
    'categorias' => ['Categorias', 'Organize seus lançamentos'],
    'contatos' => ['Clientes e Fornecedores', 'Gerencie seus contatos financeiros'],
    'clientes' => ['Clientes', 'Gerencie os clientes da empresa'],
    'fornecedores' => ['Fornecedores', 'Gerencie os fornecedores da empresa'],
    'sair-das-dividas' => ['Sair das Dívidas', 'Diagnóstico, plano de quitação e negociação'],
    'assistente' => ['Assistente Financeiro', 'Prévia sem envio ou registro de movimentações'],
    'configuracoes' => ['Configurações', 'Personalize sua experiência'],
    'empresarial' => ['Empresarial', 'Gestão financeira para MEI e empresas'],
    'notificacoes' => ['Notificações', 'O que precisa da sua atenção'],
    'integracoes' => ['Integrações', 'Conexões com bancos e serviços'],
    'perfil' => ['Meu perfil', 'Seus dados e a segurança da conta'],
    'equipe' => ['Equipe e Permissões', 'Quem acessa os dados da empresa'],
    'empresa' => ['Configurações da Empresa', 'Dados cadastrais da empresa'],
    'contato-detalhe' => [($_GET['papel'] ?? '') === 'supplier' ? 'Fornecedor' : 'Cliente', 'Dados e histórico financeiro'],
    'fluxo-de-caixa' => ['Fluxo de caixa', 'Previsto e realizado, separados'],
    'faturas' => ['Cartões e faturas', 'Limites, faturas e parcelas dos cartões'],
    'conciliacao' => ['Conciliação bancária', 'Extratos conferidos com os lançamentos'],
    'centros-de-custo' => ['Centros de custos', 'Resultados por área, projeto ou unidade'],
    'dre' => ['DRE gerencial', 'Resultado gerencial, sem valor de demonstração contábil'],
    'obrigacoes' => ['Obrigações PJ/MEI', 'Calendário conforme o enquadramento da empresa'],
    'recorrencias' => ['Assinaturas e recorrências', 'Despesas que se repetem e quanto custam'],
    'ordens-de-servico' => ['Ordens de serviço', 'Módulo opcional para empresas de serviços'],
    'permissoes' => ['Permissões', 'O que cada papel pode fazer na empresa'],
    'seguranca' => ['Segurança', 'Acesso, sessões e políticas da empresa'],
    'auditoria' => ['Auditoria', 'Registro das ações administrativas'],
];
$requestedSection = $_GET['pagina'] ?? 'overview';
$section = is_string($requestedSection) && isset($sections[$requestedSection]) ? $requestedSection : 'overview';
if ($section === 'contato-detalhe') {
    // Contato de outra empresa responde como inexistente.
    try {
        (new \Moves\Services\Contacts((int) \Moves\Services\FinanceContext::currentId()))->find((int) ($_GET['contato_id'] ?? 0));
    } catch (\InvalidArgumentException $exception) {
        \Moves\Services\AppError::render(404);
        return;
    }
}
$appView = new \MovesCode\View\Engine(dirname(__DIR__));
$appView->addFolder('components', dirname(__DIR__) . '/components');
$baseUrl = \Moves\Services\AppUrl::base($activeOrganization);
$items = [
    'overview' => ['house','Visão Geral',$baseUrl], 'receber' => ['arrow-down-left','Receber',$baseUrl . '/receber'],
    'pagar' => ['arrow-up-right','Pagar',$baseUrl . '/pagar'], 'movimentacoes' => ['arrows-left-right','Movimentações',$baseUrl . '/movimentacoes'],
];
if ($activeOrganization !== null) {
    $items['clientes'] = ['building','Clientes',$baseUrl . '/clientes'];
    $items['fornecedores'] = ['building','Fornecedores',$baseUrl . '/fornecedores'];
    foreach (['fluxo-de-caixa', 'faturas', 'conciliacao', 'centros-de-custo', 'dre', 'obrigacoes', 'recorrencias', 'ordens-de-servico', 'permissoes', 'seguranca', 'auditoria'] as $moduleSection) {
        $items[$moduleSection] = ['list-filter', $sections[$moduleSection][0], $baseUrl . '/' . $moduleSection];
    }
}
$items += [
    'contas' => ['credit-card','Contas e Cartões',$baseUrl . '/cartoes'], 'relatorios' => ['chart-no-axes-column','Relatórios',$baseUrl . '/relatorios'],
    'planejamento' => ['calendar-days','Planejamento',$baseUrl . '/planejamento'], 'metas' => ['target','Metas',$baseUrl . '/metas'],
    'categorias' => ['list-filter','Categorias',$baseUrl . '/categorias'], 'recorrencias' => ['repeat','Assinaturas e recorrências',$baseUrl . '/recorrencias'],
];
if ($activeOrganization === null) {
    $items['sair-das-dividas'] = ['target','Sair das Dívidas',$baseUrl . '/sair-das-dividas'];
}
// Navegação PF: preservar módulos e URLs existentes, alterando só a ordem.
if ($activeOrganization === null) {
    $priority = ['overview', 'receber', 'pagar', 'movimentacoes', 'recorrencias', 'metas', 'planejamento', 'contas', 'relatorios', 'sair-das-dividas'];
    $ordered = [];
    foreach ($priority as $key) {
        if (isset($items[$key])) {
            $ordered[$key] = $items[$key];
        }
    }
    $items = $ordered + $items;
}
$items['integracoes'] = ['arrows-left-right','Integrações',$baseUrl . '/integracoes'];
$items += $activeOrganization !== null
    ? ['equipe' => ['building','Equipe e Permissões',$baseUrl . '/equipe'], 'empresa' => ['settings','Configurações da Empresa',$baseUrl . '/empresa']]
    : ['configuracoes' => ['settings','Configurações',$baseUrl . '/configuracoes']];
// Menu da empresa em grupos. "Em breve" aponta para uma página que diz o que falta, sem dados simulados.
$pendingSections = ['faturas', 'conciliacao', 'centros-de-custo', 'dre', 'obrigacoes', 'ordens-de-servico', 'seguranca', 'auditoria'];
$navGroups = null;
if ($activeOrganization !== null) {
    $items['overview'][1] = 'Dashboard';
    $items['receber'][1] = 'Receber e cobranças';
    $items['contas'][1] = 'Bancos e contas';
    $items['relatorios'][1] = 'Relatórios';
    $items['equipe'][1] = 'Equipe';
    $items['empresa'][1] = 'Configurações';
    $items['notificacoes'] = ['list-filter', 'Notificações', $baseUrl . '/notificacoes'];
    $items['suporte'] = ['message-circle', 'Ajuda e chamados', '/suporte'];
    $navGroups = [
        ['Financeiro', 'arrows-left-right', ['receber', 'pagar', 'movimentacoes', 'recorrencias', 'fluxo-de-caixa', 'contas', 'faturas', 'conciliacao']],
        ['Cadastros', 'building', ['clientes', 'fornecedores', 'categorias', 'centros-de-custo']],
        ['Gestão', 'chart-no-axes-column', ['relatorios', 'dre', 'planejamento', 'metas', 'obrigacoes', 'ordens-de-servico']],
        ['Administração', 'settings', ['equipe', 'permissoes', 'empresa', 'seguranca', 'auditoria']],
        ['Plataforma', 'message-circle', ['integracoes', 'notificacoes', 'suporte']],
    ];
    // Esconder é só conveniência: as rotas e as ações validam a permissão de novo.
    $navPermission = ['overview' => 'finance.view', 'receber' => 'finance.view', 'pagar' => 'finance.view', 'movimentacoes' => 'finance.view', 'recorrencias' => 'finance.view', 'fluxo-de-caixa' => 'finance.view', 'contas' => 'accounts.view', 'clientes' => 'contacts.view', 'fornecedores' => 'contacts.view', 'categorias' => 'finance.view', 'relatorios' => 'reports.view', 'planejamento' => 'planning.view', 'metas' => 'planning.view'];
    $ownPermissions = \Moves\Services\OrganizationTeam::permissionsFor((int) $user['id'], (int) $activeOrganization['id']);
    foreach ($navGroups as $groupIndex => $group) {
        $navGroups[$groupIndex][2] = array_values(array_filter($group[2], static fn (string $key): bool => !isset($navPermission[$key]) || in_array($navPermission[$key], $ownPermissions, true)));
    }
}
$iconPaths = [
    'house' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'arrow-down-left' => '<path d="M17 7 7 17M7 7v10h10"/>',
    'arrow-up-right' => '<path d="M7 17 17 7M7 7h10v10"/>',
    'arrows-left-right' => '<path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/>',
    'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'chart-no-axes-column' => '<path d="M5 20v-6M10 20V9M15 20V4M20 20v-9"/>',
    'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    'repeat' => '<path d="m17 2 4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4M21 13v2a3 3 0 0 1-3 3H3"/>',
    'list-filter' => '<path d="M4 7h16M7 12h10M10 17h4"/>',
    'message-circle' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3 1.7-5.1A8 8 0 1 1 21 15Z"/>',
    'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h1M15 7h1M8 11h1M15 11h1"/>',
    'settings' => '<path d="M12 2v3M12 19v3M4.93 4.93l2.12 2.12m9.9 9.9 2.12 2.12M2 12h3m14 0h3M4.93 19.07l2.12-2.12m9.9-9.9 2.12-2.12"/><circle cx="12" cy="12" r="4"/>',
];
$icon = static function (string $name) use ($iconPaths): string {
    $paths = $iconPaths[$name] ?? '';
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#079550">
<?php
$personalFavicon = '/assets/realizze/v1/images/favicon-realizze.svg';
$personalLogo = '/assets/realizze/v1/images/new-logo-realizze.svg';
$enterpriseLogo = '/assets/realizze/v1/images/new-logo-realizze-empresa.svg';
$assetVersion = (string) max(
    (int) @filemtime(BASE_PATH . '/public' . $personalFavicon),
    (int) @filemtime(BASE_PATH . '/public/assets/realizze/v1/images/favicon-realizze-empresa.svg'),
    (int) @filemtime(BASE_PATH . '/public' . $personalLogo),
    (int) @filemtime(BASE_PATH . '/public' . $enterpriseLogo),
);
$personalFavicon .= '?v=' . $assetVersion;
$enterpriseFavicon = is_file(BASE_PATH . '/public/assets/realizze/v1/images/favicon-realizze-empresa.svg')
    ? '/assets/realizze/v1/images/favicon-realizze-empresa.svg?v=' . $assetVersion
    : $personalFavicon;
$activeFavicon = $activeOrganization !== null ? $enterpriseFavicon : $personalFavicon;
?>
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($activeFavicon, ENT_QUOTES, 'UTF-8') ?>" data-app-favicon>
<title><?= htmlspecialchars($sections[$section][0], ENT_QUOTES, 'UTF-8') ?> | Realizze</title>
<?php if (is_file($publishedCss)): ?>
<link rel="stylesheet" href="/assets/realizze-app/v1/css/dashboard.min.css?v=<?= (int) filemtime($publishedCss) ?>">
<?php else: ?>
<style><?= file_get_contents(dirname(__DIR__) . '/assets/css/dashboard.css') ?></style>
<?php endif; ?>
<noscript>
<style>
/* APP-A11Y-056: on small screens keep navigation reachable without JavaScript. */
.app-nojs-note{margin:0;padding:12px 18px;background:#eaf7ef;color:#145c3a}
@media(max-width:850px){
  .shell{display:block}
  .sidebar{position:static;transform:none;width:auto;height:auto;overflow:visible;padding:20px 18px}
  .sidebar-close,.sidebar-toggle,.sidebar-backdrop{display:none!important}
}
</style>
</noscript>
</head>
<body data-context="<?= $activeOrganization !== null ? 'pj' : 'pf' ?>" data-organization-id="<?= (int) ($activeOrganization['id'] ?? 0) ?>" data-logo-pf="<?= htmlspecialchars($personalLogo . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>" data-logo-pj="<?= htmlspecialchars($enterpriseLogo . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
<a class="app-skip-link" href="#overview">Ir para o conteúdo principal</a>
<noscript><p class="app-nojs-note">JavaScript desativado: o menu permanece visível. Filtros interativos não estão disponíveis nesta prévia.</p></noscript>
<div class="shell" id="shell">
<?= $appView->render('components::sidebar', [
    'items' => $items,
    'sections' => $sections,
    'section' => $section === 'contato-detalhe' ? (($_GET['papel'] ?? '') === 'supplier' ? 'fornecedores' : 'clientes') : $section,
    'icon' => $icon,
    'name' => $name,
    'user' => $user,
    'csrf' => $csrf,
    'activeOrganization' => $activeOrganization,
    'organizations' => $organizations,
    'contextEnabled' => \Moves\Services\OrganizationContext::enabled(),
    'navGroups' => $navGroups,
    'pendingSections' => $pendingSections,
 ]) ?>
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
<main class="main" id="overview" tabindex="-1">
<?php if ($section === 'overview'): ?>
<?= $appView->render('components::dashboard-header', ['name' => $name, 'user' => $user, 'appView' => $appView]) ?>
<?php if ($activeOrganization !== null): ?><?= $appView->render('components::executive-panel') ?><?php endif; ?>
<?= $appView->render('components::overview-live', ['icon' => $icon, 'csrf' => $csrf, 'appView' => $appView]) ?>
<?php else: ?>
<div class="section-topbar"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
<?= $appView->render('components::page-heading', [
    'pageHeading' => [
        'section' => $section,
        'title' => $sections[$section][0],
        'description' => $sections[$section][1],
        'context' => $activeOrganization !== null || $section === 'empresarial' ? 'enterprise' : 'personal',
        'live' => in_array($section, ['permissoes', 'receber', 'sair-das-dividas', 'pagar', 'contas', 'movimentacoes', 'relatorios', 'planejamento', 'metas', 'categorias', 'recorrencias', 'contatos', 'clientes', 'fornecedores', 'perfil', 'equipe', 'empresa', 'notificacoes', 'integracoes', 'contato-detalhe', 'fluxo-de-caixa', 'faturas', 'conciliacao', 'centros-de-custo', 'dre', 'obrigacoes', 'ordens-de-servico', 'permissoes', 'seguranca', 'auditoria'], true),
    ],
    'appView' => $appView,
    'name' => $name,
    'user' => $user,
]) ?>
<?php if (in_array($section, ['receber', 'sair-das-dividas', 'pagar', 'movimentacoes', 'contas', 'relatorios', 'planejamento', 'metas', 'categorias', 'recorrencias', 'contatos', 'clientes', 'fornecedores', 'perfil', 'equipe', 'empresa', 'notificacoes', 'integracoes', 'assistente', 'configuracoes', 'empresarial', 'fluxo-de-caixa', 'permissoes', 'contato-detalhe'], true)): ?>
<?= $appView->render('pages/' . (in_array($section, ['clientes', 'fornecedores'], true) ? 'contatos' : ($section === 'fluxo-de-caixa' ? 'fluxo-caixa' : $section)), ['icon' => $icon, 'csrf' => $csrf, 'appView' => $appView]) ?>
<?php elseif (in_array($section, $pendingSections, true)): ?>
<?= $appView->render('components::module-pending', ['section' => $section, 'icon' => $icon, 'title' => $sections[$section][0]]) ?>
<?php else: ?>
<section class="card section-workspace"><div class="workspace-icon"><?= $icon($items[$section][0]) ?></div><h2><?= htmlspecialchars($sections[$section][0], ENT_QUOTES, 'UTF-8') ?></h2><p>Esta área está pronta para receber os recursos e dados financeiros do Realizze. Nenhuma informação financeira foi cadastrada.</p><div class="workspace-actions"><a href="/minha-conta?pagina=overview" class="primary">Voltar ao painel</a></div></section>
<?php endif; ?>
<?php endif; ?>
<?php if (!in_array($section, ['overview', 'permissoes', 'receber', 'sair-das-dividas', 'pagar', 'contas', 'movimentacoes', 'relatorios', 'planejamento', 'metas', 'categorias', 'contatos', 'clientes', 'fornecedores', 'perfil', 'equipe', 'empresa', 'notificacoes', 'integracoes', 'contato-detalhe', 'fluxo-de-caixa', 'faturas', 'conciliacao', 'centros-de-custo', 'dre', 'obrigacoes', 'recorrencias', 'ordens-de-servico', 'permissoes', 'seguranca', 'auditoria'], true)): ?>
<p class="muted" style="text-align:center;margin-top:20px">Prévia de interface · Dados financeiros ainda não integrados · Nenhuma movimentação real é exibida</p>
<?php endif; ?>
</main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<?php require dirname(__DIR__) . '/components/support-panel.php'; ?>
<?php if (is_file($publishedJs)): ?>
<script src="/assets/realizze-app/v1/js/dashboard.min.js?v=<?= (int) filemtime($publishedJs) ?>" defer></script>
<?php else: ?>
<script><?= file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js') ?></script>
<?php endif; ?>
</body>
</html>
