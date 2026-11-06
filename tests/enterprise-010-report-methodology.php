<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\ReportMethodology;

$rows = ReportMethodology::all();
$expected = ['cash-flow','management-dre','billing','pay-receive','account-position','budget','service-orders','obligations'];
$ids = array_column($rows, 'id');

if ($ids !== $expected || count($ids) !== count(array_unique($ids))) {
    throw new RuntimeException('ENT-010: catálogo incompleto, fora de ordem ou com identificadores duplicados.');
}

foreach ($rows as $row) {
    foreach (['id','title','source','period','formula','limitations'] as $field) {
        if (!isset($row[$field]) || trim((string)$row[$field]) === '') {
            throw new RuntimeException("ENT-010: {$row['id']} sem {$field} documentado.");
        }
        if (str_contains((string)$row[$field], '<')) {
            throw new RuntimeException("ENT-010: {$row['id']} contém marcação não permitida em {$field}.");
        }
    }
}

$byId = array_column($rows, null, 'id');
if (!str_contains($byId['cash-flow']['formula'], 'saldo inicial')
    || !str_contains($byId['management-dre']['limitations'], 'não substitui DRE contábil')
    || !str_contains($byId['billing']['limitations'], 'nota fiscal')
    || !str_contains($byId['obligations']['limitations'], 'Não calcula tributos')) {
    throw new RuntimeException('ENT-010: critérios financeiros ou limites de conformidade divergentes.');
}

$template = file_get_contents(dirname(__DIR__) . '/container/app/realizze/v1/pages/relatorios.php');
$exportAction = file_get_contents(dirname(__DIR__) . '/source/App/FinanceActions.php');
$reviewGuide = file_get_contents(dirname(__DIR__) . '/docs/compliance/VALIDACAO-CONTABIL.md');
if (!is_string($template)
    || !str_contains($template, 'id="report-methodology-title"')
    || !str_contains($template, 'foreach ($methodology as $criterion)')
    || !str_contains($template, '$e($criterion[\'source\'])')
    || !str_contains($template, '$e($criterion[\'formula\'])')
    || !str_contains($template, '$e($criterion[\'limitations\'])')) {
    throw new RuntimeException('ENT-010: metodologia não está integrada com escape à Central de Relatórios.');
}
if (!is_string($exportAction)
    || !str_contains($exportAction, "relatorio'] ?? '') === 'metodologia'")
    || !str_contains($exportAction, 'ReportMethodology::all()')
    || !is_string($reviewGuide)
    || !str_contains($reviewGuide, 'parecer externo pendente')
    || !str_contains($reviewGuide, 'Profissional e CRC')) {
    throw new RuntimeException('ENT-010: pacote de revisão contábil incompleto.');
}

echo 'OK - ENT-010: metodologia documentada, exibida e exportável para revisão externa.' . PHP_EOL;
