# Design system do Realizze 2.0

Este documento é o contrato visual obrigatório do App. Ele consolida a interface existente; não cria um tema paralelo e não altera regras financeiras.

## Princípios

- Consistência antes de novidade: a mesma ação deve manter nome, posição e aparência.
- Dados financeiros continuam verdadeiros: cores reforçam rótulos e estados, nunca substituem texto.
- Uma interface para PF e PJ: o contexto muda a identidade e as permissões, não o comportamento básico.
- Acessibilidade por padrão: foco visível, rótulos explícitos, regiões roláveis identificadas e alvos de toque adequados.

## Tokens

Os tokens canônicos vivem em `assets/css/dashboard.css`, no bloco `DS-001`.

| Grupo | Tokens | Uso |
|---|---|---|
| Marca | `--ds-color-brand`, `--ds-color-brand-strong` | ação primária e destaque institucional |
| Texto | `--ds-color-text`, `--ds-color-muted` | conteúdo e apoio |
| Superfície | `--ds-color-surface`, `--ds-color-canvas`, `--ds-color-border` | cards, fundo e divisórias |
| Acessibilidade | `--ds-color-focus` | foco visível |
| Espaço | `--ds-space-1` a `--ds-space-7` | escala de 4 a 28 px |
| Forma | `--ds-radius-sm`, `--ds-radius-md`, `--ds-radius-lg` | controles e cards |
| Elevação | `--ds-shadow-card` | cards sobre o canvas |

Estados financeiros usam os tokens `--finance-realized`, `--finance-pending`, `--finance-overdue`, `--finance-forecast` e `--finance-neutral`, sempre acompanhados de texto.

## Tipografia e controles

- Família: Inter com fallback para fontes de sistema.
- Título de página: 26 px no desktop e 22 px no celular.
- Título de módulo: 18 px; título interno: 14–17 px.
- Texto de apoio: 12–13 px, com contraste preservado.
- Controles principais têm altura visual mínima de 40 px; em navegação móvel, priorizar 44 px.
- O foco usa contorno verde de 2–3 px e nunca deve ser removido sem substituto equivalente.

## Responsividade

- Desktop: conteúdo em grids, filtros na mesma linha quando houver espaço.
- Tablet: cabeçalho e ações quebram linha; painéis passam para uma ou duas colunas.
- Celular: ações ocupam largura disponível, formulários viram uma coluna e tabelas usam região horizontal rolável com `tabindex="0"`.
- Respeitar `prefers-reduced-motion` e `forced-colors`.
