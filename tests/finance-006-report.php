<?php
declare(strict_types=1);

/** Relatório do período: agrupamento por dia e totais. Não acessa o banco. */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\Finance;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$row = static fn (string $kind, int $amount, string $due, ?string $paid = null, string $status = 'pending', int $settled = 0, ?string $category = null): array => [
    'kind' => $kind, 'amount_cents' => $amount, 'due_date' => $due, 'paid_at' => $paid, 'status' => $status,
    'settled_cents' => $settled, 'category' => $category, 'category_color' => null,
];

$report = Finance::buildReport([
    $row('income', 100000, '2026-11-02', '2026-11-03', 'received'),
    $row('expense', 30000, '2026-11-02', '2026-11-02', 'pending', 0, 'Casa'),
    $row('expense', 10000, '2026-11-05', null, 'pending', 0, 'Casa'),
    $row('income', 50000, '2026-11-05', null, 'partially_received', 20000),
    $row('transfer', 70000, '2026-11-05', '2026-11-05'),
    $row('expense', 99900, '2026-11-20', null, 'cancelled'),
    $row('expense', 5000, '2026-11-20', null, 'pending', 0, null),
], '2026-11-10');

$totals = $report['totals'];
$check($report['count'] === 7 && count($report['days']) === 3, 'lista todos os lançamentos, agrupados por dia');
$check($totals['income'] === 150000 && $totals['income_paid'] === 120000, 'receita soma baixa parcial no recebido');
$check($totals['expense'] === 45000 && $totals['expense_paid'] === 30000, 'cancelado e transferência não somam');
$check($totals['income_overdue'] === 30000 && $totals['expense_overdue'] === 10000, 'atraso considera só o saldo em aberto vencido');
$check($report['days'][0]['result'] === 70000 && $report['days'][1]['accumulated'] === 110000 && $report['days'][2]['accumulated'] === 105000, 'resultado do dia e acumulado');
$check($report['days'][2]['entries'][0]['cancelled'] && !$report['days'][2]['entries'][0]['overdue'], 'cancelado continua na lista e não fica vencido');
$check($report['categories'][0]['name'] === 'Casa' && $report['categories'][0]['total'] === 40000 && $report['categories'][1]['name'] === 'Sem categoria', 'categorias ordenadas por valor');
$check(Finance::buildReport([], '2026-11-10')['days'] === [], 'período vazio');
