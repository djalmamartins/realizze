# Notificações do App

Fonte única: tabela `notifications`, lida por `Moves\Services\Notifications`. Sininho (`components/app-toolbar.php`) e tela (`pages/notificacoes.php`) usam o mesmo `feed`.

## Origens

- **Automáticas** (`Notifications::sync`): vencimento em até 7 dias (`finance.due_soon`), hoje (`finance.due_today`), em atraso (`finance.overdue`), orçamento ultrapassado (`finance.budget_exceeded`) e meta com prazo em até 7 dias ou vencido (`finance.goal_deadline`). Há uma linha por registro: mudar de estágio volta a marcar como não lida, e o aviso sai da lista quando o registro é pago, cancelado ou deixa de valer.
- **CMS** (`CmsBroadcasts`): comunicados para clientes aparecem em qualquer área do usuário. Agendados saem no próximo acesso ao App ou pelo processador.
- **Suporte** (`HelpdeskNotifier`): respostas de chamados, como antes.

A sincronização roda a cada tela para quem está usando. `php scripts/process-notifications.php`, quando agendado no cron, sincroniza todos e também é o que permitirá enviar push com o usuário fora do App.

## Preferências

Na tela de Notificações, cada usuário escolhe a antecedência dos vencimentos (7, 3 ou 1 dia, ou só no dia) e liga ou desliga atrasos, orçamento e metas (`notification_preferences`). Desligar retira os avisos já mostrados na próxima sincronização. Comunicados e suporte não são afetados.

## Isolamento

Avisos financeiros têm `financial_context_id`. O usuário só vê os da área aberta, e os da empresa exigem `finance.manage`. `/suporte/notificacoes` não mostra avisos financeiros.

## Clique

`POST /minha-conta/notificacoes/abrir` confere o dono, marca como lida e redireciona. O destino vem de `target_json`, ou de `link` nas notificações do CMS e do suporte. No CMS, um link curto como `/pagar` abre a tela na área atual do usuário. A rota da empresa revalida o acesso.

## Push (estrutura, sem envio)

Migration `109_extend_notifications_for_app_and_push.sql`:

- `notification_devices`: aparelho por usuário, plataforma (`ios`, `android`), provedor (`apns`, `fcm`), token.
- `notification_deliveries`: canal `push`, com `notification_id` e `device_id`.
- `notifications.target_json`: destino neutro, como `{"screen":"pagar","entity":"finance_entry","id":7,"params":{"mes":"2026-11"}}`.

`Notifications::payload()` é o contrato para os apps. Falta: API de registro de aparelho, API da caixa, envio em `ExternalNotifications::process` (hoje um canal sem transporte é marcado como `skipped`) e preferências por categoria. Antes de enviar, decidir se valores aparecem na tela bloqueada.

Sem a migration 109, o App mostra os avisos calculados e as notificações existentes, sem marcar como lida os avisos financeiros.

Teste: `php tests/notifications-001-app.php` (exige a 109).
