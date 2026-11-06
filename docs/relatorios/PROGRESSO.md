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
| Fluxo de Caixa | Concluído — período personalizado, saldos, projeção, rastreabilidade e CSV entregues | acompanhar regressões e avaliar cenários salvos sem inventar premissas |
| DRE Gerencial | Concluída — caixa/competência, período personalizado, classificação por categoria, comparação e CSV | acompanhar regressões e validação contábil da terminologia |
| Faturamento | Concluído — competência, caixa, posição histórica, comparação, filtros, origem e CSV | validação fiscal/contábil externa antes de qualquer uso oficial |
| Pagar/Receber | Concluído — posição histórica, previsto/realizado, parciais, filtros e CSV | acompanhar regressões dos serviços financeiros compartilhados |
| Posição de Contas | Concluída — saldo inicial/final, movimentação líquida, data-base e CSV | acompanhar regressões com novos tipos de conta |
| Orçamento Empresarial | Concluído — orçado, comprometido, pago, aberto, disponível, comparação mensal e CSV | acompanhar regressões e avaliar metas anuais por categoria |
| Operação de Serviços | Concluído — ordens, status, valores, cobranças, recebimentos e CSV | acrescentar filtros por cliente/status sem perder o corte histórico |
| Agenda de Obrigações | Concluída — vencimentos, recorrências, responsáveis, comprovantes, situação histórica e CSV | acrescentar filtros por tipo/responsável na Central |

## Incremento 2026-11-06 — Fluxo de Caixa

Entregue:

- saldo inicial das contas de caixa na véspera do período;
- saldo realizado ao final do período (`saldo inicial + entradas realizadas - saídas realizadas`);
- saldo projetado cumulativo por dia ou semana (`saldo anterior + realizado + previsto em aberto`);
- período mensal ou personalizado, inclusive atravessando meses e anos, com agrupamento diário ou semanal;
- indicação de períodos com déficit projetado;
- previsto considera apenas o saldo efetivamente aberto após baixas parciais;
- exclusão de compras no cartão do realizado de caixa;
- reconhecimento único do pagamento da fatura como saída bancária;
- estornos de pagamento deixam de permanecer como saída;
- transferências internas continuam neutras;
- detalhamento de cada linha realizada ou prevista com lançamento, origem, conta e categoria;
- exportação CSV do período completo, protegida pela permissão de exportação e com neutralização de fórmulas em textos do usuário;
- URLs canônicas preservam o intervalo personalizado e o agrupamento selecionado.

Testes isolados aprovados: `finance-009-transfer-balance`, `finance-017-dashboard-consistency`, `finance-020-card-invoices`, `finance-024-report-as-of` e `ui-002-view-smoke` no banco `realizze_pjtest`.

## Incremento 2026-11-06 — DRE Gerencial

Entregue:

- critério de competência preserva o valor integral reconhecido no período;
- critério de caixa reúne pagamentos diretos e baixas integrais ou parciais na data efetiva;
- pagamentos de fatura são rateados pelas alocações das compras, preservando categoria e valor sem contar a compra e o pagamento duas vezes;
- estornos de fatura deixam de compor o caixa;
- cada linha expõe lançamento, data, categoria, origem e valor;
- período mensal ou personalizado; o mensal compara o mês anterior e o personalizado usa o intervalo imediatamente anterior de igual duração;
- classificação gerencial explícita e isolada por categoria, limitada a grupos compatíveis com receita ou despesa;
- fallback compatível por prefixo enquanto uma categoria não possui escolha explícita;
- alteração da classificação exige gestão financeira e gera evento na auditoria empresarial;
- exportação CSV específica para o critério selecionado, protegida pela permissão existente de exportação;
- datas civis inválidas recusadas e consultas isoladas por contexto financeiro.

Fórmula do resultado gerencial: `receita bruta - deduções - custos - despesas operacionais + outras receitas - outras despesas`.

A classificação continua estritamente gerencial. A configuração por categoria depende da migration `020_add_management_dre_group.sql`; sem ela, o relatório permanece operacional com os prefixos `Custo`/`CMV`, `Dedução`/`Imposto` e `Outra receita`/`Outra despesa`. Não há cálculo automático de tributos.

Testes isolados aprovados: `enterprise-004-management-dre`, `finance-009-transfer-balance`, `finance-017-dashboard-consistency`, `finance-020-card-invoices`, `finance-024-report-as-of` e `ui-002-view-smoke` no banco `realizze_pjtest`. A migration 020 foi aplicada somente nesse banco, após backup local; `realizze_dev` permaneceu sem a coluna nova.

## Incremento 2026-11-06 — Faturamento Gerencial

Fonte única: receitas de **Contas a Receber**. Ordens de Serviço só entram quando geram uma cobrança vinculada; uma OS sem cobrança não é tratada como faturamento.

Entregue:

- faturado por competência, excluindo cobranças canceladas;
- recebido no período pela data efetiva de cada baixa, inclusive de competências anteriores;
- posição histórica do faturado (`recebido até a data-base` e `em aberto na data-base`);
- vencidos integrais e parcialmente recebidos calculados pelo saldo ainda aberto;
- separação entre cobranças manuais e originadas por Ordem de Serviço;
- filtros por cliente e origem aplicados de forma idêntica ao faturado, recebido em caixa, saldos e detalhamento;
- comparação com o período anterior equivalente, preservando os filtros selecionados;
- rastreabilidade até o lançamento, cliente e número da OS;
- detalhamento na Central de Relatórios e exportação CSV completa com os mesmos filtros da tela;
- limite visual de 100 linhas, mantendo todo o período no CSV;
- isolamento entre empresas validado no backend.

Este relatório não representa emissão de Nota Fiscal, documento fiscal, receita tributável ou apuração de imposto.

Testes isolados aprovados no banco `realizze_pjtest`: `enterprise-009-billing-report`,
`enterprise-007-service-orders`, `finance-017-dashboard-consistency`,
`finance-024-report-as-of` e `ui-002-view-smoke`.

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
- filtros por situação histórica, contraparte e conta aplicados às linhas e totalizadores;
- busca de contraparte considera o contato vinculado ou o nome informado manualmente;
- CSV geral ganhou realizado, saldo em aberto e situação, usando o mesmo corte histórico da tela;
- exportação CSV preserva os mesmos filtros selecionados na Central;
- validação de período no backend e manutenção do isolamento por contexto financeiro.

Testes aprovados: `finance-006-report` e `finance-024-report-as-of`; regressões de Receber,
Faturamento, Dashboard, Cartões/Faturas e templates executadas no banco `realizze_pjtest`.

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

## Incremento 2026-11-06 — Operação de Serviços

Fonte única: ordens de serviço da empresa. O período usa a data de início da ordem e, quando ausente, a data de criação.

Entregue:

- quantidade e valor comercial das ordens, excluindo canceladas dos totalizadores financeiros;
- descontos, valores concluídos e distribuição pelos estados operacionais existentes;
- separação entre ordem sem cobrança e ordem com Conta a Receber vinculada;
- recebido e saldo aberto calculados pelas baixas efetivas até a data-base;
- detalhamento com número, cliente, responsável, prazo, situação e vínculo financeiro;
- exportação CSV protegida pela permissão existente de relatórios;
- isolamento por `organization_id` e `financial_context_id` validado;
- nenhuma criação de cobrança ou alteração de saldo durante a consulta.

O relatório não calcula horas, custo, margem, produtividade ou faturamento fiscal porque essas fontes não existem nas ordens atuais. A situação exibida é a situação atual da ordem; o corte histórico aplica-se aos recebimentos.

Teste isolado ampliado: `enterprise-007-service-orders` no banco `realizze_pjtest`.

## Incremento 2026-11-06 — Agenda de Obrigações

Fonte única: obrigações cadastradas manualmente pela empresa. O período usa a data de vencimento.

Entregue:

- quantidade e valor opcional informado, sem transformar a obrigação em despesa;
- pendentes, próximas em até sete dias, vencidas, concluídas e canceladas na data-base;
- conclusões e cancelamentos posteriores não reescrevem a posição histórica;
- competência, vencimento, responsável, periodicidade e quantidade de comprovantes por linha;
- recorrências permanecem ocorrências independentes, sem agregação ou duplicação artificial;
- exportação CSV protegida pela permissão existente de relatórios;
- ativação condicional: a Central continua funcionando quando a migration 018 ainda não existe;
- isolamento por empresa e ausência de escrita financeira validados.

Este é um calendário gerencial. O Realizze não calcula imposto, multa, juros, enquadramento, guia ou prazo legal e não transmite obrigações.

Teste isolado ampliado: `enterprise-006-obligations` no banco `realizze_pjtest`.

