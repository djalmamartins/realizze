# Planejamento

Tela: `/p/planejamento/AAAA/MM` (mês) e `/p/planejamento/AAAA` (ano, só leitura). Também em `/e/{empresa}/planejamento`. Service: `Finance::budgets`, `saveBudget`, `deleteBudget`, `copyPreviousBudgets`, `planProjection`. Teste: `tests/finance-015-planning.php`.

## Regras de cálculo

Os valores vêm de `finance_entries`; não há tabela paralela de gastos. O mês do lançamento é o do vencimento, como em Relatórios.

| Lançamento | Tratamento |
| --- | --- |
| Pago | Realizado |
| Pendente | A pagar: previsto, já reduz o disponível |
| Cancelado | Fora de tudo |
| Transferência | Fora de tudo |
| Recorrência já gerada | Lançamento normal |
| Recorrência ainda não gerada | Previsto no resultado, sem categoria no limite |
| Pagamento de dívida | Despesa realizada; entra na categoria escolhida no pagamento |
| Parcela futura de dívida | Prevista no resultado, só na PF |

- Disponível = planejado − realizado − a pagar.
- Alerta: abaixo de 80% do limite, dentro; de 80% a 100%, perto; acima de 100%, excedido.
- Parcela de dívida com pagamento registrado no mês não é prevista de novo. Uma despesa lançada à mão para a mesma parcela só é reconhecida depois de vinculada ao pagamento; até lá, a tela avisa do risco de contar duas vezes.
