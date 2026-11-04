# Módulo Empresa (PJ): progresso

Controle da missão de concluir o ambiente Empresa do App, na `dev-claude`. O código é a fonte de verdade; este arquivo diz o que foi conferido e onde parou.

## Ponto de partida (04/11/2026)

- A `dev-claude` estava contida na `main` (0 commits próprios). Foi avançada por fast-forward até `810fe02`, sem merge commit, para trazer cartões, conciliação, centros de custo e as migrations 014 e 015 do Codex.
- Testes rodam no container `realizze-pjtest-db` (banco `realizze_pjtest`, cópia do backup de 02/11 com as migrations até a 016). O `realizze_dev` não é tocado.
- Usuários de teste só nesse banco: `pj-dono@example.invalid` (dono de "Missao Alfa Ltda" e "Missao Beta ME") e `pj-leitor@example.invalid` (Visualizador na Alfa).
- Verificação HTTP: `php -S` temporário dentro do `dev-apache`, com `DB_*` do banco isolado, e um cliente que reenvia o cookie Secure à mão.

## Inventário real das rotas `/e/{empresa}`

Levantado logado como dono e como Visualizador, lendo rota, página e serviço de cada tela. Nenhuma rota respondeu 500 nem mostrou aviso PHP.

| Rota | Tela | Estado no início | Observação |
|---|---|---|---|
| `/` | Dashboard | Funcional | `executive-panel` + visão geral da PF. Indicadores reais |
| `/receber` | Receber e cobranças | Funcional | `Receivables` completo |
| `/pagar` | Pagar | Parcial | Sem fornecedor nem baixa parcial (roteiro PJ-04) |
| `/movimentacoes` | Movimentações | Funcional | Extrato e transferências |
| `/recorrencias` | Assinaturas | Funcional | Serviço `RecurringFinance` |
| `/fluxo-de-caixa` | Fluxo de caixa | Funcional | Previsto e realizado separados |
| `/cartoes` | Bancos e contas | Funcional | Contas, cartões, saldos |
| `/faturas` | Cartões e faturas | Funcional (Codex) | `CardInvoices`, migrations 014 e 015 |
| `/conciliacao` | Conciliação | Parcial (Codex) | Importa CSV e ignora linhas; a tela não tem como conciliar com um lançamento |
| `/clientes`, `/fornecedores` | Cadastros | Funcional | `Contacts` |
| `/categorias` | Categorias | Funcional | Compartilhada com PF |
| `/centros-de-custo` | Centros de custo | Parcial (Codex) | Cria; a tela não edita nem inativa |
| `/relatorios` | Relatórios | Funcional | `Finance::report` |
| `/dre` | DRE gerencial | Ausente | "Em breve"; depende da decisão caixa ou competência (PJ-09) |
| `/planejamento`, `/metas` | Gestão | Funcional | Compartilhadas com PF |
| `/obrigacoes` | Obrigações PJ/MEI | Ausente | "Em breve"; sem enquadramento no banco |
| `/ordens-de-servico` | Ordens de serviço | Ausente | "Em breve" |
| `/equipe` | Equipe | Com erro | Primeiro convite falhava no MySQL (corrigido pela 016) |
| `/permissoes` | Permissões | Funcional | Catálogo de `OrganizationTeam` |
| `/empresa` | Configurações | Parcial | Só leitura; logo desativado |
| `/seguranca` | Segurança | Ausente | "Em breve" com texto desatualizado |
| `/auditoria` | Auditoria | Ausente | Tabela `organization_audit_logs` existe e ninguém grava |
| `/integracoes`, `/notificacoes` | Plataforma | Funcional | Compartilhadas |
| `/empresarial`, `/configuracoes`, `/assistente`, `/contatos` | Sobras | Prévias antigas fora do menu | Redirecionar (PJ-15) |

## Execução

| Página | Estado | Funcionalidades | Testes | Commit |
|---|---|---|---|---|
| Equipe | Concluída | Migration 016 libera os papéis do catálogo no MySQL; convites, papel, suspensão e remoção registrados na auditoria | `enterprise-002-team`, `enterprise-003-company-audit`, `tests/http/enterprise-company-flow.php` | ver abaixo |
| Configurações da empresa | Concluída | Editar razão social, nome fantasia, CNPJ (com dígito verificador), e-mail e telefone; só leitura para quem não tem `team.manage`; últimas alterações | `enterprise-003-company-audit`, fluxo HTTP | ver abaixo |
| Auditoria | Concluída | Consulta de `organization_audit_logs` com filtros por ação, pessoa e período, paginação; exige `team.manage` na rota e no menu | `enterprise-003-company-audit`, fluxo HTTP | ver abaixo |
| Segurança | Concluída | 2FA e sessões da própria conta; para quem tem `team.manage`, 2FA de cada membro e mudanças de acesso recentes. Não existe política por empresa (exigir 2FA): dito na tela | `tests/http/enterprise-security-flow.php` | ver abaixo |
| Dashboard | Pendente | Conferir números com os serviços | — | — |
| Pagar | Pendente | Fornecedor, baixa parcial, histórico | — | — |
| Centros de custo | Pendente | Editar, inativar, filtro | — | — |
| Conciliação | Pendente | Conciliar linha com lançamento existente | — | — |
| Rotas que sobraram | Concluída | `/empresarial` e `/assistente` levam ao Dashboard, `/configuracoes` a Configurações, `/contatos` a Clientes; aba do navegador diz Dashboard; textos "Em breve" de módulos que já existem removidos | `tests/http/enterprise-security-flow.php` | ver abaixo |

## Migrations da missão

| Migration | O que faz | Depende de | Aplicada em |
|---|---|---|---|
| `016_allow_organization_team_roles.sql` | Troca a restrição de `organization_roles.role_key` para aceitar os papéis de `OrganizationTeam::ROLES` | base de organizações | só `realizze_pjtest` |

O `realizze_dev` precisa das 014, 015 e 016 para Faturas, Conciliação, Centros de custo e Equipe funcionarem. Quem aplica é Djalma.

## Decisões

- Logo da empresa: não há coluna nem armazenamento para ele. O botão desativado saiu da tela; fica como pendência, sem migration nova por enquanto.
- A auditoria registra o que o serviço aceitou, a partir de `TeamActions`, das rotas de aceite e recusa do convite e de `Organizations::update`. Falha ao gravar o registro vai para o log do servidor e não desfaz a ação.

- Editar os dados da empresa exige `team.manage` (proprietário e Administrador, cujo papel diz "Gerencia dados e pessoas da empresa"). Não foi criada permissão nova.
