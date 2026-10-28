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
    'class="avatar">&lt;</span>',
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
] as $page => $caption) {
    $html = $view->render('pages/' . $page, ['icon' => $icon, 'appView' => $view]);
    if (!str_contains($html, '<caption class="finance-table-caption">' . $caption)
        || !str_contains($html, '<th scope="col">')
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
    '&lt;script&gt;title&lt;/script&gt;', '&lt;b&gt;description&lt;/b&gt;', 'colspan="2"'] as $needle) {
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
