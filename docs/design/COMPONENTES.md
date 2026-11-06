# Componentes visuais

## Cabeçalho da página

`components/page-heading.php` é o cabeçalho global, com contexto, título, descrição e ferramentas. Páginas internas não devem recriá-lo.

## Cabeçalho de relatório

`components/report-heading.php` recebe dados estruturados:

```php
$appView->render('components::report-heading', [
    'reportHeading' => ['id' => 'cash-title', 'title' => 'Fluxo de caixa', 'description' => 'Descrição curta.'],
    'reportActions' => [['label' => 'Exportar fluxo', 'href' => $exportUrl, 'download' => true]],
]);
```

Use links para navegação/exportação e botão somente para ações locais, como impressão. Não passe HTML nos rótulos.

## Período de relatório

`components/report-period-form.php` padroniza data inicial, data final, parâmetros ocultos e envio AJAX GET. Preserve os nomes `inicio` e `fim`.

## Indicadores

Use `finance-goal-summary` + `finance-goal-stat finance-indicator`. Aplique um estado semântico (`is-realized`, `is-pending`, `is-overdue`, `is-forecast`, `is-positive`, `is-negative` ou `is-neutral`) e mantenha rótulo e explicação textual.

## Tabelas

- `finance-table` para fluxos operacionais e `report-table` para relatórios analíticos.
- Envolver em `finance-table-wrap` ou `report-table-wrap` com `role="region"`, nome acessível e `tabindex="0"` quando houver rolagem.
- Valores monetários ficam alinhados à direita (`num` ou `is-money`).
- Cabeçalhos usam `scope="col"`; totais usam `tfoot`.

## Estados e feedback

- Vazio/erro: `finance-empty`, com título que descreve o estado e orientação acionável.
- Feedback de operação: `finance-flash` com `role="status"`.
- Carregamento global: `app-loader`; não criar spinners isolados sem necessidade.
- Ações destrutivas exigem confirmação e classe de perigo existente.
