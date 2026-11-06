<?php
declare(strict_types=1);

namespace Moves\Services;

/**
 * Catálogo público dos critérios usados pelos relatórios gerenciais.
 *
 * Este serviço não consulta dados nem calcula indicadores. Ele mantém a
 * explicação exibida ao usuário junto das mesmas regras implementadas nos
 * serviços financeiros, reduzindo o risco de apresentar uma métrica sem
 * fonte, fórmula ou limitação conhecida.
 */
final class ReportMethodology
{
    /**
     * @return list<array{id:string,title:string,source:string,period:string,formula:string,limitations:string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'cash-flow',
                'title' => 'Fluxo de caixa',
                'source' => 'Contas bancárias e carteiras, baixas de receitas e despesas, transferências e pagamentos de faturas.',
                'period' => 'A data efetiva da baixa determina o realizado; o saldo ainda aberto compõe a projeção no vencimento.',
                'formula' => 'Saldo projetado = saldo inicial + entradas realizadas − saídas realizadas + entradas abertas − saídas abertas.',
                'limitations' => 'Transferências internas são neutras. Compras no cartão só afetam o caixa quando a fatura é paga.',
            ],
            [
                'id' => 'management-dre',
                'title' => 'DRE gerencial',
                'source' => 'Receitas e despesas classificadas por categoria, incluindo alocações de compras pagas por fatura.',
                'period' => 'Competência usa a data reconhecida do lançamento; caixa usa a data e o valor de cada baixa.',
                'formula' => 'Resultado = receita bruta − deduções − custos − despesas operacionais + outras receitas − outras despesas.',
                'limitations' => 'Classificação estritamente gerencial; não substitui DRE contábil, escrituração ou apuração tributária.',
            ],
            [
                'id' => 'billing',
                'title' => 'Faturamento gerencial',
                'source' => 'Receitas de Contas a Receber; ordens de serviço entram somente quando possuem cobrança vinculada.',
                'period' => 'Faturado usa a competência da receita; recebido usa a data de cada baixa; aberto respeita a data-base.',
                'formula' => 'Em aberto = valor faturado − recebimentos acumulados até a data-base; vencido considera somente esse saldo.',
                'limitations' => 'Não comprova emissão de nota fiscal, receita tributável ou faturamento fiscal.',
            ],
            [
                'id' => 'pay-receive',
                'title' => 'Pagar e receber',
                'source' => 'Lançamentos financeiros, baixas integrais ou parciais, contas e contrapartes do contexto ativo.',
                'period' => 'A competência da listagem é o vencimento; o realizado usa baixas ocorridas até a data-base.',
                'formula' => 'Saldo aberto = valor do lançamento − baixas até a data-base; resultado previsto = receitas − despesas.',
                'limitations' => 'Cancelados e transferências não entram nos totais. Situações são gerenciais e dependem dos dados cadastrados.',
            ],
            [
                'id' => 'account-position',
                'title' => 'Posição de contas',
                'source' => 'Saldos iniciais e movimentos efetivos de contas correntes, poupanças e carteiras.',
                'period' => 'Compara a posição na véspera do início com a posição na data-base do período.',
                'formula' => 'Saldo final = saldo inicial + movimentação líquida; transferências mudam contas individuais, não o total.',
                'limitations' => 'Cartões, limites de crédito e contas arquivadas antes do corte não são patrimônio disponível.',
            ],
            [
                'id' => 'budget',
                'title' => 'Orçamento empresarial',
                'source' => 'Orçamentos mensais por categoria, lançamentos comprometidos e baixas efetivas.',
                'period' => 'Comprometido usa o vencimento no mês; realizado considera pagamentos até a data-base.',
                'formula' => 'Aberto = comprometido − realizado; disponível = orçamento − comprometido.',
                'limitations' => 'É limite gerencial por categoria, não provisão contábil nem autorização automática de gasto.',
            ],
            [
                'id' => 'service-orders',
                'title' => 'Operação de serviços',
                'source' => 'Ordens de serviço da empresa e, quando existente, a Conta a Receber vinculada e suas baixas.',
                'period' => 'Usa a data de início da ordem, ou sua criação quando ausente; recebimentos respeitam a data-base.',
                'formula' => 'Saldo da cobrança = cobrança vinculada − recebimentos até a data-base; ordens canceladas não somam valor.',
                'limitations' => 'Não infere horas, custo, margem, produtividade, estoque ou documento fiscal.',
            ],
            [
                'id' => 'obligations',
                'title' => 'Agenda de obrigações',
                'source' => 'Obrigações e comprovantes cadastrados manualmente pela empresa.',
                'period' => 'O período usa o vencimento; conclusão e cancelamento são avaliados na data-base.',
                'formula' => 'Os indicadores contam ocorrências e somam apenas o valor opcional informado, sem gerar lançamento financeiro.',
                'limitations' => 'Não calcula tributos, multas, juros, guias, enquadramento ou prazos legais.',
            ],
        ];
    }
}
