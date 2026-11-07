<?php
declare(strict_types=1);

/** Contrato de composição e escape dos componentes compartilhados de relatórios. */
require dirname(__DIR__) . '/vendor/autoload.php';

use MovesCode\View\Engine;

$root = dirname(__DIR__) . '/container/app/realizze/v1';
$view = new Engine($root);
$view->addFolder('components', $root . '/components');

$heading = $view->render('components::report-heading', [
    'reportHeading' => [
        'id' => 'cash-title',
        'title' => '<script>Fluxo</script>',
        'description' => 'Previsto & realizado',
    ],
    'reportActions' => [
        ['label' => 'Exportar', 'href' => '/exportar?tipo=fluxo&formato=csv', 'download' => true],
        ['type' => 'button', 'label' => 'Gerar PDF', 'class' => 'primary', 'print' => true, 'entry_open' => 'income', 'disabled' => true, 'title' => 'Cadastre uma conta primeiro'],
    ],
]);

foreach ([
    'id="cash-title"', '&lt;script&gt;Fluxo&lt;/script&gt;', 'Previsto &amp; realizado',
    'aria-label="Ações do relatório"', 'href="/exportar?tipo=fluxo&amp;formato=csv"',
    'download', 'data-finance-print', 'data-entry-open="income"', 'disabled', 'title="Cadastre uma conta primeiro"',
] as $needle) {
    if (!str_contains($heading, $needle)) {
        throw new RuntimeException('Cabeçalho de relatório incompleto: ' . $needle);
    }
}
if (str_contains($heading, '<script>')) {
    throw new RuntimeException('Cabeçalho de relatório renderizou HTML não confiável.');
}

$period = $view->render('components::report-period-form', [
    'reportPeriodForm' => [
        'action' => '/minha-conta?pagina=dre&teste="x"',
        'start' => '2026-01-01',
        'end' => '2026-01-31',
        'hidden' => ['criterio' => 'competencia'],
    ],
]);
foreach ([
    'class="finance-filter-bar report-period-form"', 'data-swap-get',
    'action="/minha-conta?pagina=dre&amp;teste=&quot;x&quot;"',
    'name="inicio" value="2026-01-01"', 'name="fim" value="2026-01-31"',
    'name="criterio" value="competencia"', 'type="submit"',
] as $needle) {
    if (!str_contains($period, $needle)) {
        throw new RuntimeException('Formulário de período incompleto: ' . $needle);
    }
}

$css = (string) file_get_contents($root . '/assets/css/dashboard.css');
foreach (['--ds-color-brand:#079550', '--ds-space-7:28px', '.report-period-form', '@media(max-width:650px){.report-heading'] as $needle) {
    if (!str_contains($css, $needle)) {
        throw new RuntimeException('Contrato visual ausente: ' . $needle);
    }
}

$reports = (string) file_get_contents($root . '/pages/relatorios.php');
foreach (['$selectedReport', 'class="report-catalog no-print"', "['relatorio' => \$key]", 'report-detail report-billing', 'name="relatorio" value="ledger"'] as $needle) {
    if (!str_contains($reports, $needle)) {
        throw new RuntimeException('Central de relatórios sem navegação progressiva: ' . $needle);
    }
}
foreach (['.report-center.is-catalog .report-detail', '.report-center.report-selected-ledger .report-ledger', '@media(max-width:560px){.report-catalog'] as $needle) {
    if (!str_contains($css, $needle)) {
        throw new RuntimeException('Central de relatórios sem responsividade progressiva: ' . $needle);
    }
}

echo "OK - componentes de relatório compartilham composição, responsividade e escape.\n";
