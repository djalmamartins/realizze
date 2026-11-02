# Metas do App

Tela `pages/metas.php`, com `components/goal-card.php`, `goal-wizard.php` (criação em 5 passos), `goal-fields.php` (edição) e `goal-icon.php`. Dados em `finance_goals` e `finance_goal_contributions`, sempre no contexto financeiro atual.

- **Foto:** `Services/GoalPhotos.php`. JPG ou PNG até 5 MB (o GD deste ambiente não lê WEBP), regravado em JPEG de até 1600 px (descarta metadados como localização). Fica em `storage/finance/goals`, fora de `public/`, e sai por `/minha-conta/financeiro/meta-foto`, só para o contexto dono da meta.
- **Onde está juntando:** nome da caixinha, mais banco do catálogo (`institution_id`) ou texto livre (`institution`). É informativo.
- **Aportes fora das contas:** manuais, com data. Remover um aporte reduz o valor guardado. A meta passa a "alcançada" e volta a "ativa" conforme o saldo e o valor-alvo.
- **Análise da criação:** `Finance::goalBaseline` traz as médias de lançamentos pagos dos últimos 3 meses completos (divididas pelos meses que têm lançamento) e as contribuições de outras metas ativas; o cálculo dos cenários fica em `dashboard.js`, sem juros nem rendimento. Atual usa a contribuição informada; recomendado, o valor que chega no prazo (sem prazo, metade da sobra); acelerado, toda a sobra. Sem histórico, a tela diz isso e não estima a sobra.
- **Listagem:** abas por prazo em `?prazo=` (curto até 1 ano, médio até 3, longo acima; sem prazo tem aba própria), ordenação em `?ordem=`.
- **Previsão e situação:** `Finance::goalForecast`, por mês. O ritmo é a média dos aportes dos últimos 90 dias, sem contar os do dia em que a meta foi criada (o que já estava guardado); sem aportes recentes, a contribuição planejada. "Você pode antecipar" usa a sobra real de `goalBaseline`. A dica lateral é regra simples, sem IA.
- **Ritmo mensal:** `Finance::goalProgress`, divisão simples do que falta pelos meses até o prazo, contando o mês atual, sem juros nem rendimento.

A migration `009_extend_finance_goals.sql` (exige a 006) adiciona foto, local, categoria, ícone e contribuição mensal. Sem ela, a tela funciona sem esses campos (`Finance::supportsGoalDetails`).

**Previsto, não implementado:** integração bancária. `institution_id` já identifica o banco de cada meta, mas nada lê saldo nem movimenta dinheiro.

Teste: `php tests/finance-007-goal-progress.php`.

## Carteiras virtuais (migration 012)

Cada meta tem uma carteira: dinheiro separado de uma conta real. Não existe conta bancária fictícia. Sem a 012, a tela segue com os aportes manuais (`GoalWallets::ready`).

**Fórmulas, só em `Finance::accountsWithBalance` e `Finance::balanceSummary`:**

- `saldo_total` = saldo inicial + entradas pagas - saídas pagas +/- transferências.
- `saldo_reservado` = soma de `delta_cents` das operações da conta.
- `saldo_disponivel` = `saldo_total` - `saldo_reservado`.
- Guardado na meta = `saved_cents` (registrado fora das contas) + reservas da carteira. Cartões ficam de fora.

**Efeito de cada operação:**

| Operação | Saldo total | Reservado | Disponível | Lançamento |
|---|---|---|---|---|
| Guardar | igual | sobe | cai | nenhum |
| Resgatar | igual | cai | sobe | nenhum |
| Transferir entre metas | igual | igual | igual | nenhum |
| Pagar com a meta | cai | cai | igual | 1 despesa paga |
| Despesa comum paga | cai | igual | cai | 1 despesa paga |

**Regras:** o histórico `finance_goal_wallet_operations` nunca é alterado; um estorno é uma nova linha, e excluir a despesa paga com a meta estorna o pagamento. A chave do envio (`operation_key`) impede duplicidade. A reserva trava a linha da conta (`FOR UPDATE`) antes de ler o saldo, e aportes simultâneos não passam do disponível. Reserva nova nunca passa do disponível. Pagamentos e transferências seguem a política atual de saldo negativo: se consumirem o reservado, a carteira não muda, e o App avisa (`reservation-alert.php` e o aviso depois de salvar). Excluir a meta devolve as reservas às contas.

**Aportes programados:** `finance_goal_schedules`, uma programação por meta. Não usa `RecurringFinance`, porque ela gera lançamentos, e reserva não é despesa. Nada roda sozinho: o agendador é `scripts/process-goal-schedules.php`, que precisa de um cron ainda não ativado. Sem disponível, o aporte do mês não acontece e fica registrado.

**Planejamento:** o "disponível" dessa tela é orçamento menos gasto, outro conceito, e não foi alterado.

**Aplicar a 012** (depende só da 005, e pode rodar antes ou depois de 006 a 011; faça backup antes):

```bash
docker exec -i dev-mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --default-character-set=utf8mb4 realizze_dev' < database/migrations/012_create_finance_goal_wallets.sql
```

Rollback: o mesmo comando com `012_create_finance_goal_wallets.down.sql`. Ele apaga o histórico das carteiras. As reservas somem e o disponível volta a ser igual ao total; nenhum saldo bancário muda.

Teste, sempre em banco temporário (o teste recusa o `realizze_dev`): `php tests/finance-013-goal-wallets.php`.
