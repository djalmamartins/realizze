# Matriz de páginas

Atualizada em 07/11/2026 (Etapa 4). Toda linha abaixo passou pela varredura no navegador (Chromium, sessão do dono no banco `realizze_pjtest`) em 1440, 768 e 390 px: carregou com o título certo, sem erro de JS, de console, de rede ou de PHP e sem rolagem lateral. A navegação (rota, item no menu, título igual ao menu, permissão) é coberta por `tests/http/navigation-menu-flow.php`.

Status: **Validada** = fluxos de gravação cobertos por teste automatizado que passou nesta rodada. **Validada (leitura)** = só exibe dados; carga e números conferidos. **Sem teste de gravação** = abre e funciona na varredura, mas nenhum teste cobre salvar/editar. **Parcial** = falta função. **Bloqueada** = depende de migration no `realizze_dev`.

Bloqueio geral: no `realizze_dev` faltam as migrations 014 a 022. As páginas marcadas com (014-022) só funcionam no banco de teste até Djalma aplicá-las.

## PF (`/p`)

| Página | Status | Testes |
|---|---|---|
| Visão Geral | Validada (leitura) | `finance-017-dashboard-consistency`, `ui-005-finance-indicators` |
| Receber | Validada | `finance-002-receivables`, `finance-014-partial-settlement`, `finance-012-entry-modal` |
| Pagar | Validada | `finance-023-payables-partial`, `finance-016-recurring-in-payables` |
| Movimentações (transferências) | Validada | `finance-009-transfer-balance`, `finance-011-e2e-math` |
| Contas e Cartões | Validada | `finance-018-accounts`, `finance-004-institutions` |
| Faturas | Validada, bloqueada no `realizze_dev` (014/015) | `finance-020-card-invoices` |
| Assinaturas e recorrências | Validada | `finance-008`, `finance-010` |
| Metas | Validada | `finance-007-goal-progress`, `finance-013-goal-wallets` |
| Planejamento | Validada | `finance-015-planning` |
| Sair das Dívidas | Validada | `debt-001`, `debt-002`, `debt-003` |
| Relatórios | Validada (leitura) | `finance-006-report`, `finance-024`, `finance-025` (não redesenhar) |
| Categorias | Validada | `categories-flow`, `finance-027-category-totals` (não redesenhar) |
| Notificações | Validada | `notifications-001-app` |
| Integrações | Parcial | Página informativa: integração bancária não existe |
| Configurações / Meu perfil | Validada (troca de e-mail depende da 022) | `profile-001-security`, `security-001-csrf-session`, `privacy-lgpd-flow`, `email-change-flow` |
| Assistente, Empresarial | Parcial (prévias fora do menu) | Decisão de Djalma |

## PJ (`/e/{empresa}`)

| Página | Status | Testes |
|---|---|---|
| Dashboard | Validada (leitura) | `finance-017`, `enterprise-001-context` |
| Receber e cobranças | Validada | `finance-002`, `finance-014` |
| Pagar | Validada | `finance-019-payables-suppliers`, `finance-023`, `enterprise-payables-flow` |
| Movimentações, Recorrências | Validada | como na PF |
| Fluxo de caixa | Validada (leitura) | `finance-017`, relatórios do Codex |
| Bancos e contas | Validada | `finance-018`, `enterprise-accounts-flow` |
| Cartões e faturas | Validada (014/015) | `finance-020`, `enterprise-invoices-flow` |
| Conciliação bancária | Validada (014); paginada (50 por página) | `finance-021`, `enterprise-reconciliation-flow`, `reconciliation-filters-flow` |
| Clientes, Fornecedores (+ detalhe) | Validada | `finance-003-contacts`, `contact-filters-flow` |
| Categorias | Validada | `categories-flow`; DRE por grupo depende da 020 |
| Centros de custo | Validada (014) | `finance-022-cost-centers` |
| Relatórios | Validada (leitura) | `enterprise-009/010/011`, `ui-006` |
| DRE gerencial | Validada (leitura, 020) | `enterprise-004-management-dre` |
| Planejamento, Metas | Validada | como na PF |
| Obrigações | Validada (018) | `enterprise-006`, `enterprise-business-modules-flow`, `obligation-filters-flow` |
| Ordens de serviço | Validada (019) | `enterprise-007`, `enterprise-008`, `service-order-filters-flow` |
| Equipe, Permissões | Validada (016) | `enterprise-002-team`, `enterprise-permissions-flow`, `enterprise-invite-login-flow` |
| Configurações da empresa (logotipo, enquadramento) | Validada (017, 021) | `enterprise-003`, `enterprise-005-company-logo`, `enterprise-company-flow` |
| Segurança | Validada | `enterprise-security-flow` |
| Auditoria | Validada (leitura) | `enterprise-003-company-audit` |
| Integrações, Notificações | Como na PF | |

## Suíte nesta rodada

70 testes PHP rodados contra o `realizze_pjtest`: 69 passaram. `core-001-email-smtp` exige `TEST_MAIL_TO` e envio real, por isso não foi executado. Seis testes chamam o Apache real (`auth-002-http`, `app-005-routes`, `cms-100-e2e`, `core-support-helpers`, `core-007-environment-router`, `email-confirmation-http-v1`) e ficaram de fora, para não tocar no `realizze_dev`; eles entram na homologação (Etapa 7). Os três testes Node (`ui-003`, `ui-004`, `cms-003`) passam. Os fluxos HTTP novos desta missão também passam.

> A auditoria do Codex de 07/11 (filtros e catálogo de relatórios) está em `docs/release/V1_*.md`; esta lista consolida as duas.
