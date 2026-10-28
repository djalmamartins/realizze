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
        ['Agosto de 2026', 'sidebar-toggle'],
    ],
    'summary' => [
        $view->render('components::overview-summary'),
        ['Saldo disponível', 'Fechamento previsto'],
    ],
    'panels' => [
        $view->render('components::overview-panels', ['transactions' => [], 'accounts' => []]),
        ['Últimas movimentações', 'Gastos por categoria'],
    ],
    'receber' => [
        $view->render('pages/receber', ['icon' => $icon]),
        ['Contas a receber', 'Nenhum recebimento cadastrado'],
    ],
    'empresarial' => [
        $view->render('pages/empresarial', ['icon' => $icon, 'csrf' => 'token-de-teste']),
        ['Gestão empresarial', 'Cadastro empresarial ainda não liberado', 'Aguardando integração'],
    ],
    'pagar' => [
        $view->render('pages/pagar', ['icon' => $icon]),
        ['Contas a pagar', 'Nenhuma despesa cadastrada'],
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
echo 'UI-002: templates renderizados com MovesCode View.' . PHP_EOL;
