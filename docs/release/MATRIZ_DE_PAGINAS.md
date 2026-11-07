# Matriz de páginas

Legenda: **Validada** (testada por fluxo real nesta missão), **Corrigir**, **Parcial**, **Não implementada**, **Bloqueada**. "Navegação OK" significa que a rota existe, está no menu certo, abre com 200 e respeita permissões (`navigation-menu-flow`). A coluna Funcional traz o último estado registrado e será revalidada na Etapa 4.

## PF (`/p`)

| Página | Rota | Navegação | Funcional | Observação |
|---|---|---|---|---|
| Visão Geral | `/p` | OK | a revalidar | |
| Receber | `/p/receber` | OK | a revalidar | Referência de filtros para o PJ |
| Pagar | `/p/pagar` | OK | a revalidar | |
| Movimentações | `/p/movimentacoes` | OK | a revalidar | Transferências ficam aqui |
| Contas e Cartões | `/p/cartoes` | OK | a revalidar | |
| Faturas | `/p/faturas` | OK (novo no menu) | a revalidar | Depende da 014/015 no `realizze_dev` |
| Assinaturas e recorrências | `/p/recorrencias` | OK | a revalidar | |
| Metas | `/p/metas` | OK | a revalidar | |
| Planejamento | `/p/planejamento` | OK | a revalidar | |
| Sair das Dívidas | `/p/sair-das-dividas` | OK | a revalidar | |
| Relatórios | `/p/relatorios` | OK | satisfatória (Djalma) | Não redesenhar |
| Categorias | `/p/categorias` | OK | satisfatória (Djalma) | Não redesenhar |
| Notificações | `/p/notificacoes` | OK (novo no menu) | a revalidar | |
| Integrações | `/p/integracoes` | OK | informativa | Sem integração bancária ainda |
| Configurações / Meu perfil | `/p/perfil` | OK | a revalidar | `/p/configuracoes` redireciona |
| Assistente, Empresarial | `/p/assistente`, `/p/empresarial` | fora do menu | Parcial (prévia) | Decisão pendente |

## PJ (`/e/{empresa}`)

| Página | Rota | Navegação | Funcional (PROGRESSO.md) |
|---|---|---|---|
| Dashboard | `/e/{e}` | OK | Concluída |
| Receber e cobranças | `/receber` | OK | a revalidar |
| Pagar | `/pagar` | OK | Concluída |
| Movimentações | `/movimentacoes` | OK | a revalidar |
| Recorrências | `/recorrencias` | OK | a revalidar |
| Fluxo de caixa | `/fluxo-de-caixa` | OK | Concluída |
| Bancos e contas | `/cartoes` | OK | Concluída |
| Cartões e faturas | `/faturas` | OK | Concluída |
| Conciliação | `/conciliacao` | OK | Concluída |
| Clientes / Fornecedores (+ detalhe) | `/clientes`, `/fornecedores` | OK | Concluída |
| Categorias | `/categorias` | OK | a revalidar |
| Centros de custo | `/centros-de-custo` | OK | Concluída |
| Relatórios | `/relatorios` | OK (exige reports.view) | Concluída |
| DRE gerencial | `/dre` | OK (exige reports.view; corrigido no menu) | Concluída |
| Planejamento, Metas | `/planejamento`, `/metas` | OK (exige planning.view) | a revalidar |
| Obrigações | `/obrigacoes` | OK | Concluída |
| Ordens de serviço | `/ordens-de-servico` | OK | Concluída |
| Equipe, Permissões, Segurança, Configurações | `/equipe`, `/permissoes`, `/seguranca`, `/empresa` | OK | Concluída |
| Auditoria | `/auditoria` | OK (exige team.manage) | Concluída |
| Integrações, Notificações, Ajuda | `/integracoes`, `/notificacoes`, `/suporte` | OK | a revalidar |
