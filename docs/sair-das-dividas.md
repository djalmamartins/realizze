# Sair das Dívidas

Módulo só da PF: `/p/sair-das-dividas`. Service: `Debts`; cálculos: `DebtPlanner`. Testes: `tests/debt-001-planner.php`, `debt-002-module.php`, `debt-003-control.php`.

## Migration 013

`013_extend_finance_debts.sql` é aditiva e precisa ser aplicada por Djalma com `aplicar-migrations.sh`. Sem ela, a tela funciona como antes e esconde estorno, descrição e aplicação automática do acordo; o duplo clique fica protegido só pela sessão (`Debts::controlReady`).

## Pagamento

Tudo na mesma transação, com a linha da dívida travada, e com `idempotency_key` para o mesmo envio não pagar duas vezes.

- Com lançamento vinculado: baixa a despesa, sem criar outra. O valor precisa ser igual.
- Sem vínculo: cria a saída na conta escolhida ou na conta da dívida.
- "Pago por fora": abate a dívida sem mexer em conta.
- Parcela cheia (valor maior ou igual à parcela) avança o vencimento e reduz as parcelas. Pagamento parcial, não.
- A saída ligada a um pagamento não pode ser editada nem excluída em Pagar.

## Estorno

Mantém a linha no histórico, marcada com `reversed_at`, e devolve o saldo. A saída criada pelo pagamento é removida; a despesa baixada volta a ficar em aberto; a despesa que já estava paga continua paga, só sem o vínculo. Vencimento e parcelas só voltam se não houver pagamento posterior.

## Renegociação

Proposta não muda a dívida. Ao aceitar, o saldo vira o total do acordo, a taxa vai a 0% porque os juros já estão nas parcelas, e os termos anteriores ficam em `previous_terms`. Só um acordo vale por vez. Rompido ou recusado, voltam os termos anteriores, descontado o que foi pago durante o acordo.

## Simulações

Dívida sem taxa informada entra com 0%, para não sumir do plano, e a tela avisa que prazo e custo podem ser maiores. Nenhuma taxa é estimada. Sem valor informado, o plano usa o maior entre a capacidade do diagnóstico e a soma das parcelas. As premissas ficam em "Hipóteses usadas".
