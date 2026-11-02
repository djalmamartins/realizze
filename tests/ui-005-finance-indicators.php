<?php
declare(strict_types=1);

/** Contrato visual: os estados financeiros compartilham tokens e classes semânticas. */
$root = dirname(__DIR__) . '/container/app/realizze/v1';
$css = file_get_contents($root . '/assets/css/dashboard.css');
$required = [
    '--finance-realized:#16834a', '--finance-pending:#2563eb', '--finance-overdue:#c0392b',
    '--finance-forecast:#6654b8', '.finance-indicator.is-realized', '.finance-indicator.is-pending',
    '.finance-indicator.is-overdue', '.finance-indicator.is-forecast', '.finance-indicator.is-neutral',
];
foreach ($required as $token) {
    if (!str_contains($css, $token)) throw new RuntimeException('Token/classe ausente: ' . $token);
}
$sources = [
    'components/overview-live.php', 'components/finance-entries.php', 'components/receivables.php',
    'components/executive-panel.php', 'pages/movimentacoes.php', 'pages/contas.php',
    'pages/planejamento.php', 'pages/fluxo-caixa.php', 'pages/relatorios.php', 'pages/sair-das-dividas.php',
];
foreach ($sources as $file) {
    if (!str_contains(file_get_contents($root . '/' . $file), 'finance-indicator')) {
        throw new RuntimeException('Indicador semântico ausente em ' . $file);
    }
}
echo "OK - indicadores financeiros usam tokens semânticos compartilhados em PF e PJ.\n";
