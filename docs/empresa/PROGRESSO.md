# Módulo Empresa (PJ): progresso

Controle da missão de concluir o ambiente Empresa do App, na `dev-claude`. O código é a fonte de verdade; este arquivo diz o que foi conferido e onde parou.

## Ponto de partida (04/11/2026)

- A `dev-claude` estava contida na `main` (0 commits próprios). Foi avançada por fast-forward até `810fe02`, sem merge commit, para trazer cartões, conciliação, centros de custo e as migrations 014 e 015 do Codex.
- Testes rodam no container `realizze-pjtest-db` (banco `realizze_pjtest`, cópia do backup de 02/11 com as migrations até a 016). O `realizze_dev` não é tocado.
- Usuários de teste só nesse banco: `pj-dono@example.invalid` (dono de "Missao Alfa Ltda" e "Missao Beta ME") e `pj-leitor@example.invalid` (Visualizador na Alfa).
- Verificação HTTP: `php -S` temporário dentro do `dev-apache`, com `DB_*` do banco isolado e `TMPDIR` próprio (o limitador de login guarda arquivos no diretório temporário e seria compartilhado com o App real), e um cliente que reenvia o cookie Secure à mão (`tests/http/client.php`).
- `registration-002-signup` falha neste ambiente por permissão nos arquivos do limitador em `/tmp/realizze-auth-limits` do `dev-apache`; não tem relação com o módulo Empresa.

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
| Dashboard | Concluída | Baixa parcial de recebimento passou a contar em Receitas, Resultado, A receber, Fechamento previsto e no gráfico mensal (antes sumia, embora entrasse no saldo); "A pagar vencido" leva a Pagar filtrado e mostra a quantidade; cabeçalho e aba dizem Dashboard. `Finance::summary` e `monthlySeries` são compartilhados com a PF: suíte inteira rodada | `finance-017-dashboard-consistency` + suíte PF | ver abaixo |
| Bancos e contas | Concluída | Cadastro de cartão com limite, fechamento e vencimento (o formulário não tinha esses campos e Faturas dependia deles); editar nome, instituição e regras do cartão; arquivar só com saldo zero, sem lançamento em aberto, sem reserva de meta e sem assinatura ativa, para o saldo consolidado não mudar. Tipo e saldo inicial não se editam | `finance-018-accounts`, `tests/http/enterprise-accounts-flow.php`, capturas 1440 e 390 px | ver abaixo |
| Pagar | Concluída | Fornecedor e centro de custo na despesa (criar e editar; editar sem os campos na tela mantém o que está gravado); fornecedor e centro aparecem na linha; filtros por fornecedor e centro de custo com chip. Baixa parcial de despesa não é suportada: o saldo das contas soma toda baixa como entrada, e mudar isso mexe no saldo da PF; fica registrado como pendência | `finance-019-payables-suppliers`, `tests/http/enterprise-payables-flow.php`, capturas | ver abaixo |
| Fornecedores | Concluída | Detalhe do fornecedor com contas a pagar, pagamentos e resumo (a pagar, em atraso, pago, total) | `tests/http/enterprise-payables-flow.php` | ver abaixo |
| Cartões e faturas | Concluída | Correções no serviço do Codex: (1) a parcela guardava um vencimento que não caía na janela da fatura, e com vencimento antes do fechamento ficava antes da própria compra; agora cada compra vai para a fatura do fechamento e vence no primeiro dia de vencimento depois dele; (2) pagar a fatura criava uma despesa além das compras, contando a despesa duas vezes e deixando o cartão negativo; agora é transferência da conta para o cartão; (3) reenviar o formulário de pagamento dava erro em vez de devolver o pagamento já feito; (4) o estorno apagava a saída e, pela FK em cascata, o próprio registro do pagamento; agora cancela a transferência. Tela: navegação por fatura, pagamento total ou parcial em janela própria, histórico de pagamentos com estorno, categoria e centro de custo na compra | `finance-020-card-invoices`, `tests/http/enterprise-invoices-flow.php`, capturas | ver abaixo |
| Centros de custo | Concluída | Editar nome, código e situação; inativo some dos formulários e guarda o histórico; nome ou código repetido com mensagem clara (antes era erro genérico); resultado do mês por centro, com realizado e previsto e a linha "Sem centro de custo"; nome do centro leva a Pagar filtrado. Receber ignorava o centro escolhido na modal; agora grava e mantém ao editar | `finance-022-cost-centers`, `finance-019-payables-suppliers`, captura | ver abaixo |
| Conciliação | Concluída | Sugestões de lançamento (mesma conta, valor e sentido, até 7 dias, ainda não conciliado), conciliar, ignorar e desfazer; o serviço passou a recusar conciliação com lançamento de outra conta ou valor; importação de OFX; CSV com vírgula ou BOM (antes só funcionava com ponto e vírgula); mesmo arquivo recusado com mensagem clara; filtros por situação e conta; contadores. Nenhum saldo muda | `finance-021-reconciliation`, `tests/http/enterprise-reconciliation-flow.php`, capturas | ver abaixo |
| Rotas que sobraram | Concluída | `/empresarial` e `/assistente` levam ao Dashboard, `/configuracoes` a Configurações, `/contatos` a Clientes; aba do navegador diz Dashboard; textos "Em breve" de módulos que já existem removidos | `tests/http/enterprise-security-flow.php` | ver abaixo |

| Fluxo de caixa | Concluída | O realizado passou a contar cada baixa na data em que entrou (antes a baixa parcial sumia e a quitação entrava com o valor inteiro na última data); o previsto de Receber usa só o que falta receber | `finance-017-dashboard-consistency` | ver abaixo |
| Relatórios | Concluída | Na empresa, painel "Resultado por centro de custo" no período; o restante (dia a dia, categorias, série mensal, CSV, PDF) já existia | captura | ver abaixo |
| DRE gerencial | Bloqueada | Falta a decisão de Djalma entre caixa e competência e a classificação das categorias em receita, custo e despesa. A tela diz isso, sem números simulados | — | — |
| Obrigações PJ/MEI, Ordens de serviço | Bloqueada | Fora das etapas desta missão e sem tabela; continuam "Em breve" com o que falta | — | — |

| Permissões (PJ-03) | Concluída | Matriz por HTTP no MySQL: Visualizador e Operacional recebem 403 em todas as 20 ações financeiras de escrita, não exportam, não convidam, não abrem a auditoria nem outra empresa; Operacional cadastra clientes (`contacts.manage`). Nenhuma linha mudou no banco | `tests/http/enterprise-permissions-flow.php` | ver abaixo |
| Papéis de consulta na tela | Concluída | Quem não tem `finance.manage` vê um aviso e não vê botões de lançar, editar, baixar ou excluir (CSS por `data-readonly` no `body`); sem `contacts.manage`, some o cadastro de contatos. É conveniência: o servidor continua recusando | `tests/http/enterprise-permissions-flow.php`, captura | ver abaixo |
| Convite depois do login (PJ-13) | Concluída | O login recriava a sessão e perdia o convite; agora volta para `/convite-empresa`, inclusive com 2FA | `tests/http/enterprise-invite-login-flow.php` | ver abaixo |

## Migrations da missão

| Migration | O que faz | Depende de | Aplicada em |
|---|---|---|---|
| `016_allow_organization_team_roles.sql` | Troca a restrição de `organization_roles.role_key` para aceitar os papéis de `OrganizationTeam::ROLES` | base de organizações | só `realizze_pjtest` |

O `realizze_dev` precisa das 014, 015 e 016 para Faturas, Conciliação, Centros de custo e Equipe funcionarem. Quem aplica é Djalma.

## Decisões

- Dados de cartão criados antes da correção das faturas mantêm o vencimento antigo, e um pagamento de fatura antigo continua como despesa. No `realizze_dev` isso precisa de conferência manual antes de valer como dado real.

- Atenção: no `dev-apache`, `app.realizze.lab` serve a pasta `realizze-main-integration` (a `main`), não esta pasta. As mudanças da `dev-claude` não aparecem ali até serem integradas. As capturas desta missão usam o servidor temporário e os arquivos de `public/` desta pasta.
- Saldo inicial de conta não é editável: o saldo vem dos lançamentos e uma correção entra como lançamento de ajuste.

- Visual PJ conferido em captura: barra lateral verde-escura com logo branco e fundo verde-claro, diferente da PF (roteiro PJ-18).
- Logo da empresa: não há coluna nem armazenamento para ele. O botão desativado saiu da tela; fica como pendência, sem migration nova por enquanto.
- A auditoria registra o que o serviço aceitou, a partir de `TeamActions`, das rotas de aceite e recusa do convite e de `Organizations::update`. Falha ao gravar o registro vai para o log do servidor e não desfaz a ação.

- Editar os dados da empresa exige `team.manage` (proprietário e Administrador, cujo papel diz "Gerencia dados e pessoas da empresa"). Não foi criada permissão nova.
