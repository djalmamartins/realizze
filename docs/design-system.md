# Design System — Realizze 2.0

## Princípios

- **Clareza:** indicadores devem explicar origem, período e disponibilidade.
- **Confiança:** nenhum valor demonstrativo pode parecer dado real.
- **Progressão:** tarefas frequentes primeiro; opções avançadas sob demanda.
- **Isolamento:** contexto PF/PJ e permissões nunca dependem apenas da interface.
- **Acessibilidade:** foco visível, semântica, contraste e movimento reduzido.

## Tokens fundamentais

| Grupo | Token | Valor inicial | Uso |
|---|---|---:|---|
| Marca | `--rz-brand-600` | `#078b4c` | Ações e estados ativos |
| Marca | `--rz-brand-700` | `#066f3e` | Hover e contraste |
| Marca | `--rz-brand-100` | `#eaf7ef` | Superfícies selecionadas |
| Texto | `--rz-ink-900` | `#183b2b` | Títulos e informação principal |
| Texto | `--rz-ink-600` | `#65746d` | Texto auxiliar |
| Superfície | `--rz-surface` | `#ffffff` | Cards e painéis |
| Fundo | `--rz-canvas` | `#f8faf9` | Área principal |
| Estado | `--rz-danger-600` | `#d92d36` | Atrasos e erros reais |
| Estado | `--rz-warning-100` | `#fff8e8` | Avisos sem bloqueio |
| Raio | `--rz-radius-sm` | `8px` | Controles |
| Raio | `--rz-radius-md` | `14px` | Cards |
| Espaço | `--rz-space-1` a `--rz-space-8` | escala de 4px | Ritmo consistente |

## Componentes

- Sidebar responsiva e off-canvas no mobile.
- Cabeçalho de página com contexto, título e estado da prévia.
- Cards financeiros com rótulo, valor, período e estado.
- Tabelas roláveis por teclado em telas estreitas.
- Filtros com rótulo persistente e controles desabilitados honestamente.
- Estados vazios com motivo, impacto e próximo passo.
- Toast apenas para feedback transitório; erros importantes permanecem visíveis.

## Responsividade

- Desktop: sidebar persistente e grades de indicadores.
- Tablet: sidebar off-canvas e grades reduzidas.
- Mobile: uma coluna, alvos de toque adequados e ações principais em largura total.
- Tabelas mantêm semântica tabular e rolagem horizontal acessível.

## Modo escuro

O modo escuro permanece planejado. Deve ser implementado com tokens semânticos,
`prefers-color-scheme`, preferência persistida e contraste verificado. O toggle
atual não deve sugerir que a função está ativa antes dessa etapa.
