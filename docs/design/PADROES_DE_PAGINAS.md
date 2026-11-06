# Padrões de páginas

## Estrutura interna

1. O shell e a sidebar vêm de `default.php`.
2. O cabeçalho global vem de `page-heading.php`.
3. Cada módulo usa um `card finance-workspace` como superfície principal.
4. Relatórios começam com `report-heading.php`, seguido por indicadores, navegação/filtros, visualização, tabela e rastreabilidade.
5. A ação principal fica no cabeçalho do módulo; filtros ficam imediatamente antes dos dados afetados.

## Central de Relatórios

- Período e critério devem permanecer visíveis e persistir em links e exportações.
- Previsto e realizado são apresentados separadamente.
- Toda exportação repete os filtros aplicados.
- Tabelas analíticas oferecem rastreabilidade até os lançamentos quando a fonte existir.
- Impressão remove navegação e controles, preservando identidade, período e data de emissão.

## Formulários

- Rótulo sempre associado visualmente ao controle.
- Campos relacionados usam grid responsivo; no celular, uma coluna.
- Erros aparecem próximos ao formulário e também em região anunciável quando aplicável.
- Não criar formulários financeiros paralelos: estender os componentes existentes.

## Critérios de revisão de uma página

- Cabeçalho, ações e filtros seguem a ordem padrão.
- Navegação por teclado alcança todos os controles.
- A página funciona a 360 px sem perda de ação; tabelas podem rolar dentro da região identificada.
- Estados vazio, erro e carregamento não inventam dados.
- PF/PJ e permissões continuam isolados.
- AJAX, refresh e histórico do navegador preservam filtros e rota.

## Páginas já consolidadas

- Central de Relatórios, DRE e Fluxo de Caixa: cabeçalho, exportação e período compartilhados.
- Contas a Pagar e Contas a Receber: cabeçalho e ação principal estruturados pelo mesmo componente, preservando o formulário financeiro existente.
- Faturamento gerencial e Posição de Contas: cabeçalhos de subseção e exportações compartilham a composição dos demais relatórios.
- Navegação móvel: drawer com `inert`, foco restaurado, fechamento por Escape, backdrop e troca de breakpoint cobertos por teste JavaScript.
