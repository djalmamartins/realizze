# Conformidade Brasil — progresso

Última atualização: 06/11/2026.

## Escopo atual

A Central de Relatórios entrega informações **gerenciais** com rastreabilidade aos lançamentos do Realizze. Ela não calcula tributos, não transmite obrigações e não apresenta DRE, balanço ou escrituração como documentos contábeis oficiais.

## Controles aplicados

- separação explícita entre regime de caixa e competência;
- valores em centavos, sem `FLOAT` para dinheiro;
- baixas parciais reconhecidas na data e pelo valor efetivamente liquidado;
- isolamento por contexto financeiro e por empresa no backend;
- compras no cartão separadas do pagamento bancário da fatura;
- transferências internas excluídas de receitas e despesas;
- nenhuma migration ou escrita de teste executada em `realizze_dev`.

## Pendências

- documentar fontes, fórmulas e limitações de cada relatório da Central;
- incluir trilha de detalhamento/exportação sem expor dados de outra empresa;
- validar retenção, classificação e terminologia com profissional contábil antes de qualquer alegação fiscal ou oficial;
- não implementar impostos, balanço oficial, estoque, margem ou indicadores sem fonte confiável e regras aprovadas.

