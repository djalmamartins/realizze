<?php
declare(strict_types=1);
// UI-001: demonstração visual. Nenhum valor financeiro representa dados do usuário.
$user = \Moves\Services\SessionAuth::current();
if ($user === null) {
    http_response_code(403);
    exit('Acesso negado.');
}
$name = (string) ($user['first_name'] ?? 'Cliente');
$csrf = \Moves\Services\Csrf::token();
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
    'assistente' => ['Assistente Financeiro', 'Prévia sem envio ou registro de movimentações'],
    'configuracoes' => ['Configurações', 'Personalize sua experiência'],
    'empresarial' => ['Empresarial', 'Gestão financeira para MEI e empresas'],
];
$requestedSection = $_GET['pagina'] ?? 'overview';
$section = is_string($requestedSection) && isset($sections[$requestedSection]) ? $requestedSection : 'overview';
$appView = new \MovesCode\View\Engine(dirname(__DIR__));
$appView->addFolder('components', dirname(__DIR__) . '/components');
$items = [
    ['house','Visão Geral','/minha-conta?pagina=overview'],['arrow-down-left','Receber','/minha-conta?pagina=receber'],['arrow-up-right','Pagar','/minha-conta?pagina=pagar'],
    ['arrows-left-right','Movimentações','/minha-conta?pagina=movimentacoes'],['credit-card','Contas e Cartões','/minha-conta?pagina=contas'],
    ['chart-no-axes-column','Relatórios','/minha-conta?pagina=relatorios'],['calendar-days','Planejamento','/minha-conta?pagina=planejamento'],
    ['target','Metas','/minha-conta?pagina=metas'],['list-filter','Categorias','/minha-conta?pagina=categorias'],['message-circle','Assistente Financeiro','/minha-conta?pagina=assistente'],['settings','Configurações','/minha-conta?pagina=configuracoes'],['building','Empresarial','/minha-conta?pagina=empresarial']
];
$iconPaths = [
    'house' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'arrow-down-left' => '<path d="M17 7 7 17M7 7v10h10"/>',
    'arrow-up-right' => '<path d="M7 17 17 7M7 7h10v10"/>',
    'arrows-left-right' => '<path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/>',
    'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'chart-no-axes-column' => '<path d="M5 20v-6M10 20V9M15 20V4M20 20v-9"/>',
    'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
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
<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg">
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
<body>
<a class="app-skip-link" href="#overview">Ir para o conteúdo principal</a>
<noscript><p class="app-nojs-note">JavaScript desativado: o menu permanece visível. Filtros interativos não estão disponíveis nesta prévia.</p></noscript>
<div class="shell" id="shell">
<?= $appView->render('components::sidebar', [
    'items' => $items,
    'sections' => $sections,
    'section' => $section,
    'icon' => $icon,
    'name' => $name,
    'user' => $user,
    'csrf' => $csrf,
 ]) ?>
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
<main class="main" id="overview" tabindex="-1">
<?php if ($section === 'overview'): ?>
<?= $appView->render('components::dashboard-header', ['name' => $name, 'user' => $user, 'appView' => $appView]) ?>
<?= $appView->render('components::getting-started', ['icon' => $icon]) ?>
<?= $appView->render('components::overview-summary') ?>
<?= $appView->render('components::overview-panels') ?>
<?php else: ?>
<div class="section-topbar"><button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
<?= $appView->render('components::page-heading', [
    'pageHeading' => [
        'title' => $sections[$section][0],
        'description' => $sections[$section][1],
        'context' => $section === 'empresarial' ? 'enterprise' : 'personal',
    ],
    'appView' => $appView,
]) ?>
<?php if (in_array($section, ['receber', 'pagar', 'movimentacoes', 'contas', 'relatorios', 'planejamento', 'metas', 'categorias', 'assistente', 'configuracoes', 'empresarial'], true)): ?>
<?= $appView->render('pages/' . $section, ['icon' => $icon, 'csrf' => $csrf, 'appView' => $appView]) ?>
<?php else: ?>
<section class="card section-workspace"><div class="workspace-icon"><?= $icon($items[array_search($section, array_keys($sections), true)][0]) ?></div><h2><?= htmlspecialchars($sections[$section][0], ENT_QUOTES, 'UTF-8') ?></h2><p>Esta área está pronta para receber os recursos e dados financeiros do Realizze. Nenhuma informação financeira foi cadastrada.</p><div class="workspace-actions"><a href="/minha-conta?pagina=overview" class="primary">Voltar ao painel</a></div></section>
<?php endif; ?>
<?php endif; ?>
<p class="muted" style="text-align:center;margin-top:20px">Prévia de interface · Dados financeiros ainda não integrados · Nenhuma movimentação real é exibida</p>
</main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<?php if (is_file($publishedJs)): ?>
<script src="/assets/realizze-app/v1/js/dashboard.min.js?v=<?= (int) filemtime($publishedJs) ?>" defer></script>
<?php else: ?>
<script><?= file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js') ?></script>
<?php endif; ?>
</body>
</html>
