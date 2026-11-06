# Conformidade Brasil — progresso

Última atualização: 06/11/2026.

## Escopo atual

A Central de Relatórios entrega informações **gerenciais** com rastreabilidade aos lançamentos do Realizze. Ela não calcula tributos, não transmite obrigações e não apresenta DRE, balanço ou escrituração como documentos contábeis oficiais.

O “Faturamento gerencial” significa receitas registradas em Contas a Receber por competência. Não comprova emissão de Nota Fiscal e não deve ser usado como faturamento fiscal sem integração e validação contábil próprias.

## Controles aplicados

- separação explícita entre regime de caixa e competência;
- valores em centavos, sem `FLOAT` para dinheiro;
- baixas parciais reconhecidas na data e pelo valor efetivamente liquidado;
- relatórios encerrados preservam a posição histórica: liquidações posteriores não reescrevem realizado, aberto ou vencido da data-base;
- filtros de Pagar/Receber usam a situação calculada na mesma data-base e atualizam linhas, totais e CSV em conjunto;
- isolamento por contexto financeiro e por empresa no backend;
- compras no cartão separadas do pagamento bancário da fatura;
- fluxo de caixa expõe lançamento, origem, conta e categoria, preserva baixas parciais e não duplica compras do cartão com o pagamento da fatura;
- posição de contas exclui cartões e limites do patrimônio disponível e reconhece somente a saída bancária no pagamento da fatura;
- orçamento empresarial separa limite gerencial, compromisso por vencimento e caixa pago até a data-base; baixas posteriores não reescrevem o período encerrado;
- pagamentos de fatura só entram no realizado do orçamento quando alocados às compras e são removidos do caixa gerencial após estorno;
- na DRE por caixa, pagamentos de fatura são rastreados até as compras e categorias pelas alocações, sem dupla contagem;
- classificação da DRE é gerencial, configurável por categoria e restrita a grupos compatíveis; alterações exigem gestão financeira e são auditadas;
- categorias sem configuração explícita mantêm fallback documentado por prefixo, sem inferir plano contábil ou tratamento fiscal;
- transferências internas excluídas de receitas e despesas;
- CSV da DRE aplica neutralização de fórmulas em textos fornecidos pelo usuário;
- CSV do fluxo de caixa aplica neutralização de fórmulas e mantém a separação entre realizado e saldo previsto;
- CSV de faturamento neutraliza fórmulas e preserva a distinção entre competência, caixa e saldo em aberto;
- filtros de cliente e origem usam o mesmo escopo nos indicadores, comparação, detalhamento e CSV de faturamento;
- comparação de faturamento usa períodos equivalentes e não converte variação gerencial em alegação fiscal;
- relatório operacional usa somente ordens de serviço registradas, separa valor comercial, cobrança e recebimento e não infere margem, produtividade ou documento fiscal;
- filtros da operação de serviços mantêm o mesmo escopo empresarial nos indicadores, linhas e CSV;
- exportação de ordens neutraliza fórmulas nos textos fornecidos pelo usuário e mantém o isolamento da empresa ativa;
- agenda de obrigações usa somente dados informados pela empresa, preserva a situação na data-base e não calcula tributos, multas, guias ou prazos legais;
- filtros da agenda mantêm o mesmo escopo empresarial nos indicadores, linhas e CSV, inclusive para obrigações sem responsável;
- exportação de obrigações neutraliza fórmulas nos textos e não converte valores opcionais em lançamentos financeiros;
- metodologia de oito relatórios expõe na própria Central a fonte, o corte temporal, a fórmula e os limites de uso;
- catálogo metodológico reforça que indicadores gerenciais não são documentos contábeis ou fiscais oficiais;
- linhas e CSVs carregam referência rastreável, enquanto as telas de origem revalidam contexto e permissão no backend;
- navegação por fragmento não usa ID de URL como autorização e não consulta outra empresa;
- metodologia pode ser exportada, sob `reports.export`, como evidência para revisão externa;
- roteiro de validação contábil registra decisões, ressalvas, retenção e identificação profissional sem antecipar aprovação;
- nenhuma migration ou escrita de teste executada em `realizze_dev`.

## Pendências

- validar retenção, classificação e terminologia com profissional contábil antes de qualquer alegação fiscal ou oficial;
- não implementar impostos, balanço oficial, estoque, margem ou indicadores sem fonte confiável e regras aprovadas.

