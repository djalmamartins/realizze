<?php
declare(strict_types=1);

/** Motor determinístico do Sair das Dívidas: sem banco, sem rede. */
require_once dirname(__DIR__) . '/source/Services/DebtPlanner.php';

use Moves\Services\DebtPlanner;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$start = new DateTimeImmutable('2026-12-01');

// Exemplo do brief: renda 4.000, essenciais 3.200, sobra 800, dívida 100.000.
$cap = DebtPlanner::capacity(['net_income_cents' => 400000, 'essential_expenses_cents' => 320000, 'variable_expenses_cents' => 0], [['balance_cents' => 10000000]]);
$check($cap['free_cents'] === 80000, 'Sobra de R$ 800 calculada');
$check($cap['capacity_cents'] < 80000 && $cap['capacity_cents'] > 0, 'Não compromete a sobra inteira (capacidade R$ ' . number_format($cap['capacity_cents'] / 100, 2, ',', '.') . ')');
$check($cap['buffer_cents'] === 40000, 'Margem de 10% da renda para imprevistos');
$variable = DebtPlanner::capacity(['net_income_cents' => 400000, 'essential_expenses_cents' => 320000, 'variable_expenses_cents' => 0, 'income_stability' => 'variable']);
$check($variable['capacity_cents'] < $cap['capacity_cents'], 'Renda variável reduz a capacidade');
$check(DebtPlanner::capacity(['net_income_cents' => 300000, 'essential_expenses_cents' => 320000, 'variable_expenses_cents' => 0])['situation'] === 'deficit', 'Despesas maiores que a renda viram déficit');
$check(DebtPlanner::capacity(['net_income_cents' => 0, 'essential_expenses_cents' => 0, 'variable_expenses_cents' => 0])['situation'] === 'no_income', 'Sem renda informada');
$full = DebtPlanner::capacity(['net_income_cents' => 400000, 'essential_expenses_cents' => 200000, 'variable_expenses_cents' => 0, 'emergency_reserve_cents' => 600000]);
$check($full['reserve_contribution_cents'] === 0 && $full['capacity_cents'] === 160000, 'Com reserva completa, só a margem é descontada');

// 100 mil a 3% a.m. com R$ 340/mês: juros (R$ 3.000) superam o pagamento.
$big = DebtPlanner::simulate([['id' => 1, 'balance_cents' => 10000000, 'monthly_rate' => 3.0, 'monthly_payment_cents' => 0]], $cap['capacity_cents'], 'avalanche', $start);
$check(!$big['feasible'] && $big['reason'] === 'interest_exceeds_payment', 'Plano inviável é informado, sem projeção otimista');

// Juros: 1.000,00 a 2% a.m. com 200/mês.
$one = DebtPlanner::simulate([['id' => 1, 'balance_cents' => 100000, 'monthly_rate' => 2.0, 'monthly_payment_cents' => 0]], 20000, 'avalanche', $start);
$check($one['feasible'] && $one['months'] === 6, 'Quita R$ 1.000 a 2% a.m. em 6 meses com R$ 200');
$check($one['total_interest_cents'] > 0 && $one['total_paid_cents'] === 100000 + $one['total_interest_cents'], 'Total pago = saldo + juros');
$check($one['payoff_date'] === '2027-05', 'Data provável de quitação');
$zero = DebtPlanner::simulate([['id' => 1, 'balance_cents' => 120000, 'monthly_rate' => 0.0]], 10000, 'snowball', $start);
$check($zero['months'] === 12 && $zero['total_interest_cents'] === 0, 'Sem juros: 12 parcelas de R$ 100');

$debts = [
    ['id' => 'cartao', 'balance_cents' => 500000, 'monthly_rate' => 12.0, 'monthly_payment_cents' => 30000],
    ['id' => 'pessoal', 'balance_cents' => 200000, 'monthly_rate' => 3.0, 'monthly_payment_cents' => 15000],
    ['id' => 'loja', 'balance_cents' => 80000, 'monthly_rate' => 5.0, 'monthly_payment_cents' => 8000],
];
$cmp = DebtPlanner::compare($debts, 120000, $start);
$check($cmp['avalanche']['feasible'] && $cmp['snowball']['feasible'], 'Avalanche e bola de neve viáveis com R$ 1.200');
$check($cmp['avalanche']['total_interest_cents'] <= $cmp['snowball']['total_interest_cents'], 'Avalanche paga menos juros');
$check($cmp['recommended'] === 'avalanche', 'Recomenda a de menor custo');
$check(!$cmp['minimum']['feasible'], 'Só os mínimos não quitam o cartão a 12% a.m.');
$check($cmp['avalanche']['savings_cents'] === null, 'Economia não é inventada quando a base não quita');
$snow = array_column($cmp['snowball']['debts'], 'paid_off_month', 'id');
$check($snow['loja'] <= $snow['pessoal'], 'Bola de neve quita primeiro o menor saldo');
$below = DebtPlanner::simulate($debts, 40000, 'avalanche', $start);
$check(!$below['feasible'] && $below['reason'] === 'below_minimums' && $below['required_cents'] === 53000, 'Orçamento abaixo das parcelas é recusado');
$missing = DebtPlanner::simulate([['id' => 9, 'balance_cents' => 1000, 'monthly_rate' => null]], 1000, 'avalanche', $start);
$check(!$missing['feasible'] && $missing['reason'] === 'missing_rates' && $missing['skipped_ids'] === [9], 'Sem taxa informada, a dívida não é simulada');

// Pagamento parcial altera o saldo e o prazo.
$after = DebtPlanner::simulate([['id' => 1, 'balance_cents' => 60000, 'monthly_rate' => 2.0]], 20000, 'avalanche', $start);
$check($after['months'] < $one['months'], 'Saldo menor encurta o prazo');

// Parcelas e propostas.
$check(DebtPlanner::installment(100000, 0.0, 10) === 10000, 'Parcela sem juros');
$pmt = DebtPlanner::installment(100000, 2.0, 12);
$check($pmt === 9456, 'Parcela Price de R$ 1.000 em 12x a 2% = R$ 94,56');
$rate = DebtPlanner::impliedRate(100000, $pmt, 12);
$check($rate !== null && abs($rate - 2.0) < 0.01, 'Taxa implícita recupera 2% a.m.');
$prop = DebtPlanner::evaluateProposal(1000000, 100000, 10, 50000, 60000);
$check($prop['total_cents'] === 600000 && $prop['discount_cents'] === 400000 && $prop['fits_capacity'] === true, 'Proposta com desconto de R$ 4.000 cabe na capacidade');
$check($prop['implied_monthly_rate'] === 0.0, 'Proposta sem juros implícitos');
$tight = DebtPlanner::evaluateProposal(1000000, 0, 24, 60000, 50000);
$check($tight['fits_capacity'] === false && $tight['discount_cents'] < 0 && $tight['implied_monthly_rate'] > 0, 'Parcela acima da capacidade é sinalizada');
$check(abs(DebtPlanner::annualToMonthly(26.8242) - 2.0) < 0.001, 'Conversão de CET anual para mensal');
try {
    DebtPlanner::evaluateProposal(0, 0, 1, 1);
    $check(false, 'Saldo zero aceito');
} catch (InvalidArgumentException) {
    echo "OK - Proposta inválida recusada\n";
}
echo "Todos os testes do motor passaram.\n";
