# Conformidade Brasil — progresso

Última atualização: 06/11/2026.

## Escopo atual

A Central de Relatórios entrega informações **gerenciais** com rastreabilidade aos lançamentos do Realizze. Ela não calcula tributos, não transmite obrigações e não apresenta DRE, balanço ou escrituração como documentos contábeis oficiais.

O “Faturamento gerencial” significa receitas registradas em Contas a Receber por competência. Não comprova emissão de Nota Fiscal e não deve ser usado como faturamento fiscal sem integração e validação contábil próprias.

## Controles aplicados

- separação explícita entre regime de caixa e competência;
- valores em centavos, sem `FLOAT` para dinheiro;
- baixas parciais reconhecidas na data e pelo valor efetivamente liquidado;
- isolamento por contexto financeiro e por empresa no backend;
- compras no cartão separadas do pagamento bancário da fatura;
- na DRE por caixa, pagamentos de fatura são rastreados até as compras e categorias pelas alocações, sem dupla contagem;
- transferências internas excluídas de receitas e despesas;
- CSV da DRE aplica neutralização de fórmulas em textos fornecidos pelo usuário;
- CSV de faturamento neutraliza fórmulas e preserva a distinção entre competência, caixa e saldo em aberto;
- nenhuma migration ou escrita de teste executada em `realizze_dev`.

## Pendências

- documentar fontes, fórmulas e limitações de cada relatório da Central;
- incluir trilha de detalhamento/exportação sem expor dados de outra empresa;
- validar retenção, classificação e terminologia com profissional contábil antes de qualquer alegação fiscal ou oficial;
- não implementar impostos, balanço oficial, estoque, margem ou indicadores sem fonte confiável e regras aprovadas.

