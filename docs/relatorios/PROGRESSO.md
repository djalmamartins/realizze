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
| Pagar/Receber | Em evolução — posição histórica, previsto/realizado, parciais e CSV entregues | filtros por situação, contraparte e conta na Central |
| Posição de Contas | Concluída — saldo inicial/final, movimentação líquida, data-base e CSV | acompanhar regressões com novos tipos de conta |
| Orçamento Empresarial | Concluído — orçado, comprometido, pago, aberto, disponível, comparação mensal e CSV | acompanhar regressões e avaliar metas anuais por categoria |

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

## Incremento 2026-11-06 — Orçamento Empresarial

Entregue sobre o Planejamento já existente, sem criar módulo paralelo:

- orçamento mensal por categoria comparado ao valor comprometido pela data de vencimento;
- realizado reconhece somente pagamentos efetivos até a data-base, inclusive baixas parciais;
- parcelas de cartão permanecem comprometidas no ciclo e só viram realizado quando o pagamento da fatura é alocado à compra;
- pagamentos e estornos posteriores não reescrevem uma posição histórica encerrada;
- saldo em aberto e disponível usam `comprometido - pago` e `orçado - comprometido`;
- comparação do comprometido com o mês anterior por categoria;
- consolidação anual preserva os mesmos critérios mensais;
- CSV específico com orçado, comprometido, pago, aberto, disponível e variação, usando a permissão existente de exportação;
- projeção do resultado passou a reconhecer baixas parciais de despesas, antes ignoradas;
- isolamento pelo contexto financeiro e exclusão de cancelados preservados.

Testes isolados aprovados no banco `realizze_pjtest`: `finance-015-planning`, `finance-014-partial-settlement`, `finance-017-dashboard-consistency`, `finance-020-card-invoices` e `enterprise-004-management-dre`.

## Incremento 2026-11-06 — Pagar e Receber

Entregue:

- data-base explícita: períodos encerrados usam o último dia; período corrente ou futuro usa a data atual;
- baixas posteriores ao fim do período não alteram retroativamente seus indicadores;
- previsto, realizado e saldo aberto separados para receitas e despesas;
- baixas parciais exibem valor realizado e valor restante em cada linha;
- vencido calculado somente sobre o saldo aberto na data-base;
- pagamentos diretos sem registro de baixa continuam reconhecidos corretamente;
- cancelamentos e transferências não compõem os totais;
- CSV geral ganhou realizado, saldo em aberto e situação, usando o mesmo corte histórico da tela;
- validação de período no backend e manutenção do isolamento por contexto financeiro.

Testes aprovados: `finance-006-report` e `finance-024-report-as-of`; regressões de Receber, Faturamento e Dashboard executadas no banco `realizze_pjtest`.

## Incremento 2026-11-06 — Posição de Contas

Entregue:

- posição histórica individual de conta corrente, poupança e carteira;
- saldo inicial, movimentação líquida e saldo final na data-base;
- baixas integrais/parciais atribuídas à conta efetivamente usada;
- transferências internas movimentam as contas, mas permanecem neutras no total em caixa;
- pagamento da conta para o cartão reduz o caixa na data efetiva;
- cartões de crédito e limites não são apresentados como patrimônio disponível;
- contas criadas depois da data-base ficam fora e contas arquivadas preservam o histórico anterior;
- visualização reutilizada na Central de Relatórios para PF e PJ;
- exportação CSV com proteção contra fórmulas e isolamento por contexto financeiro.

Teste isolado: `finance-025-account-position`; regressões `finance-009`, `017`, `018`, `020`, `024` e `UI-002` aprovadas em `realizze_pjtest`.

