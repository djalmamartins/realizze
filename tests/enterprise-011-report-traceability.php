<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\ReportTrace;

putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=0');

$income = ReportTrace::entry(42, 'income', '2026-11-15');
$expense = ReportTrace::entry(43, 'expense', '2026-12-31');
$transfer = ReportTrace::entry(44, 'transfer', '2027-01-01');
if ($income !== '/p/receber/2026/11#lancamento-42'
    || $expense !== '/p/pagar/2026/12#lancamento-43'
    || $transfer !== '/p/movimentacoes/2027/01#lancamento-44') {
    throw new RuntimeException('ENT-011: URLs canônicas dos lançamentos divergentes.');
}
if (ReportTrace::serviceOrder(7) !== '/p/ordens-de-servico#ordem-7'
    || ReportTrace::obligation(8, '2026-01-01', '2026-12-31') !== '/p/obrigacoes?inicio=2026-01-01&fim=2026-12-31#obrigacao-8') {
    throw new RuntimeException('ENT-011: URLs operacionais divergentes.');
}
if (ReportTrace::entry(0, 'income', '2026-11-15') !== null
    || ReportTrace::entry(1, 'unknown', '2026-11-15') !== null
    || ReportTrace::entry(1, 'income', '2026-02-30') !== null
    || ReportTrace::obligation(1, '2026-12-31', '2026-01-01') !== null
    || ReportTrace::reference('LAN', 12) !== 'LAN-12'
    || ReportTrace::reference('=cmd', 12) !== '') {
    throw new RuntimeException('ENT-011: referência ou parâmetros inválidos não foram neutralizados.');
}

$root = dirname(__DIR__);
$reports = (string)file_get_contents($root . '/container/app/realizze/v1/pages/relatorios.php');
$orders = (string)file_get_contents($root . '/container/app/realizze/v1/pages/ordens-de-servico.php');
$obligations = (string)file_get_contents($root . '/container/app/realizze/v1/pages/obrigacoes.php');
$script = (string)file_get_contents($root . '/container/app/realizze/v1/assets/js/dashboard.js');
$styles = (string)file_get_contents($root . '/container/app/realizze/v1/assets/css/dashboard.css');
$exports = (string)file_get_contents($root . '/source/App/FinanceActions.php');
foreach (['ReportTrace::entry', 'ReportTrace::serviceOrder', 'ReportTrace::obligation'] as $needle) {
    if (!str_contains($reports, $needle)) throw new RuntimeException('ENT-011: relatório sem trilha ' . $needle);
}
if (!str_contains($orders, 'id="ordem-') || !str_contains($obligations, 'id="obrigacao-')
    || !str_contains($script, 'focusFragment(finalUrl.href)')
    || !str_contains($styles, '.finance-table tr[id]:focus')
    || substr_count($exports, "ReportTrace::reference('") < 7) {
    throw new RuntimeException('ENT-011: destino ou navegação AJAX sem suporte à trilha.');
}

echo 'OK - ENT-011: trilhas canônicas, referências exportáveis e fragmentos seguros validados.' . PHP_EOL;
