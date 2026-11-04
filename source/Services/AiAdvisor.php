<?php
declare(strict_types=1);

namespace Moves\Services;

/**
 * Ponto único da camada de IA (explicações), separado do motor de cálculo.
 *
 * Nenhum provedor está ligado: não há credenciais nem chamadas externas.
 * Um provedor futuro deve implementar AiProvider, receber só o resumo
 * minimizado de minimizedSummary() (sem nomes, documentos ou contas) e só ser
 * ativado com autorização expressa do titular e configuração explícita.
 */
final class AiAdvisor
{
    public static function provider(): ?AiProvider
    {
        // Intencionalmente sem implementação: ativar exige decisão do Djalma (custo, LGPD e contrato).
        return null;
    }

    public static function available(): bool
    {
        return self::provider() !== null;
    }

    /**
     * Resumo sem dados identificáveis, com números já calculados pelo DebtPlanner.
     *
     * @param array<string,mixed> $capacity
     * @param list<array<string,mixed>> $debts
     * @param array<string,mixed> $comparison
     * @return array<string,mixed>
     */
    public static function minimizedSummary(array $capacity, array $debts, array $comparison): array
    {
        return [
            'capacity' => array_intersect_key($capacity, array_flip(['net_income_cents', 'essential_expenses_cents', 'variable_expenses_cents', 'free_cents', 'capacity_cents', 'current_payments_cents', 'total_debt_cents', 'situation'])),
            'debts' => array_map(static fn (array $d): array => [
                'type' => (string) ($d['debt_type'] ?? 'other'),
                'balance_cents' => (int) ($d['balance_cents'] ?? 0),
                'monthly_rate' => $d['monthly_rate'] ?? null,
                'collateral' => (string) ($d['collateral'] ?? 'none'),
                'status' => (string) ($d['status'] ?? 'current'),
            ], $debts),
            'strategies' => array_map(static fn (array $run): array => array_intersect_key($run, array_flip(['feasible', 'reason', 'months', 'total_paid_cents', 'total_interest_cents', 'savings_cents'])), array_intersect_key($comparison, array_flip(['avalanche', 'snowball', 'minimum']))),
        ];
    }
}
