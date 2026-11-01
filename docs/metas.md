# Metas do App

Tela `pages/metas.php`, com `components/goal-card.php`, `goal-wizard.php` (criação em 5 passos), `goal-fields.php` (edição) e `goal-icon.php`. Dados em `finance_goals` e `finance_goal_contributions`, sempre no contexto financeiro atual.

- **Foto:** `Services/GoalPhotos.php`. JPG ou PNG até 5 MB (o GD deste ambiente não lê WEBP), regravado em JPEG de até 1600 px (descarta metadados como localização). Fica em `storage/finance/goals`, fora de `public/`, e sai por `/minha-conta/financeiro/meta-foto`, só para o contexto dono da meta.
- **Onde está juntando:** nome da caixinha, mais banco do catálogo (`institution_id`) ou texto livre (`institution`). É informativo.
- **Aportes:** manuais, com data. Remover um aporte reduz o valor guardado. A meta passa a "alcançada" e volta a "ativa" conforme o saldo e o valor-alvo.
- **Análise da criação:** `Finance::goalBaseline` traz as médias de lançamentos pagos dos últimos 3 meses completos (divididas pelos meses que têm lançamento) e as contribuições de outras metas ativas; o cálculo dos cenários fica em `dashboard.js`, sem juros nem rendimento. Atual usa a contribuição informada; recomendado, o valor que chega no prazo (sem prazo, metade da sobra); acelerado, toda a sobra. Sem histórico, a tela diz isso e não estima a sobra.
- **Listagem:** abas por prazo em `?prazo=` (curto até 1 ano, médio até 3, longo acima; sem prazo tem aba própria), ordenação em `?ordem=`.
- **Previsão e situação:** `Finance::goalForecast`, por mês. O ritmo é a média dos aportes dos últimos 90 dias, sem contar os do dia em que a meta foi criada (o que já estava guardado); sem aportes recentes, a contribuição planejada. "Você pode antecipar" usa a sobra real de `goalBaseline`. A dica lateral é regra simples, sem IA.
- **Ritmo mensal:** `Finance::goalProgress`, divisão simples do que falta pelos meses até o prazo, contando o mês atual, sem juros nem rendimento.

A migration `009_extend_finance_goals.sql` (exige a 006) adiciona foto, local, categoria, ícone e contribuição mensal. Sem ela, a tela funciona sem esses campos (`Finance::supportsGoalDetails`).

**Previsto, não implementado:** integração bancária. `institution_id` já identifica o banco de cada meta, mas nada lê saldo nem movimenta dinheiro.

Teste: `php tests/finance-007-goal-progress.php`.
