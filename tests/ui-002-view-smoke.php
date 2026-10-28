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
        $view->render('pages/receber', ['icon' => $icon]),
        ['Contas a receber', 'Nenhum lançamento disponível', 'Nenhum valor demonstrativo'],
    ],
    'empresarial' => [
        $view->render('pages/empresarial', ['icon' => $icon, 'csrf' => 'token-de-teste', 'appView' => $view]),
        ['Gestão empresarial', 'Cadastro empresarial ainda não liberado', 'Nenhum registro consultado', 'Conciliação OFX/CSV', 'Centros de custos'],
    ],
    'pagar' => [
        $view->render('pages/pagar', ['icon' => $icon]),
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
echo 'OK - APP-UI-035: busca PJ local acessível e sem submissão de dados.' . PHP_EOL;
echo 'OK - ENT-027: onze módulos PJ acessíveis, sem valores inventados e sem seleção indevida.' . PHP_EOL;
echo 'OK - UI-026: sem valores financeiros fictícios na visão geral.' . PHP_EOL;
echo 'UI-002: templates renderizados com MovesCode View.' . PHP_EOL;
