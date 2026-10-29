<?php
declare(strict_types=1);

/**
 * Smoke test de templates do App sem banco de dados ou sessão.
 * Não executa transações financeiras nem usa dados reais.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use MovesCode\View\Engine;

$root = dirname(__DIR__) . '/container/app/realizze/v1';
$view = new Engine($root);
$view->addFolder('components', $root . '/components');

$icon = static fn (string $name): string => '<svg aria-hidden="true" data-icon="' .
    htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"></svg>';
$user = ['first_name' => 'Teste'];
$items = [['house', 'Visão Geral', '/minha-conta?pagina=overview']];
$sections = ['overview' => ['Visão Geral', 'Resumo']];

putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=0');

$checks = [
    'sidebar' => [
        $view->render('components::sidebar', [
            'items' => $items,
            'sections' => $sections,
            'section' => 'overview',
            'icon' => $icon,
            'name' => 'Teste',
            'user' => $user,
            'csrf' => 'token-de-teste',
        ]),
        ['new-logo-realizze.svg', 'aria-current="page"', 'csrf_token'],
    ],
    'header' => [
        $view->render('components::dashboard-header', ['name' => 'Teste', 'user' => $user]),
        ['Sem dados integrados.', 'sidebar-toggle'],
    ],
    'summary' => [
        $view->render('components::overview-summary'),
        ['Saldo disponível', 'Fechamento previsto'],
    ],
    'panels' => [
        $view->render('components::overview-panels'),
        ['Últimas movimentações', 'Gastos por categoria'],
    ],
    'receber' => [
        $view->render('pages/receber', ['icon' => $icon, 'appView' => $view]),
        ['Contas a receber', 'Nenhum lançamento disponível', 'Nenhum valor demonstrativo'],
    ],
    'empresarial' => [
        $view->render('pages/empresarial', ['icon' => $icon, 'csrf' => 'token-de-teste', 'appView' => $view]),
        ['Gestão empresarial', 'Cadastro empresarial ainda não liberado', 'Nenhum registro consultado', 'Conciliação OFX/CSV', 'Centros de custos'],
    ],
    'pagar' => [
        $view->render('pages/pagar', ['icon' => $icon, 'appView' => $view]),
        ['Contas a pagar', 'Nenhum lançamento disponível', 'Nenhum valor demonstrativo'],
    ],
    'assistente' => [
        $view->render('pages/assistente', ['icon' => $icon]),
        ['Assistente Realizze', 'Nenhuma conversa iniciada', 'Nenhuma conversa ou movimentação será registrada', 'Integração de mensagens não habilitada'],
    ],
];

foreach ($checks as $name => [$html, $expected]) {
    foreach ($expected as $needle) {
        if (!str_contains($html, $needle)) {
            throw new RuntimeException("Falha ao renderizar {$name}: {$needle}");
        }
    }
    echo 'OK - ' . $name . PHP_EOL;
}
// APP-SEC-043: sidebar escapes profile text, navigation attributes and logout CSRF.
$maliciousSidebar = $view->render('components::sidebar', [
    'items' => [['house', '<script>menu</script>', '/minha-conta?pagina=overview&test="x"']],
    'sections' => $sections,
    'section' => 'overview',
    'icon' => $icon,
    'name' => '<img src=x onerror=alert(1)>',
    'user' => ['first_name' => '<'],
    'csrf' => '" onfocus="alert(1)',
]);
foreach ([
    '&lt;script&gt;menu&lt;/script&gt;',
    '&lt;img src=x onerror=alert(1)&gt;',
    'href="/minha-conta?pagina=overview&amp;test=&quot;x&quot;"',
    'value="&quot; onfocus=&quot;alert(1)"',
    'class="avatar" aria-hidden="true">&lt;</span>',
    'aria-label="Alternar tema"',
    'aria-label="Ajuda"',
] as $needle) {
    if (!str_contains($maliciousSidebar, $needle)) {
        throw new RuntimeException('APP-SEC-043: escape ou SVG ausente na sidebar: ' . $needle);
    }
}
foreach (['<script>menu</script>', '<img src=x onerror=alert(1)>', 'value="" onfocus='] as $unsafe) {
    if (str_contains($maliciousSidebar, $unsafe)) {
        throw new RuntimeException('APP-SEC-043: HTML não confiável renderizado na sidebar.');
    }
}
if (substr_count($maliciousSidebar, '<svg ') < 4) {
    throw new RuntimeException('APP-UI-043: ícones SVG da sidebar incompletos.');
}
echo 'OK - APP-SEC-043: sidebar escapa perfil, links e token CSRF.' . PHP_EOL;
$maliciousHeader = $view->render('components::dashboard-header', [
    'name' => '<img src=x onerror=alert(1)>',
    'user' => ['first_name' => 'Teste'],
]);
if (str_contains($maliciousHeader, '<img src=x onerror=alert(1)>')
    || !str_contains($maliciousHeader, '&lt;img src=x onerror=alert(1)&gt;')) {
    throw new RuntimeException('APP-SEC-043: saudação do dashboard não escapou o nome.');
}
echo 'OK - APP-SEC-043: saudação personalizada escapada.' . PHP_EOL;
// As telas iniciais não devem exibir saldos, transações ou bancos fictícios.
foreach (['header', 'summary', 'panels'] as $name) {
    $html = $checks[$name][0];
    if (preg_match('/R\\$\\s*[0-9]/u', $html) === 1) {
        throw new RuntimeException("UI-026: valor monetário fictício em {$name}");
    }
    foreach (['Agosto de 2026', 'Nubank', 'Visa Final 4242', 'Enel'] as $fixture) {
        if (str_contains($html, $fixture)) {
            throw new RuntimeException("UI-026: dado demonstrativo indevido em {$name}: {$fixture}");
        }
    }
}
// ENT-027: o módulo PJ deve ser navegação local sem troca de organização por GET.
$enterpriseHtml = $checks['empresarial'][0];
foreach (['clientes', 'fornecedores', 'equipes', 'receber', 'pagar', 'bancos', 'fluxo', 'custos', 'dre', 'conciliacao', 'obrigacoes'] as $moduleId) {
    if (!str_contains($enterpriseHtml, 'id="enterprise-' . $moduleId . '" tabindex="-1"')
        || !str_contains($enterpriseHtml, 'href="#enterprise-' . $moduleId . '"')) {
        throw new RuntimeException('ENT-027: módulo PJ sem âncora acessível: ' . $moduleId);
    }
}
if (substr_count($enterpriseHtml, 'class="enterprise-module"') !== 11
    || preg_match('/R\\$\\s*[0-9]/u', $enterpriseHtml) === 1
    || str_contains($enterpriseHtml, 'name="organization_id"')) {
    throw new RuntimeException('ENT-027: prévia PJ inconsistente ou seletor indevido com flag desligada.');
}
// APP-UI-035: client-side module search is accessible and never submits organization IDs.
foreach (['id="enterprise-module-search"', 'type="search"', 'aria-controls="enterprise-module-groups"',
    'id="enterprise-module-groups"', 'id="enterprise-module-clear"',
    'id="enterprise-search-status"', 'aria-live="polite"',
    'Não consulta empresas ou lançamentos'] as $needle) {
    if (!str_contains($enterpriseHtml, $needle)) {
        throw new RuntimeException('APP-UI-035: filtro PJ incompleto: ' . $needle);
    }
}
// APP-A11Y-054: sem JavaScript, a busca não funcional fica oculta; os módulos permanecem visíveis.
if (!str_contains($enterpriseHtml, 'aria-label="Filtrar áreas empresariais" hidden')
    || !str_contains($enterpriseHtml, 'id="enterprise-module-clear" type="button" class="enterprise-secondary" disabled')) {
    throw new RuntimeException('APP-A11Y-054: filtro progressivo ou botão inicial não configurado.');
}
echo 'OK - APP-A11Y-054: busca PJ progressiva, sem controles inoperantes quando JS está ausente.' . PHP_EOL;
// APP-UI-040: a busca tem estado vazio explícito e mantém controles acessíveis.
foreach (['id="enterprise-search-empty"', 'id="enterprise-search-empty-title"',
    'Nenhum módulo encontrado', 'hidden aria-labelledby="enterprise-search-empty-title"'] as $needle) {
    if (!str_contains($enterpriseHtml, $needle)) {
        throw new RuntimeException('APP-UI-040: estado vazio da busca PJ ausente: ' . $needle);
    }
}
if (preg_match('/<form\\b/i', $enterpriseHtml) === 1) {
    throw new RuntimeException('APP-UI-035: formulário inesperado com contexto empresarial desabilitado.');
}
// APP-SEC-042: both organization-context POST forms must HTML-escape the CSRF token.
$enterpriseSource = file_get_contents($root . '/pages/empresarial.php');
if (!is_string($enterpriseSource)
    || substr_count($enterpriseSource, '$escapeEnterprise($csrf ?? \'\')') !== 2
    || str_contains($enterpriseSource, 'value="<?= $csrf ?? \'\' ?>"')) {
    throw new RuntimeException('APP-SEC-042: token CSRF sem escape em formulário empresarial.');
}
echo 'OK - APP-SEC-042: tokens CSRF escapados nos dois formulários empresariais.' . PHP_EOL;
// APP-UI-040: verify that the client-only filter toggles the empty state and supports Escape.
$enterpriseJs = file_get_contents(dirname(__DIR__) . '/container/app/realizze/v1/assets/js/dashboard.js');
$enterpriseCss = file_get_contents(dirname(__DIR__) . '/container/app/realizze/v1/assets/css/dashboard.css');
if (!is_string($enterpriseJs) || !is_string($enterpriseCss)
    || !str_contains($enterpriseJs, "enterpriseEmpty.hidden = visible !== 0")
    || !str_contains($enterpriseJs, "event.key !== 'Escape'")
    || !str_contains($enterpriseCss, '.enterprise-search-empty[hidden]{display:none!important}')) {
    throw new RuntimeException('APP-UI-040: estado vazio ou limpeza por teclado não configurados.');
}
echo 'OK - APP-UI-040: estado vazio e limpeza por Escape configurados.' . PHP_EOL;
// APP-A11Y-043: off-canvas mobile links are inert while the menu is closed.
if (!str_contains($enterpriseJs, 'sidebar.inert = !desktopViewport.matches')
    || !str_contains($enterpriseJs, 'updateSidebarInert();')
    || !str_contains($enterpriseJs, 'sidebar?.contains(document.activeElement)')) {
    throw new RuntimeException('APP-A11Y-043: sidebar móvel permite foco fora da tela.');
}
echo 'OK - APP-A11Y-043: menu móvel remove links ocultos da ordem de foco.' . PHP_EOL;
echo 'OK - APP-UI-035: busca PJ local acessível e sem submissão de dados.' . PHP_EOL;
echo 'OK - ENT-027: onze módulos PJ acessíveis, sem valores inventados e sem seleção indevida.' . PHP_EOL;
echo 'OK - UI-026: sem valores financeiros fictícios na visão geral.' . PHP_EOL;
echo 'UI-002: templates renderizados com MovesCode View.' . PHP_EOL;

// APP-UI-044: shared financial tables have captions and escape dynamic metadata.
foreach ([
    'receber' => 'Lançamentos a receber',
    'pagar' => 'Lançamentos a pagar',
    'movimentacoes' => 'Movimentações',
    'categorias' => 'Categorias',
    'metas' => 'Metas financeiras',
    'contas' => 'Contas e carteiras',
    'relatorios' => 'Relatórios financeiros',
    'planejamento' => 'Planejamento financeiro',
] as $page => $caption) {
    $html = $view->render('pages/' . $page, ['icon' => $icon, 'appView' => $view]);
    if (!str_contains($html, '<caption class="finance-table-caption">' . $caption)
        || !str_contains($html, '<th scope="col">')
        || !str_contains($html, 'role="region" tabindex="0" aria-label="')
        || !str_contains($html, 'Nenhum')
        || preg_match('/R\\$\\s*[0-9]/u', $html) === 1) {
        throw new RuntimeException('APP-UI-044: tabela PF inconsistente: ' . $page);
    }
}
$unsafeTable = $view->render('components::finance-empty-table', [
    'financeEmptyTable' => [
        'caption' => '<script>caption</script>',
        'columns' => ['<img src=x onerror=alert(1)>', 'Valor'],
        'title' => '<script>title</script>',
        'description' => '<b>description</b>',
    ],
    'icon' => $icon,
]);
foreach (['&lt;script&gt;caption&lt;/script&gt;', '&lt;img src=x onerror=alert(1)&gt;',
    '&lt;script&gt;title&lt;/script&gt;', '&lt;b&gt;description&lt;/b&gt;', 'colspan="2"',
    'role="region" tabindex="0" aria-label="&lt;script&gt;caption&lt;/script&gt;"'] as $needle) {
    if (!str_contains($unsafeTable, $needle)) {
        throw new RuntimeException('APP-UI-044: escape/estrutura de tabela ausente: ' . $needle);
    }
}
if (str_contains($unsafeTable, '<script>') || str_contains($unsafeTable, '<img src=x')) {
    throw new RuntimeException('APP-UI-044: HTML não confiável interpretado na tabela.');
}
echo 'OK - APP-UI-044: tabelas PF acessíveis e metadados escapados.' . PHP_EOL;

// APP-SEC-044: values remain raw until the sidebar/header HTML sinks escape them.
$dashboardSource = file_get_contents($root . '/pages/dashboard.php');
if (!is_string($dashboardSource)
    || !str_contains($dashboardSource, '$name = (string) (')
    || !str_contains($dashboardSource, '$csrf = \\Moves\\Services\\Csrf::token();')
    || str_contains($dashboardSource, '$name = htmlspecialchars(')
    || str_contains($dashboardSource, '$csrf = htmlspecialchars(')) {
    throw new RuntimeException('APP-SEC-044: dashboard escapando dados antes dos componentes.');
}
echo 'OK - APP-SEC-044: nome e token CSRF escapados somente no HTML.' . PHP_EOL;

// APP-UI-045: Contas has two separate, labelled tables; reports and planning each have one.
$accountsHtml = $view->render('pages/contas', ['icon' => $icon, 'appView' => $view]);
if (substr_count($accountsHtml, '<caption class="finance-table-caption">') !== 2
    || !str_contains($accountsHtml, 'Cartões de crédito — integração pendente')) {
    throw new RuntimeException('APP-UI-045: contas e cartões sem tabelas acessíveis independentes.');
}
foreach (['relatorios', 'planejamento'] as $page) {
    $html = $view->render('pages/' . $page, ['icon' => $icon, 'appView' => $view]);
    if (substr_count($html, '<caption class="finance-table-caption">') !== 1) {
        throw new RuntimeException('APP-UI-045: tabela duplicada ou ausente: ' . $page);
    }
}
echo 'OK - APP-UI-045: contas/cartões, relatórios e planejamento com tabelas reutilizáveis.' . PHP_EOL;


// APP-UI-048: settings preview has accessible fields and no live mutations.
$settingsHtml = $view->render('pages/configuracoes', ['icon' => $icon, 'appView' => $view]);
foreach (['settings-profile-title', 'settings-security-title', 'settings-notifications-title',
    'settings-privacy-title', 'settings-preview-help', 'finance-settings-grid',
    'Nenhuma configuração, sessão, notificação ou informação financeira'] as $needle) {
    if (!str_contains($settingsHtml, $needle)) {
        throw new RuntimeException('APP-UI-048: configuração PF incompleta: ' . $needle);
    }
}
preg_match_all('/<(?:button|input|select|textarea)\\b[^>]*>/i', $settingsHtml, $settingsControls);
foreach ($settingsControls[0] as $control) {
    if (preg_match('/\\bdisabled\\b/i', $control) !== 1) {
        throw new RuntimeException('APP-UI-048: controle de configuração habilitado: ' . $control);
    }
}
if (str_contains($settingsHtml, '<form') || preg_match('/R\\$\\s*[0-9]/u', $settingsHtml) === 1
    || substr_count($settingsHtml, 'id="settings-preview-help"') !== 1) {
    throw new RuntimeException('APP-UI-048: formulário, valores fictícios ou ID duplicado.');
}
$settingsCss = file_get_contents($root . '/assets/css/dashboard.css');
if (!is_string($settingsCss) || !str_contains($settingsCss, '.finance-settings-grid')
    || !str_contains($settingsCss, '@media(max-width:650px){.finance-settings-grid')) {
    throw new RuntimeException('APP-UI-048: estilos responsivos de configurações ausentes.');
}
echo 'OK - APP-UI-048: configurações PF responsivas, sem mutações ou dados inventados.' . PHP_EOL;

// APP-A11Y-055: no fictitious transactions, chat messages or out-of-scope channel actions.
$assistantHtml = $checks['assistente'][0];
if (preg_match('/R\\$\\s*[0-9]/u', $assistantHtml) === 1
    || str_contains($assistantHtml, 'assistant-message-user')
    || stripos($assistantHtml, 'WhatsApp') !== false
    || str_contains($assistantHtml, '<form')) {
    throw new RuntimeException('APP-A11Y-055: assistente exibe dados fictícios ou ação fora de escopo.');
}
preg_match_all('/<(?:button|input|select|textarea)\\b[^>]*>/i', $assistantHtml, $assistantControls);
foreach ($assistantControls[0] as $control) {
    if (preg_match('/\\bdisabled\\b/i', $control) !== 1) {
        throw new RuntimeException('APP-A11Y-055: controle do assistente habilitado: ' . $control);
    }
}
$dashboardLayout = file_get_contents($root . '/pages/dashboard.php');
if (!is_string($dashboardLayout) || !str_contains($dashboardLayout, '<main class="main" id="overview" tabindex="-1">')) {
    throw new RuntimeException('APP-A11Y-055: destino do link de pular navegação não é focável.');
}
echo 'OK - APP-A11Y-055: assistente sem dados fictícios, canais ou operações; skip link focável.' . PHP_EOL;

// APP-A11Y-056: fallback mobile must not hide the navigation when JS is disabled.
if (!str_contains($dashboardLayout, '<noscript>')
    || !str_contains($dashboardLayout, '@media(max-width:850px)')
    || !str_contains($dashboardLayout, '.sidebar{position:static;transform:none;')
    || !str_contains($dashboardLayout, '.sidebar-close,.sidebar-toggle,.sidebar-backdrop{display:none!important}')
    || !str_contains($dashboardLayout, 'JavaScript desativado: o menu permanece visível.')
    || !str_contains($dashboardLayout, 'Prévia sem envio ou registro de movimentações')
    || !str_contains($checks['sidebar'][0], 'id="app-sidebar"')
    || !str_contains($checks['sidebar'][0], 'action="/sair"')) {
    throw new RuntimeException('APP-A11Y-056: navegação sem JavaScript ou indicação da prévia ausente.');
}
echo 'OK - APP-A11Y-056: menu móvel acessível sem JS e assistente descrito como prévia.' . PHP_EOL;

// APP-A11Y-057: all eleven PJ tables expose visible captions; no raw HTML injection.
if (substr_count($enterpriseHtml, '<caption class="enterprise-module-caption">') !== 11
    || !str_contains($enterpriseHtml, 'Estrutura de Clientes — prévia sem dados')) {
    throw new RuntimeException('APP-A11Y-057: legendas de tabelas PJ ausentes.');
}
$unsafeModule = $view->render('components::enterprise-module-panel', [
    'module' => [
        'id' => 'unsafe',
        'title' => '<img src=x onerror=alert(1)>',
        'description' => '<script>alert(1)</script>',
        'icon' => 'building',
        'columns' => ['Empresa & nome'],
    ],
    'icon' => $icon,
]);
if (str_contains($unsafeModule, '<img src=x') || str_contains($unsafeModule, '<script>')
    || !str_contains($unsafeModule, '&lt;img src=x')
    || !str_contains($unsafeModule, '&lt;script&gt;')
    || !str_contains($unsafeModule, 'Empresa &amp; nome')) {
    throw new RuntimeException('APP-A11Y-057: tabela PJ não escapou os metadados.');
}
echo 'OK - APP-A11Y-057: onze tabelas PJ com legendas visíveis e escape seguro.' . PHP_EOL;

// APP-A11Y-064: initials are complete Unicode graphemes, not partial UTF-8 bytes.
foreach ([
    ['Álvaro', 'Á'],
    ['Érica', 'É'],
    ['👩‍💻 Maria', '👩‍💻'],
    ['', 'R'],
    ["\xC3\x28", 'R'],
] as [$firstName, $initial]) {
    $profile = ['first_name' => $firstName];
    $expectedAvatar = '<span class="avatar" aria-hidden="true">' . $initial . '</span>';
    $sidebarHtml = $view->render('components::sidebar', [
        'items' => $items, 'sections' => $sections, 'section' => 'overview',
        'icon' => $icon, 'name' => $firstName, 'user' => $profile, 'csrf' => 'test-token',
    ]);
    $headerHtml = $view->render('components::dashboard-header', [
        'name' => $firstName, 'user' => $profile,
    ]);
    if (!str_contains($sidebarHtml, $expectedAvatar) || !str_contains($headerHtml, $expectedAvatar)) {
        throw new RuntimeException('APP-A11Y-064: inicial Unicode inválida ou avatar inacessível.');
    }
}
echo 'OK - APP-A11Y-064: avatar compartilhado preserva grafemas e fallback UTF-8.' . PHP_EOL;

// APP-A11Y-065: PF/PJ tables must explain horizontal scrolling without enabling actions.
foreach (['receber', 'pagar', 'movimentacoes', 'contas', 'relatorios', 'planejamento', 'metas', 'categorias'] as $page) {
    $html = $view->render('pages/' . $page, ['icon' => $icon, 'appView' => $view]);
    if (!str_contains($html, 'class="finance-scroll-hint"')
        || !str_contains($html, 'Com teclado, foque esta região e use as setas.')) {
        throw new RuntimeException('APP-A11Y-065: orientação de rolagem ausente em ' . $page);
    }
}
if (substr_count($enterpriseHtml, 'class="enterprise-scroll-hint"') !== 11) {
    throw new RuntimeException('APP-A11Y-065: orientação de rolagem PJ ausente em algum módulo.');
}
$scrollCss = file_get_contents($root . '/assets/css/dashboard.css');
if (!is_string($scrollCss)
    || !str_contains($scrollCss, '@media(max-width:850px){.finance-scroll-hint,.enterprise-scroll-hint{display:block}}')
    || !str_contains($scrollCss, '.enterprise-module-table-wrap:focus-visible>.enterprise-scroll-hint')) {
    throw new RuntimeException('APP-A11Y-065: orientação responsiva/foco das tabelas ausente.');
}
echo 'OK - APP-A11Y-065: tabelas PF/PJ com orientação de rolagem responsiva.' . PHP_EOL;

// APP-A11Y-066: a tall mobile menu must remain scrollable by touch and within dynamic viewport.
if (!str_contains($scrollCss, 'body.app-menu-locked{overflow:hidden;overscroll-behavior:contain}')
    || str_contains($scrollCss, 'body.app-menu-locked{overflow:hidden;touch-action:none}')
    || !str_contains($scrollCss, 'height:100dvh;')
    || !str_contains($scrollCss, 'touch-action:pan-y;')
    || !str_contains($scrollCss, 'env(safe-area-inset-bottom, 0px)')
    || !str_contains($scrollCss, '.profile strong,.page-heading h1,.nav a{overflow-wrap:anywhere}')) {
    throw new RuntimeException('APP-A11Y-066: rolagem do menu ou quebra de textos longos indisponível.');
}
echo 'OK - APP-A11Y-066: sidebar móvel rolável por toque, viewport dinâmico e nomes longos.' . PHP_EOL;

// APP-A11Y-067: navigation stays reachable if the JS bundle fails to load.
// The off-canvas layout must only apply after the script marks a complete shell.
if (!str_contains($scrollCss, '.shell:not(.app-js) .sidebar{')
    || !str_contains($scrollCss, 'max-height:none;')
    || !str_contains($scrollCss, '.shell:not(.app-js) .sidebar-toggle,')
    || !str_contains($scrollCss, '.shell:not(.app-js) .sidebar-backdrop{display:none!important}')) {
    throw new RuntimeException('APP-A11Y-067: fallback de navegação sem JS incompleto.');
}
$mobileScript = file_get_contents($root . '/assets/js/dashboard.js');
if (!is_string($mobileScript)
    || !str_contains($mobileScript, "const navigationReady = Boolean(desktopViewport);")
    || !str_contains($mobileScript, "typeof window.matchMedia === 'function'")
    || !str_contains($mobileScript, "typeof candidate.addListener === 'function'")
    || !str_contains($mobileScript, "if (navigationReady) shell.classList.add('app-js');")
    || !str_contains($mobileScript, "if (navigationReady) sidebar.inert =")
    || !str_contains($mobileScript, "if (navigationReady) {")) {
    throw new RuntimeException('APP-A11Y-067: ativação progressiva do menu não encontrada.');
}
echo 'OK - APP-A11Y-067: navegação PF/PJ permanece visível se o JS não carregar.' . PHP_EOL;
