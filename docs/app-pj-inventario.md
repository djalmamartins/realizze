# App PJ: inventário técnico e contratos de backend

Auditoria da Fase 1, em 01/11/2026, sobre `feat/ui-001-finance-dashboard` (`fd72891`), com alterações de outros trabalhos sem commit na pasta. Verificado por leitura do código, consulta ao banco `realizze_dev` e navegação em Chrome headless com um usuário temporário dono de duas empresas.

## Situação das telas

Classificação do brief. "Interface parcial" significa que a tela abre com dados reais, mas não cobre tudo o que o módulo pede.

| Módulo | Rota atual | Estado | O que existe | O que falta |
| --- | --- | --- | --- | --- |
| Dashboard executivo | `/e/{slug}` | Em implementação | Saldo, resultado, a receber, a pagar, série mensal, categorias (mesma tela da PF) | Inadimplência, fluxo previsto x realizado, filtros além do período |
| Contas a receber | `/e/{slug}/receber` | Completa e integrada | `Receivables`: criar, editar, baixa parcial, cancelar, histórico, filtros, paginação | Recorrência e anexos (sem backend) |
| Contas a pagar | `/e/{slug}/pagar` | Bloqueada por dependência | `Finance`: criar, baixa total, excluir | Fornecedor, edição, baixa parcial, histórico, centro de custo |
| Movimentações | `/e/{slug}/movimentacoes` | Interface parcial | Extrato do mês, transferência, exclusão de transferência | Pesquisa, detalhes, estorno |
| Clientes | `/e/{slug}/clientes` e `/clientes/{id}` | Completa e integrada | `Contacts`: lista, detalhe com cobranças, pagamentos e totais, cadastro, edição, situação | Abas de OS e arquivos (sem backend) |
| Fornecedores | `/e/{slug}/fornecedores` | Interface parcial | Mesma entidade `finance_contacts` | Despesas não têm `contact_id`, então não há histórico de pagamentos |
| Bancos e contas | `/e/{slug}/cartoes` | Interface parcial | Listar, cadastrar, saldo calculado | Editar, arquivar, catálogo de bancos (outra conversa) |
| Cartões e faturas | `/e/{slug}/cartoes` | Bloqueada por dependência | Colunas de limite, fechamento e vencimento | Faturas, parcelamento, pagamento de fatura |
| Fluxo de caixa | `/e/{slug}/fluxo-de-caixa` | Completa e integrada | Previsto e realizado por semana ou dia, saldo projetado | Comparação entre períodos |
| Categorias | `/e/{slug}/categorias` | Interface parcial | Criar, excluir, totais | Editar, ativar e desativar, hierarquia |
| Centros de custos | sem rota | Bloqueada por dependência | Nada | Tabela, serviço e vínculo no lançamento |
| Relatórios | `/e/{slug}/relatorios` | Interface parcial | Resumo do período, categorias, série mensal, exportar CSV | Relatórios por cliente, fornecedor e inadimplência |
| DRE gerencial | sem rota | Bloqueada por dependência | Nada | Critério de classificação das categorias |
| Planejamento | `/e/{slug}/planejamento` | Interface parcial | Orçamento mensal por categoria | Planejamento anual e projeções |
| Metas | `/e/{slug}/metas` | Interface parcial | Metas de valor e aportes | Metas de receita e de redução de despesa |
| Conciliação | sem rota | Bloqueada por dependência | Nada | Importação OFX/CSV e correspondência |
| Obrigações PJ/MEI | sem rota | Bloqueada por dependência | Nada; `organizations` não guarda enquadramento | Tabela, regime da empresa, comprovantes |
| Equipe | `/e/{slug}/equipe` | Interface parcial | Lista de membros, só leitura | Convites, troca de papel, revogação |
| Convites | sem rota | Bloqueada por dependência | Tabela `organization_invitations`, sem código que a use | Serviço, e-mail, tela de aceite |
| Dados da empresa | `/e/{slug}/empresa` | Interface parcial | Dados cadastrais, só leitura | Edição, endereço, logo, preferências |
| Suporte | `/suporte` | Completa e integrada | Helpdesk da plataforma, com contexto da empresa no formulário | Rota é global, sem `/e/{slug}` |

Telas sobrando dentro da empresa: `/e/{slug}/empresarial` é a prévia antiga dos 11 módulos, hoje superada pelas telas reais; `/e/{slug}/configuracoes` e `/e/{slug}/assistente` também são prévias.

## Defeitos encontrados

| # | Gravidade | Defeito | Evidência | Responsável |
| --- | --- | --- | --- | --- |
| 1 | Alta | **Com duas abas abertas, um envio vai para a empresa errada.** As ações usam a empresa da sessão, não a da tela. | Teste real: formulário aberto em Alfa, Beta aberta depois, a categoria foi gravada em Beta | UI e Codex (contrato C1) |
| 2 | Alta | Mesma causa nos filtros: links `/minha-conta?pagina=...` seguem a sessão e trocam de empresa. | `/minha-conta?pagina=receber` a partir de Alfa abriu `/e/auditoria-beta-…/receber` | UI |
| 3 | Alta | `ReceivableActions` e `ContactActions` não checam `finance.manage`, só sessão e contexto. Um membro sem permissão poderia gravar. Hoje só existe o papel de proprietário, então não ocorre ainda. | `source/App/ReceivableActions.php:16`, `ContactActions.php:15` | Codex (C2) |
| 4 | Alta | A exportação CSV não checa permissão específica. | `FinanceActions::export` | Codex (C2) |
| 5 | Média | Só existem duas permissões, `organization.view` e `finance.manage`, e um papel, `owner`. Não dá para montar menu ou telas por permissão. | `Organizations.php:37` e banco | Codex (C3) |
| 6 | Média | A tabela de membros mostra o nome do papel, mas não permite nenhuma ação. Convidar está desativado. | `pages/equipe.php` | Depende de C3 e C4 |
| 7 | Baixa | Dois arquivos `108_` em `database/migrations`, além dos dois `003_` já conhecidos. | Listagem da pasta | Codex |
| 8 | Baixa | Fundo da área PJ igual ao da PF. O perfil PJ pede fundo verde-claro. | Captura em 1280 px | UI |
| 9 | Baixa | Menu PJ sem os grupos pedidos, e "Conexão bancária" ocupando a barra lateral sem função. | `pages/dashboard.php`, `components/sidebar.php` | UI |

Sem overflow horizontal em 1280 px e 390 px nas 20 rotas que abrem. Rotas inexistentes respondem 404 real. Testes `ui-002`, `app-005`, `finance-001/002/003` e `enterprise-001` passam.

## Componentes reutilizáveis

`sidebar`, `context-switch`, `page-heading`, `context-badge`, `finance-entries`, `receivables`, `contact-fields`, `overview-live`, `finance-empty-table`, `support-panel`, além das classes `finance-goal-summary`, `finance-table`, `finance-dialog`, `finance-filter-bar`, `finance-empty` e `finance-badge`. `enterprise-kpis` e `enterprise-module-panel` só servem à prévia antiga.

## Contratos para o Codex

Nada disto foi implementado pela interface. As telas mostram o estado real até o backend existir.

**C1. Empresa da tela no envio.** Já resolvido na camada de rotas: `dashboard.js` envia `context_organization_id` em todo POST e `routes.php` recusa sem gravar quando difere da empresa da sessão. Sem JavaScript o campo não vai e a proteção não atua; o Codex pode gravar o campo nos formulários ou exigi-lo nas Actions.

**C2. Permissão nas ações (urgente).** `ReceivableActions`, `ContactActions` e `FinanceActions::export` devem checar `Authorization::can`, como `FinanceActions::handle` já faz.

**C3. Permissões por módulo.** Chaves no formato `{modulo}.{operacao}`, com módulos `finance`, `receivables`, `payables`, `contacts`, `accounts`, `reports`, `planning`, `team` e `company`, e operações `view`, `create`, `update`, `delete`, `approve`, `export` e `manage`. Papéis padrão: `owner`, `admin`, `finance`, `accountant`, `partner` e `employee`. Migration aditiva que preserve os donos atuais. A interface precisa de `Authorization::permissionsFor(int $userId, int $organizationId): array` para montar o menu.

**C4. Equipe e convites.** Sobre `organization_invitations`: `invite(orgId, email, roleId, actor)`, `pending(orgId)`, `revoke(id, actor)`, `resend(id, actor)`, `accept(token, userId)`. Token aleatório guardado só em hash, expiração, e-mail pela fila existente. Membros: `changeRole`, `suspend`, `remove`, sempre protegendo o último proprietário, e histórico em `organization_audit_logs`.

**C5. Contas a pagar.** Espelhar `Receivables` para `kind = expense`: `contact_id`, baixa parcial em `finance_entry_settlements`, `update`, `cancel`, `history`, `listing` com filtros e chave de idempotência, sem duplicar lançamentos. Ajustar `Contacts::financialSummary` para contar despesas.

**C6. Contas.** `Finance::updateAccount(id, data)` e `archiveAccount(id)`. O campo do banco vem do catálogo central, da conversa "Criar Sair das Dívidas e catálogo de bancos".

**C7. Cartões e faturas.** `invoices(cardId)`, `invoice(cardId, month)`, `payInvoice(cardId, month, accountId, amount)` e compra parcelada, usando `closing_day` e `due_day`, no mesmo motor de `finance_entries`.

**C8. Fluxo de caixa.** A interface calcula a partir de `entries` e `movements`. Um `Finance::cashFlow(start, end, 'day'|'week'|'month')` com saldo inicial, entradas, saídas e saldo final, separando previsto e realizado, evitaria consultas por período.

**C9. Centros de custos.** Tabela `finance_cost_centers` por `financial_context_id`, coluna opcional `cost_center_id` em `finance_entries`, listagem, criação, edição, ativação e totais por período.

**C10. Categorias.** `updateCategory`, ativar e desativar sem perder histórico; `parent_id` se houver hierarquia.

**C11. DRE gerencial.** Coluna `dre_group` em `finance_categories` e `Finance::managementResult(start, end)`, por regime de caixa ou competência, escolhido explicitamente. A tela dirá sempre que não é demonstração contábil oficial.

**C12. Conciliação.** Importar OFX/CSV para área de revisão sem criar lançamentos, sugerir correspondências, confirmar item a item, detectar duplicidade por hash e guardar histórico. Nada conciliado automaticamente.

**C13. Obrigações.** Campo de regime em `organizations` (MEI, Simples, Presumido, Real) e tabela de obrigações com competência, vencimento, situação e comprovante. Sem cálculo tributário.

**C14. Dados da empresa.** `Organizations::updateProfile(id, data, actor)`, com endereço, preferências, logo via `Upload`, e histórico em `organization_audit_logs`.

**C15. Numeração das migrations.** Resolver os pares `003_` e `108_`.

## Contratos do brief ampliado (16 referências)

**C16. Cobrança.** Sobre `Receivables`, sem segundo motor: número sequencial, itens (descrição, quantidade, unidade, valor, desconto), anexos, lembretes, agendamento, filtro por forma de pagamento, PDF. PIX e boleto só com provedor autorizado.

**C17. Metas.** Em `finance_goals`: prazo por categoria, ícone, imagem, contribuição planejada, conta vinculada. Previsão de conclusão com a hipótese exposta na resposta. Conquistas só derivadas de eventos reais.

**C18. Notificações.** Sobre as tabelas `notifications` e `notification_deliveries`: listar por usuário e empresa, categoria, lida ou não lida, marcar todas, preferências, link do registro. Isolamento por empresa testado.

**C19. Integrações e Open Finance.** Catálogo de integrações administrado no CMS, estado por empresa (não configurada, conectada, sincronizando, com erro, indisponível), última sincronização e histórico reais. Sem provedor homologado, sem conexão. Nunca guardar senha bancária. Pedido de nova integração vira chamado pelo `Helpdesk`.

**C20. Ordens de serviço.** Módulo opcional por empresa, habilitado no CMS. Tabelas de OS, itens, checklist, eventos e anexos por `financial_context_id` ou organização; número sequencial; responsável é membro da empresa; "gerar cobrança" chama `Receivables::create` uma única vez com idempotência. Separado do Helpdesk.

**C21. Recorrências.** Regra em `finance_entries` ou tabela própria: frequência, próximo vencimento, cancelamento, sem gerar lançamento em duplicidade. Custo mensal e anual calculados.

**C22. Segurança.** 2FA, sessões e dispositivos, políticas por empresa e logs. A tela só mostra estados reais, sem nota percentual.

**C23. Auditoria.** Consulta paginada de `organization_audit_logs`, com filtros e permissão própria.

**C24. Configurações da empresa.** Amplia C14: inscrições, site, moeda, idioma, fuso, formato de data, numeração de cobranças e recibos, etiquetas, impostos, exportação e importação. Exclusão de empresa com fluxo protegido. Plano apenas para consulta, vindo do CMS.

**C25. Cartões (detalhe).** Amplia C7: bandeira, final, melhor dia de compra calculado, fatura aberta ou fechada, pagamento mínimo, agendamento de pagamento, tudo sem duplicar movimentações.

Todo contrato deve trazer testes de isolamento entre empresas e de permissão negada.

**C26. Recebimento parcial fora do saldo (defeito).** `Receivables::settle` só preenche `paid_at` na quitação total. Por isso `Finance::accountsWithBalance`, `movements` e o Fluxo de caixa ignoram o dinheiro de baixas parciais até a última parcela, e na quitação contam o valor inteiro na conta do lançamento, mesmo que a baixa tenha ido para outra conta. Saldo e realizado devem somar `finance_entry_settlements`, sem contar duas vezes. O saldo projetado do Fluxo de caixa continua correto porque soma o valor inteiro do que está em aberto.
