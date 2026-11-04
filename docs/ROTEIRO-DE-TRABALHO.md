# Roteiro de trabalho do Realizze 2.0

Auditoria de 02/11/2026 no branch `feat/realizze-integracao-ui-20261101`. Esta é a lista oficial do que fazer. A rotina "Melhorias contínuas do Realizze" pega o primeiro item `[ ]` sem a marca "Decisão" ou "Djalma", faz, testa e marca com o commit.

**Ordem:** PF primeiro (decisão de 01/11/2026), depois PJ, CMS e site. Dentro de cada área, P0 impede o uso ou mostra número errado, P1 é função que falta para lançar, P2 é melhoria.

## Regras de execução

- Trabalhar só neste branch, com um commit local por item e sem push.
- Sem `reset --hard`, `clean` nem force push. Não mexer em `vendor/` nem no `.env`.
- Migrations só aditivas. Quem aplica no `realizze_dev` é Djalma, com `aplicar-migrations.sh`.
- Testes só em banco temporário, nunca no `realizze_dev`. Receita em "Como foi verificado".
- Sem valores financeiros fictícios nas telas. Módulo sem backend diz o que falta.

## Como foi verificado

Subi um MySQL temporário (`rz-audit-db`) com o backup de 02/11 mais as migrations, e um Apache temporário com as montagens do `dev-apache`. Nada tocou no `realizze_dev`.

- **Páginas:** um rastreador logado seguiu os links de site, App PF, App PJ e CMS, com dados de `scripts/seed-finance-demo.php`. Foram 20 respostas no site, 400 no App PF, 400 no App PJ e 123 no CMS. Nenhum erro 500 e nenhum aviso PHP visível nas páginas, e o log de erros do App ficou vazio. No App, o rastreador parou no limite de 400 páginas por área, e na PJ só percorreu a empresa Auditoria Alfa, sem dados de exemplo. As telas principais foram abertas, mas nem toda combinação de filtro e período. `/e/{empresa}/sair-das-dividas` responde 404 porque o módulo é só da PF.
- **Testes:** 45 scripts PHP de `tests/` terminaram sem erro, incluindo `core-002-mail-queue`, que antes falhava.
- **Não executados:** `app-005-routes`, `auth-002-http`, `cms-100-e2e`, `core-007-environment-router`, `core-support-helpers` e `email-confirmation-http-v1` fazem requisições HTTP e podem cair no banco real. `core-001-email-smtp` envia e-mail de verdade. Os testes `.js` também não rodaram.
- **Limite do rastreador:** ele só faz GET. Os formulários foram avaliados pela leitura do código e pelos testes de serviço, e o layout não foi revisto nesta rodada.

## Resumo

Nenhuma tela quebra. Os problemas que importam são outros: saldo errado com baixa parcial, contas que não podem ser corrigidas depois de criadas, convite de equipe que nunca é enviado, e sobras de prévia dizendo que algo não funciona quando já funciona.

## App PF

### P0

- [x] **PF-01. Baixa parcial não entra no saldo.** `Receivables::settle` só preenche `paid_at` na quitação, e `Finance::accountsWithBalance` só olha `paid_at`. Quem recebe R$ 400 de R$ 1.000 não vê o dinheiro na conta, e se a baixa foi em outra conta, o valor cai na conta errada ao quitar. Contrato C26.
- [ ] **PF-02. Baixa parcial some de Movimentações e do realizado.** Mesma causa em `Finance::movements`, `summary` e `monthlySeries`. "A receber" soma o valor inteiro, mesmo com parte recebida.
- [ ] **PF-03. Conta não pode ser editada nem arquivada.** Só existe `createAccount`. Um nome ou saldo inicial digitado errado não tem conserto, e `archived_at` existe sem uso. Contrato C6.
- [ ] **PF-04. Baixa não pode ser estornada.** A mensagem de `Receivables::cancel` manda estornar antes de cancelar, mas não existe estorno em `Receivables` nem em `ReceivableActions`. Um recebimento com baixa lançada errada fica sem saída.

### P1

- [ ] **PF-05. Categoria não pode ser editada.** Só criar e excluir. Falta renomear e trocar a cor. Contrato C10.
- [ ] **PF-06. Tirar as sobras de prévia do menu e do painel.** O cartão "Conexão bancária" ocupa o menu com um botão desativado, "Primeiros passos" promete "Em breve", e o `noscript` diz "nesta prévia".
- [ ] **PF-07. Primeiros passos sem progresso.** A lista não marca o que já foi feito, embora os dados existam (contas, categorias, lançamentos, orçamento).
- [ ] **PF-08. Configurações é uma prévia inteira.** `/p/configuracoes` tem tudo desativado, enquanto sessões e dados da conta já estão em Perfil. Ligar o que existe e tirar o resto.
- [ ] **PF-09. `/p/empresarial` é a prévia antiga dos módulos.** Trocar por "Criar empresa" real ou redirecionar. A ação `company` já existe.
- [ ] **PF-10. Faturas de cartão na PF.** Cartão tem limite, fechamento e vencimento, mas não há fatura, parcelas nem pagamento. Contratos C7 e C25. Item grande, dividir ao começar.
- [ ] **PF-11. Exportar e excluir a conta (LGPD).** Não encontrei fluxo de exportação de dados pessoais nem de exclusão. Excluir usuário com recorrência ainda falha pela FK `finance_recurring_items.account_id`.
- [ ] **PF-12. Trocar o e-mail da conta.** O campo em Perfil está desativado com "Alterações exigem verificação".

- [ ] **PF-18. Categorias soma lançamentos cancelados.** `Finance::categoriesWithTotals` não filtra `status`. Não mudei porque Categorias ficou fora da etapa de 02/11. Fazer junto com PF-05.
- [ ] **PF-19. Despesa paga não volta a ficar em aberto.** Só dá para excluir. Fazer junto com PF-04.
- [ ] **PF-20. Agendar as tarefas de produção.** `finance-recurring-due`, `process-mail-queue`, `process-notifications` e `process-goal-schedules` não têm cron.
- [ ] **PF-21. Backup, restauração e controle de migrations.** Hoje há só dump manual, sem teste de restauração nem registro de migrations aplicadas.
- [ ] **PF-22. Djalma: aplicar a migration 013** com `aplicar-migrations.sh`. Sem ela, Dívidas fica sem estorno e sem aplicar acordo.

### P2

- [ ] **PF-13. Conferir o App em celular real e com cache normal.** Até hoje só houve emulação no Chrome com cache desligado.
- [ ] **PF-14. Filtros e busca em Movimentações.** Hoje há só tipo e período.
- [ ] **PF-15. Importar lançamentos por CSV/OFX.** Depende da conciliação (PJ-08), usar o mesmo importador.
- [ ] **PF-16. Decisão: Assistente Financeiro.** `/p/assistente` está no menu sem funcionar, e `AiProvider` não tem provedor. Decidir entre ligar um provedor ou tirar do menu até lá.
- [ ] **PF-17. Decisão: Open Finance.** Integrações mostra o catálogo sem conexão. Depende de provedor homologado. Contrato C19.

## App PJ

Os itens PJ estão sendo feitos pela missão Empresa, com controle em `docs/empresa/PROGRESSO.md`. A rotina não deve pegar itens desta seção.

### P0

- [x] **PJ-01. Convite de equipe nunca é enviado.** `TeamActions` coloca o e-mail na fila, mas nada chama `MailQueue::work`: não há script nem cron. Quem é convidado nunca recebe. Vale para tudo que usa `Email::queue`.
- [x] **PJ-02. Dados da empresa só leitura.** `/e/{empresa}/empresa` não edita nada, e "Alterar logo" está desativado. Contrato C14.
- [ ] **PJ-03. Validar equipe e permissões no MySQL e no navegador.** O teste `enterprise-002-team` roda em SQLite. Testar membro sem permissão em cada tela e ação.

### P1

- [x] **PJ-04. Contas a pagar à altura de Receber.** Falta fornecedor, baixa parcial e histórico. Sem `contact_id` na despesa, Fornecedores não mostra pagamentos. Contrato C5.
- [x] **PJ-05. Auditoria.** A tela diz "Em breve", mas a tabela `organization_audit_logs` existe. É o módulo pendente mais fácil. Contrato C23.
- [x] **PJ-06. Segurança.** O texto diz que 2FA e sessões não existem, mas já existem em Perfil (`TwoFactorAuth`, `UserSessions`). Ligar a tela ao que há e corrigir o texto. Contrato C22.
- [ ] **PJ-07. Centros de custo.** Tabela, cadastro e vínculo no lançamento. Contrato C9.
- [x] **PJ-08. Conciliação bancária.** Importar OFX/CSV para revisão, nunca conciliar sozinho. Contrato C12.
- [ ] **PJ-09. DRE gerencial.** Contrato C11. Decisão de Djalma: regime de caixa ou competência.
- [x] **PJ-10. Faturas de cartão.** Mesmo trabalho de PF-10.
- [ ] **PJ-11. Obrigações PJ/MEI.** Regime da empresa e calendário, sem cálculo de tributo. Contrato C13.
- [ ] **PJ-12. Ordens de serviço.** Módulo opcional por empresa. Contrato C20.
- [ ] **PJ-13. Voltar ao convite depois do login.** Quem aceita o convite sem sessão perde o caminho.
- [x] **PJ-14. Histórico administrativo de equipe** em `organization_audit_logs`.

### P2

- [x] **PJ-15. Limpar rotas que sobraram.** `/e/{empresa}/empresarial`, `/configuracoes`, `/assistente` e `/contatos` não estão no menu. Redirecionar para a tela certa.
- [x] **PJ-16. Textos desatualizados em `module-pending.php`.** As entradas `recorrencias` e `permissoes` já têm tela real. Apagar.
- [x] **PJ-17. Título divergente.** O menu diz "Dashboard", e a aba do navegador diz "Visão Geral".
- [ ] **PJ-18. Fundo verde-claro da área PJ.** Pedido do perfil PJ, conferir se ainda vale. Defeito 8 do inventário.

## CMS

O rastreador abriu todas as telas sem erro. Faltam verificações mais do que funções.

### P1

- [ ] **CMS-01. Rodar `cms-100-e2e` no ambiente temporário.** O teste usa `CURLOPT_RESOLVE` para `127.0.0.1`, então precisa rodar dentro do container temporário.
- [ ] **CMS-02. Conferir cada formulário de `/acao/{name}` por navegador.** O rastreador só fez GET.
- [ ] **CMS-03. Conferir o layout das telas abertas por ID** que o rastreador não alcançou: `/assinaturas/{id}`, `/cobrancas/{id}`, `/chamados/{id}`, `/paginas/{id}`, `/planos/{id}`, `/destaques/{id}`. Faltam dados de exemplo.
- [ ] **CMS-04. Liberar fora do local.** `REALIZZE_CMS_ENABLED` em produção. Djalma.
- [ ] **CMS-05. Decisão: cobrança real.** `/cobrancas` registra pagamento à mão. Gateway depende de decisão de Djalma, junto com planos e preços.

## Site

### P0

- [x] **WEB-01. `/contato` diz que o formulário é falso.** A página afirma "Nenhuma informação é enviada", enquanto `/suporte/mensagem` já grava em `contact_messages` e o CMS lê. Ligar o formulário a essa rota e tirar o aviso.

### P1

- [ ] **WEB-02. Conferir termos e privacidade com jurídico.** Djalma.
- [ ] **WEB-03. Decisão: planos e preços.** `/planos` mostra "serão divulgados". Sem preço até Djalma definir.
- [ ] **WEB-04. Rever SEO.** `og:image`, `canonical` por página e sitemap com páginas do CMS.

### P2

- [ ] **WEB-05. Atualizar `docs/roadmap.md`,** que está desatualizado e diz que autenticação e cadastro estão pendentes.

## Infraestrutura e lançamento

### P0

- [x] **INF-01. Script do worker da fila de e-mail.** Parte de PJ-01.
- [ ] **INF-02. Agendar os crons em produção:** fila de e-mail, `process-notifications.php`, `finance-recurring-due.php`, `process-goal-schedules.php`. Hoje nenhum está agendado. Djalma.

### P1

- [ ] **INF-03. Numeração repetida de migrations.** Dois `003_` e dois `108_`. Contrato C15.
- [ ] **INF-04. Controle de migrations aplicadas.** Não há tabela de versões, e `aplicar-migrations.sh` testa caso a caso e está fora do Git. Versionar ou trocar por um executor.
- [ ] **INF-05. Faltam no `.env` de produção:** `REALIZZE_APP_ENABLED=1`, `REALIZZE_CMS_ENABLED=1`. Djalma.
- [ ] **INF-06. Remover o usuário `pj-audit@example.invalid` do `realizze_dev`.** Era temporário, e as empresas Auditoria Alfa/Beta continuam lá. Djalma ou com a permissão dele.
- [ ] **INF-07. Testes que não rodam em ambiente isolado.** Fazer `app-005`, `auth-002` e os outros HTTP aceitarem host e porta, para a suíte inteira rodar fora do banco real.
- [ ] **INF-08. Tirar `source/Services/EmailVerification.php.bak` da pasta.** Está fora do Git.

### P2

- [ ] **INF-09. Decisão: logos de bancos.** `REALIZZE_BANK_LOGOS_ENABLED` está desligado, porque o `manifest.json` pede revisão de licença. Djalma.
- [ ] **INF-10. Logos de bandeiras e assinaturas não existem no projeto.** Seguem com fallback até haver arquivos licenciados.
- [ ] **INF-11. Revisão de segurança antes de lançar,** com foco em isolamento entre empresas, uploads e sessões.

## O que depende de Djalma

| Item | O que precisa |
|---|---|
| WEB-03, CMS-05 | Definir planos, preços e gateway |
| PF-16, PF-17 | Decidir sobre assistente e Open Finance |
| PJ-09 | Regime da DRE: caixa ou competência |
| INF-02, INF-05, CMS-04 | Configurar produção |
| INF-06, INF-09, WEB-02 | Limpar usuário de teste, licença de logos, revisão jurídica |
| Migrations novas | Rodar `aplicar-migrations.sh` quando um item criar uma |
