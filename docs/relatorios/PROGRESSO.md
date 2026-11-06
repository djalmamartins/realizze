# Central de Relatórios — progresso

Última atualização: 06/11/2026.

## Critérios comuns

- Valores financeiros são inteiros em centavos.
- Toda leitura é limitada ao `financial_context_id` da pessoa ou empresa ativa.
- Realizado usa a data efetiva da baixa; previsto usa somente o saldo ainda aberto.
- Transferências entre contas de caixa não são receita nem despesa.
- Compras no cartão pertencem ao regime de competência e não antecipam uma saída de caixa; o pagamento da fatura é a saída efetiva.
- Relatórios gerenciais não substituem demonstrações contábeis ou fiscais oficiais.

## Situação por prioridade

| Relatório | Situação | Próxima melhoria |
|---|---|---|
| Fluxo de Caixa | Em evolução — saldo inicial histórico, saldo realizado e projeção cumulativa entregues | período personalizado, exportação e rastreabilidade por linha |
| DRE Gerencial | Em evolução — caixa e competência reconciliados, comparação mensal e CSV específico | período personalizado e classificação configurável |
| Faturamento | Em evolução — competência, caixa, posição histórica, origem e CSV entregues | comparação entre períodos e filtros por cliente/origem |
| Pagar/Receber | Pendente de consolidação | previsto x realizado e detalhamento de baixas parciais |
| Posição de Contas | Pendente | saldos por conta e data-base |
| Orçamento Empresarial | Pendente | comparar orçamento existente com realizado por categoria |

## Incremento 2026-11-06 — Fluxo de Caixa

Entregue:

- saldo inicial das contas de caixa na véspera do período;
- saldo realizado ao final do período (`saldo inicial + entradas realizadas - saídas realizadas`);
- saldo projetado cumulativo por dia ou semana (`saldo anterior + realizado + previsto em aberto`);
- indicação de períodos com déficit projetado;
- exclusão de compras no cartão do realizado de caixa;
- reconhecimento único do pagamento da fatura como saída bancária;
- estornos de pagamento deixam de permanecer como saída;
- transferências internas continuam neutras.

Testes isolados aprovados: `finance-009-transfer-balance`, `finance-017-dashboard-consistency` e `finance-020-card-invoices` no banco `realizze_pjtest`.

## Incremento 2026-11-06 — DRE Gerencial

Entregue:

- critério de competência preserva o valor integral reconhecido no período;
- critério de caixa reúne pagamentos diretos e baixas integrais ou parciais na data efetiva;
- pagamentos de fatura são rateados pelas alocações das compras, preservando categoria e valor sem contar a compra e o pagamento duas vezes;
- estornos de fatura deixam de compor o caixa;
- cada linha expõe lançamento, data, categoria, origem e valor;
- comparação com o mês anterior e variação por linha da DRE;
- exportação CSV específica para o critério selecionado, protegida pela permissão existente de exportação;
- datas civis inválidas recusadas e consultas isoladas por contexto financeiro.

Fórmula do resultado gerencial: `receita bruta - deduções - custos - despesas operacionais + outras receitas - outras despesas`.

A classificação continua gerencial e explícita pelo nome da categoria: prefixos `Custo`/`CMV`, `Dedução`/`Imposto` e `Outra receita`/`Outra despesa`. Não há cálculo automático de tributos.

Teste isolado ampliado: `enterprise-004-management-dre` no banco `realizze_pjtest`.

## Incremento 2026-11-06 — Faturamento Gerencial

Fonte única: receitas de **Contas a Receber**. Ordens de Serviço só entram quando geram uma cobrança vinculada; uma OS sem cobrança não é tratada como faturamento.

Entregue:

- faturado por competência, excluindo cobranças canceladas;
- recebido no período pela data efetiva de cada baixa, inclusive de competências anteriores;
- posição histórica do faturado (`recebido até a data-base` e `em aberto na data-base`);
- vencidos integrais e parcialmente recebidos calculados pelo saldo ainda aberto;
- separação entre cobranças manuais e originadas por Ordem de Serviço;
- rastreabilidade até o lançamento, cliente e número da OS;
- detalhamento na Central de Relatórios e exportação CSV completa;
- limite visual de 100 linhas, mantendo todo o período no CSV;
- isolamento entre empresas validado no backend.

Este relatório não representa emissão de Nota Fiscal, documento fiscal, receita tributável ou apuração de imposto.

Teste isolado: `enterprise-009-billing-report` no banco `realizze_pjtest`.

