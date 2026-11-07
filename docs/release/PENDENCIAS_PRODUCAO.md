# Pendências de produção — Realizze 2.0

Atualizado em 07/11/2026 após o ciclo 4.

## Impeditivos

1. **Aplicar e validar a migration 014 no banco do ambiente servido.** Conciliação bancária e Centros de custo carregam, mas mostram a mensagem de estrutura ausente. A migration deve seguir o processo normal de homologação, backup e rollback; este ciclo não alterou `realizze_dev`.
2. **Reexecutar o smoke HTTP com uma fixture autenticável do banco isolado.** O script está pronto, mas os usuários documentados dos fluxos PJ não existem em `realizze_migration014_20261103`.

## Riscos não impeditivos para o código

- Exportações CSV/PDF da Central de Relatórios foram verificadas quanto à presença e permissões, mas não foram acionadas na sessão compartilhada.
- Envios destrutivos de formulários foram deliberadamente evitados no Apache. A cobertura correspondente passou em banco isolado com limpeza/rollback.
- A conferência responsiva foi feita pelos componentes compartilhados e testes de CSS; recomenda-se uma rodada visual final em aparelhos reais após a migration.

## Trabalho restante estimado

- Migration 014 em homologação, smoke e rollback ensaiado: **2–4 horas**, dependendo do volume e da janela do banco.
- Fixture isolada e nova execução automatizada das 43 rotas: **1–2 horas**.
- Rodada visual final mobile/desktop e exportações: **2–3 horas**.

Estimativa total fundamentada: **5–9 horas**, sem incluir janela de mudança ou aprovação operacional. O código não possui conflito conhecido; o bloqueio atual é de preparação do ambiente.

## Restrições respeitadas

- Nenhum reset destrutivo, force push, push ou deploy.
- Nenhuma transação financeira na interface compartilhada.
- `realizze_dev` não foi modificado.
- Testes financeiros executados somente em `realizze_migration014_20261103`, dentro da rede Docker.
