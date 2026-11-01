<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Motor financeiro determinístico do módulo Sair das Dívidas.
 *
 * Não acessa banco nem serviços externos: recebe números e devolve números.
 * Todas as hipóteses usadas ficam explícitas no resultado, para a tela (e uma
 * eventual camada de IA) explicarem os valores sem inventá-los.
 *
 * Valores em centavos (int). Taxas em % ao mês (float, ex.: 2.5 = 2,5% a.m.).
 */
final class DebtPlanner
{
    public const MAX_MONTHS = 360;

    /** Margem para imprevistos, em fração da renda líquida. */
    public const BUFFER_STABLE = 0.10;
    public const BUFFER_VARIABLE = 0.20;

    /**
     * Capacidade de pagamento sustentável.
     *
     * @param array{net_income_cents:int,essential_expenses_cents:int,variable_expenses_cents:int,other_commitments_cents?:int,emergency_reserve_cents?:int,income_stability?:string} $profile
     * @param list<array{balance_cents:int,monthly_payment_cents?:?int}> $debts
     * @return array<string,mixed>
     */
    public static function capacity(array $profile, array $debts = []): array
    {
        $income = max(0, (int) $profile['net_income_cents']);
        $essential = max(0, (int) $profile['essential_expenses_cents']);
        $variable = max(0, (int) $profile['variable_expenses_cents']);
        $other = max(0, (int) ($profile['other_commitments_cents'] ?? 0));
        $reserve = max(0, (int) ($profile['emergency_reserve_cents'] ?? 0));
        $stable = ($profile['income_stability'] ?? 'stable') !== 'variable';

        $free = $income - $essential - $variable - $other;
        $bufferRate = $stable ? self::BUFFER_STABLE : self::BUFFER_VARIABLE;
        $buffer = (int) round($income * $bufferRate);

        // Reserva mínima recomendada: 3 meses de despesas essenciais (6 se a renda varia).
        $reserveMonths = $stable ? 3 : 6;
        $reserveTarget = $essential * $reserveMonths;
        $reserveShare = $reserve >= $reserveTarget ? 0.0 : ($reserve >= $essential ? 0.10 : 0.15);
        $afterBuffer = max(0, $free - $buffer);
        $reserveContribution = (int) round($afterBuffer * $reserveShare);
        $capacity = max(0, $afterBuffer - $reserveContribution);

        $currentPayments = 0;
        $total = 0;
        foreach ($debts as $debt) {
            $total += max(0, (int) $debt['balance_cents']);
            $currentPayments += max(0, (int) ($debt['monthly_payment_cents'] ?? 0));
        }

        if ($income <= 0) {
            $situation = 'no_income';
        } elseif ($free <= 0) {
            $situation = 'deficit';
        } elseif ($capacity <= 0) {
            $situation = 'no_margin';
        } elseif ($currentPayments > $capacity) {
            $situation = 'over_committed';
        } else {
            $situation = 'ok';
        }

        return [
            'net_income_cents' => $income,
            'essential_expenses_cents' => $essential,
            'variable_expenses_cents' => $variable,
            'other_commitments_cents' => $other,
            'free_cents' => $free,
            'buffer_cents' => $buffer,
            'buffer_rate' => $bufferRate,
            'reserve_cents' => $reserve,
            'reserve_target_cents' => $reserveTarget,
            'reserve_contribution_cents' => $reserveContribution,
            'capacity_cents' => $capacity,
            'current_payments_cents' => $currentPayments,
            'total_debt_cents' => $total,
            'income_commitment' => $income > 0 ? $currentPayments / $income : null,
            'situation' => $situation,
            'assumptions' => [
                sprintf('Margem para imprevistos de %d%% da renda líquida (%s).', (int) round($bufferRate * 100), $stable ? 'renda estável' : 'renda variável'),
                sprintf('Reserva mínima recomendada: %d meses de despesas essenciais.', $reserveMonths),
                $reserveShare > 0
                    ? sprintf('Enquanto a reserva não chega ao mínimo, %d%% da sobra vai para ela.', (int) round($reserveShare * 100))
                    : 'A reserva já atinge o mínimo recomendado.',
                'Os valores de renda e despesas são os informados no diagnóstico.',
            ],
        ];
    }

    /**
     * Simula a quitação mês a mês.
     *
     * Cada dívida precisa de saldo e taxa mensal conhecida. Paga primeiro a
     * parcela/mínimo de todas; o que sobra do orçamento vai para a dívida
     * prioritária da estratégia. Quando uma dívida termina, o valor dela é
     * redirecionado para a próxima.
     *
     * @param list<array{id:int|string,name?:string,balance_cents:int,monthly_rate:?float,monthly_payment_cents?:?int}> $debts
     * @param 'avalanche'|'snowball'|'minimum' $strategy
     * @return array<string,mixed>
     */
    public static function simulate(array $debts, int $monthlyBudget, string $strategy, ?DateTimeImmutable $start = null): array
    {
        if (!in_array($strategy, ['avalanche', 'snowball', 'minimum'], true)) {
            throw new InvalidArgumentException('Estratégia desconhecida.');
        }
        $start ??= new DateTimeImmutable('first day of next month');
        $usable = [];
        $skipped = [];
        foreach ($debts as $debt) {
            $balance = (int) $debt['balance_cents'];
            if ($balance <= 0) {
                continue;
            }
            if (!isset($debt['monthly_rate']) || $debt['monthly_rate'] === null || (float) $debt['monthly_rate'] < 0) {
                $skipped[] = $debt['id'];
                continue;
            }
            $usable[] = [
                'id' => $debt['id'],
                'name' => (string) ($debt['name'] ?? ''),
                'balance' => (float) $balance,
                'rate' => (float) $debt['monthly_rate'] / 100,
                'minimum' => max(0, (int) ($debt['monthly_payment_cents'] ?? 0)),
                'paid_off_month' => null,
                'interest' => 0.0,
            ];
        }

        $base = [
            'strategy' => $strategy,
            'monthly_budget_cents' => $monthlyBudget,
            'skipped_ids' => $skipped,
            'assumptions' => [
                'Taxas de juros constantes, informadas por você; o credor pode cobrar outras.',
                'Nenhuma nova dívida é contraída durante o plano.',
                'Pagamentos feitos todo mês, sem atraso (atrasos geram multa e juros de mora não simulados).',
                'Resultado estimado, não garantido.',
            ],
        ];
        if ($usable === []) {
            return $base + ['feasible' => false, 'reason' => $skipped === [] ? 'no_debts' : 'missing_rates', 'months' => 0, 'total_paid_cents' => 0, 'total_interest_cents' => 0, 'payoff_date' => null, 'debts' => []];
        }

        $minimumTotal = array_sum(array_column($usable, 'minimum'));
        $firstInterest = array_sum(array_map(static fn (array $d): float => $d['balance'] * $d['rate'], $usable));
        if ($strategy !== 'minimum' && $monthlyBudget < $minimumTotal) {
            return $base + ['feasible' => false, 'reason' => 'below_minimums', 'required_cents' => $minimumTotal, 'months' => 0, 'total_paid_cents' => 0, 'total_interest_cents' => 0, 'payoff_date' => null, 'debts' => []];
        }
        $budget = $strategy === 'minimum' ? $minimumTotal : $monthlyBudget;
        if ($budget <= $firstInterest) {
            return $base + ['feasible' => false, 'reason' => 'interest_exceeds_payment', 'monthly_interest_cents' => (int) ceil($firstInterest), 'months' => 0, 'total_paid_cents' => 0, 'total_interest_cents' => 0, 'payoff_date' => null, 'debts' => []];
        }

        $totalPaid = 0.0;
        $totalInterest = 0.0;
        $month = 0;
        while ($month < self::MAX_MONTHS) {
            $open = array_filter($usable, static fn (array $d): bool => $d['balance'] > 0.5);
            if ($open === []) {
                break;
            }
            $month++;
            foreach ($usable as &$debt) {
                if ($debt['balance'] > 0.5) {
                    $interest = $debt['balance'] * $debt['rate'];
                    $debt['balance'] += $interest;
                    $debt['interest'] += $interest;
                    $totalInterest += $interest;
                }
            }
            unset($debt);

            $available = (float) $budget;
            foreach ($usable as &$debt) {
                if ($debt['balance'] > 0.5 && $debt['minimum'] > 0) {
                    $pay = min($debt['minimum'], $debt['balance'], $available);
                    $debt['balance'] -= $pay;
                    $available -= $pay;
                    $totalPaid += $pay;
                }
            }
            unset($debt);

            if ($strategy !== 'minimum' || $minimumTotal === 0) {
                foreach (self::priority($usable, $strategy) as $index) {
                    if ($available <= 0.5) {
                        break;
                    }
                    $pay = min($usable[$index]['balance'], $available);
                    $usable[$index]['balance'] -= $pay;
                    $available -= $pay;
                    $totalPaid += $pay;
                }
            }

            foreach ($usable as &$debt) {
                if ($debt['balance'] <= 0.5 && $debt['paid_off_month'] === null) {
                    $debt['balance'] = 0.0;
                    $debt['paid_off_month'] = $month;
                }
            }
            unset($debt);
        }

        $remaining = array_sum(array_column($usable, 'balance'));
        $feasible = $remaining <= 0.5;
        return $base + [
            'feasible' => $feasible,
            'reason' => $feasible ? null : 'exceeds_horizon',
            'months' => $feasible ? $month : null,
            'total_paid_cents' => (int) round($totalPaid),
            'total_interest_cents' => (int) round($totalInterest),
            'remaining_cents' => (int) round($remaining),
            'payoff_date' => $feasible ? $start->modify('+' . max(0, $month - 1) . ' months')->format('Y-m') : null,
            'debts' => array_map(static fn (array $d): array => [
                'id' => $d['id'],
                'name' => $d['name'],
                'paid_off_month' => $d['paid_off_month'],
                'interest_cents' => (int) round($d['interest']),
            ], $usable),
        ];
    }

    /**
     * Compara as estratégias com o mesmo orçamento mensal. A economia é medida
     * contra pagar só as parcelas/mínimos atuais.
     *
     * @param list<array<string,mixed>> $debts
     * @return array{avalanche:array,snowball:array,minimum:array,recommended:?string}
     */
    public static function compare(array $debts, int $monthlyBudget, ?DateTimeImmutable $start = null): array
    {
        $minimum = self::simulate($debts, $monthlyBudget, 'minimum', $start);
        $result = ['minimum' => $minimum];
        foreach (['avalanche', 'snowball'] as $strategy) {
            $run = self::simulate($debts, $monthlyBudget, $strategy, $start);
            $run['savings_cents'] = $run['feasible'] && $minimum['feasible']
                ? max(0, $minimum['total_interest_cents'] - $run['total_interest_cents'])
                : null;
            $result[$strategy] = $run;
        }
        $recommended = null;
        if ($result['avalanche']['feasible'] && $result['snowball']['feasible']) {
            $recommended = $result['avalanche']['total_interest_cents'] <= $result['snowball']['total_interest_cents'] ? 'avalanche' : 'snowball';
        } elseif ($result['avalanche']['feasible']) {
            $recommended = 'avalanche';
        } elseif ($result['snowball']['feasible']) {
            $recommended = 'snowball';
        }
        $result['recommended'] = $recommended;
        return $result;
    }

    /** Parcela fixa (Tabela Price) para quitar $principal em $months meses a $monthlyRate % a.m. */
    public static function installment(int $principal, float $monthlyRate, int $months): int
    {
        if ($principal <= 0 || $months < 1) {
            throw new InvalidArgumentException('Informe valor e prazo válidos.');
        }
        $i = $monthlyRate / 100;
        if ($i <= 0) {
            return (int) ceil($principal / $months);
        }
        return (int) ceil($principal * $i / (1 - (1 + $i) ** -$months));
    }

    /**
     * Avalia uma proposta de acordo: entrada + N parcelas iguais.
     * Calcula total pago, desconto sobre o saldo atual, taxa mensal implícita
     * (quando há parcelamento) e se a parcela cabe na capacidade informada.
     *
     * @return array<string,mixed>
     */
    public static function evaluateProposal(int $balance, int $downPayment, int $installments, int $installmentValue, ?int $capacity = null): array
    {
        if ($balance <= 0 || $downPayment < 0 || $installments < 0 || $installmentValue < 0) {
            throw new InvalidArgumentException('Informe valores válidos para a proposta.');
        }
        if ($installments > 0 && $installmentValue === 0) {
            throw new InvalidArgumentException('Informe o valor da parcela.');
        }
        $total = $downPayment + $installments * $installmentValue;
        $financed = $balance - $downPayment;
        $rate = null;
        if ($installments > 0 && $financed > 0) {
            $rate = self::impliedRate($financed, $installmentValue, $installments);
        }
        return [
            'balance_cents' => $balance,
            'total_cents' => $total,
            'discount_cents' => $balance - $total,
            'discount_rate' => ($balance - $total) / $balance,
            'implied_monthly_rate' => $rate,
            'fits_capacity' => $capacity === null ? null : $installmentValue <= $capacity,
            'assumptions' => [
                'Comparação com o saldo devedor informado hoje.',
                'A taxa implícita considera parcelas mensais iguais, sem tarifas extras.',
                'A aprovação depende do credor; a simulação não garante o acordo.',
            ],
        ];
    }

    /** Taxa mensal (%) que iguala o valor presente das parcelas ao valor financiado. Null se não houver solução. */
    public static function impliedRate(int $financed, int $installment, int $months): ?float
    {
        if ($financed <= 0 || $installment <= 0 || $months < 1) {
            return null;
        }
        if ($installment * $months <= $financed) {
            return 0.0;
        }
        $low = 0.0;
        $high = 1.0;
        $pv = static fn (float $i): float => $installment * (1 - (1 + $i) ** -$months) / $i;
        if ($pv($high) > $financed) {
            return null;
        }
        for ($step = 0; $step < 200; $step++) {
            $mid = ($low + $high) / 2;
            if ($mid <= 0) {
                $low = 1e-12;
                continue;
            }
            if ($pv($mid) > $financed) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }
        return round(($low + $high) / 2 * 100, 4);
    }

    /** Converte taxa anual (%) em mensal equivalente (%). */
    public static function annualToMonthly(float $annual): float
    {
        return round(((1 + $annual / 100) ** (1 / 12) - 1) * 100, 4);
    }

    /** @param list<array<string,mixed>> $debts @return list<int> */
    private static function priority(array $debts, string $strategy): array
    {
        $open = array_keys(array_filter($debts, static fn (array $d): bool => $d['balance'] > 0.5));
        usort($open, static function (int $a, int $b) use ($debts, $strategy): int {
            if ($strategy === 'snowball') {
                return [$debts[$a]['balance'], -$debts[$a]['rate']] <=> [$debts[$b]['balance'], -$debts[$b]['rate']];
            }
            return [-$debts[$a]['rate'], $debts[$a]['balance']] <=> [-$debts[$b]['rate'], $debts[$b]['balance']];
        });
        return $open;
    }
}
