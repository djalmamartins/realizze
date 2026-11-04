# Realizze PF: relatório de prontidão

02/11/2026

## Conclusão

**A Pessoa Física ainda não está pronta para produção.** Planejamento, Sair das Dívidas e Perfil avançaram e passaram nos testes, mas restam 12 tarefas essenciais, estimadas em 89 a 105 horas (11 a 14 dias úteis). O percentual funcional é de cerca de 64%, pela regra explicada na seção C.

Os três bloqueios mais graves estão fora dos módulos desta etapa: recebimento parcial não aparece em Movimentações, conta não pode ser corrigida depois de criada, e baixa lançada errada não pode ser estornada.

**Pronto no código não é o mesmo que validado em produção.** Tudo foi testado em MySQL e Apache temporários, com cópia do backup de 02/11. O `realizze_dev` não foi alterado, a migration 013 não foi aplicada e nada foi enviado ao GitHub.

## A. Melhorias realizadas

### Planejamento

**Erros corrigidos**

- O gasto somava lançamentos cancelados.
- Era possível criar orçamento em categoria de receita.
- Não havia como editar com o valor preenchido nem excluir um orçamento.

**O que passou a existir**

- Planejado, gasto, a pagar, disponível, percentual usado e valor excedido por categoria.
- Alertas de dentro do limite, perto (a partir de 80%) e acima.
- Cópia dos limites do mês anterior, sem sobrescrever os já definidos.
- Resultado do mês ou do ano, separando realizado de previsto, com recorrências e parcelas de dívidas.
- URLs `/p/planejamento/2026/11` e `/p/planejamento/2026`. A visão anual é só leitura.

As regras de pendentes, pagos, cancelados, recorrentes e transferências estão em `docs/planejamento.md`.

### Sair das Dívidas

**Erros corrigidos**

- Excluir em Pagar a saída de um pagamento deixava a dívida abatida sem a saída correspondente. Agora é bloqueado.
- Duplo envio podia pagar duas vezes.
- Dívida quitada não podia ter os dados corrigidos.
- Dívida sem taxa sumia do plano. Agora entra sem juros, com aviso claro, e nenhuma taxa é estimada.
- Sem diagnóstico, não havia plano, mesmo com as parcelas informadas.

**O que passou a existir**

- Painel inicial: quanto devo, quanto pagar por mês e quando saio das dívidas, além de progresso, atrasos e próximos vencimentos.
- Estorno que preserva o histórico e refaz o saldo da dívida e o da conta.
- Parcela cheia avança o vencimento e reduz as parcelas; pagamento parcial, não.
- Escolha da conta e da categoria no pagamento, ou a opção de pago por fora.
- Simulação de pagamento adicional, mostrando prazo e juros economizados.
- Renegociação: o acordo aceito vale como novos termos, os anteriores ficam guardados, e o acordo rompido restaura esses termos, descontado o que foi pago.

Regras em `docs/sair-das-dividas.md`.

### Perfil do Usuário

**Erros corrigidos**

- A nova senha podia ser igual à atual, porque a verificação nunca funcionava.
- Datas inexistentes, como 31/02, eram aceitas.
- O CPF aparecia inteiro e agora sai mascarado.
- O selo "Ativo" era fixo.
- A aba de preferências gravava opções que nada usava. Foi trocada por um link para as preferências que funcionam, em Notificações.
- Scripts em `public/uploads` podiam ser solicitados. Agora a resposta é 403.

**O que passou a existir**

- Remover a foto, celular formatado, regra de senha visível e histórico de atividade em português.

A troca de e-mail não foi implementada, porque exige verificação do novo endereço. O campo agora diz isso com clareza.

### Relatórios e Categorias

Nenhum arquivo destas telas foi alterado. Encontrei um erro que deixei registrado: **Categorias soma lançamentos cancelados** (PF-18). O filtro de cancelados entrou em funções usadas pelo painel, por Fluxo de caixa e pelas listas, não nas regras dessas duas telas.

## B. Testes

| Grupo | Resultado | Observação |
| --- | --- | --- |
| Scripts PHP de serviço | 49 de 49 passaram | Inclui os novos de Planejamento, Dívidas e integração da PF |
| Testes HTTP | 6 de 6 passaram | Apache temporário; antes não eram executados |
| Testes JavaScript | 3 de 3 passaram | |
| Fluxos reais no navegador | 12 de 12 verificações passaram | Chromium, com login real |
| Abertura das telas da PF | 17 de 17 com status 200 | Sem erro PHP ou JS e sem rolagem lateral em 390 px |

**Validado no navegador:** criar, editar e excluir orçamento, visão anual, dois envios simultâneos do mesmo pagamento, estorno, edição do perfil, erro de celular inválido, recusa de repetir a senha e recusa sem CSRF.

**Validado só em testes de serviço:** cadastro e edição de dívida, pagamento parcial, quitação, simulações, renegociação, isolamento entre usuários, troca de senha bem-sucedida e 2FA.

**Falhas:** o teste de integração encontrou dois erros com lançamentos cancelados, no gasto por categoria e na lista de contas em aberto. Ambos foram corrigidos, e o teste passou depois. Não ficou nenhum teste falhando.

**Não foi possível validar**

- Envio real de e-mail (`core-001-email-smtp`, que não executei de propósito).
- Celular físico: a verificação mobile foi só por emulação.
- Ambiente de produção, carga e restauração de backup.
- Envio de foto pelo navegador: a validação de upload foi coberta só por testes de serviço.

## C. Estado geral da Pessoa Física

**Percentual estimado: cerca de 64%.** É uma convenção, não uma medição exata: 14 módulos, valendo 1 quando concluído e 0,5 quando parcial, ou seja, (4 + 5) ÷ 14.

| Módulo | Situação | Funciona | Pendência principal |
| --- | --- | --- | --- |
| Planejamento | Concluído | CRUD, cálculos, alertas, projeção | Nenhuma essencial |
| Sair das Dívidas | Concluído, depende da migration 013 | Pagamento, estorno, plano, acordo | Aplicar a 013 |
| Metas e reservas | Concluído em serviço | Carteiras, aportes, estorno | Telas só abertas nesta rodada, sem executar os fluxos |
| Relatórios | Concluído | Totais por vencimento, PDF por impressão | Nenhuma essencial |
| Dashboard | Parcial | Indicadores batem no teste de integração | Sobras de prévia, primeiros passos (PF-06, PF-07) |
| Movimentações | Parcial | Totais de pagamentos | Baixa parcial não aparece (PF-02) |
| Contas e Cartões | Parcial | Cadastro, saldo, transferência | Editar e arquivar (PF-03), faturas (PF-10) |
| Contas a Pagar | Parcial | Criar, editar, baixar | Desfazer baixa (PF-19) |
| Contas a Receber | Parcial | Baixa total e parcial | Estornar baixa (PF-04) |
| Assinaturas e recorrências | Parcial | Cadastro, previsão | Geração sem agendamento (PF-20) |
| Categorias | Parcial | Criar, excluir | Editar (PF-05), soma cancelados (PF-18) |
| Perfil do Usuário | Parcial | Dados, senha, 2FA, sessões | Trocar e-mail (PF-12), LGPD (PF-11) |
| Autenticação e segurança | Parcial | CSRF, limite de tentativas, isolamento | Validar em produção |
| Navegação e mobile | Parcial | Sem rolagem lateral em emulação | Celular real (PF-13); Configurações e Empresarial são prévias |

Nenhum módulo do menu principal ficou como Pendente. `/p/assistente` e `/p/integracoes` não entram na conta, porque dependem de decisão ou de um provedor externo. **Não verificado:** e-mail real, celular físico, produção e recuperação de backup.

### Critérios de produção

| Critério | Situação | Base |
| --- | --- | --- |
| Cálculos financeiros | Parcial | Integração correta, exceto baixa parcial (PF-02) e Categorias (PF-18) |
| Persistência | Validada em teste | Serviços e navegador |
| Integridade de saldos | Parcial | Dívidas protegidas; contas e baixas sem correção (PF-03, PF-04, PF-19) |
| Isolamento e autorização | Validado em teste | Testes de isolamento e de acesso |
| Segurança | Parcial | Sem revisão externa nem validação em produção |
| Tratamento de erros | Validado em parte | Mensagens testadas nos fluxos desta etapa |
| Responsividade | Parcial | Só emulação em 390 px |
| Funções essenciais | Pendente | Ver a seção D |
| Testes automatizados | Bom | 58 scripts passando, mas o navegador não está automatizado no CI |
| Backup e recuperação | Pendente | Dump manual, sem teste de restauração |
| Migrations | Parcial | Sem registro das aplicadas; a 013 aguarda autorização |

## D. Trabalho restante

Estimativas minhas, a partir do código lido. Não são compromisso de prazo.

| # | Tarefa | Horas | Por que importa |
| --- | --- | --- | --- |
| 1 | Aplicar e conferir a migration 013 (PF-22) | 0,5 | Liga estorno e renegociação |
| 2 | Baixa parcial em Movimentações e no realizado (PF-02) | 4 | Número errado na tela |
| 3 | Editar e arquivar conta (PF-03) | 6 | Erro de digitação sem conserto |
| 4 | Estornar baixa e reabrir despesa paga (PF-04, PF-19) | 8 | Erro de baixa sem conserto |
| 5 | Editar categoria e ignorar cancelados (PF-05, PF-18) | 3 | Total errado em Categorias |
| 6 | Agendar as rotinas de produção (PF-20) | 4 | Sem isso, e-mails e recorrências não andam |
| 7 | Backup, restauração e registro de migrations (PF-21) | 6 | Recuperação de dados |
| 8 | Tirar prévias e dar progresso aos primeiros passos (PF-06 a PF-09) | 8 | Tela que promete o que não faz |
| 9 | Trocar e-mail com verificação (PF-12) | 6 | Conta presa a um endereço antigo |
| 10 | Exportar e excluir conta, LGPD (PF-11) | 12 | Exigência legal; revisar com orientação jurídica |
| 11 | Faturas de cartão (PF-10) | 24 a 40 | Cartão hoje é só cadastro |
| 12 | Homologação e celular real (PF-13) | 8 | Validação final |
| | **Total** | **89,5 a 105,5** | Cerca de 11 a 14 dias úteis de 8 horas |

**A ordem da tabela é a ordem recomendada.** Tarefas 2 a 5 primeiro, porque afetam a confiança nos números.

**Impedem o lançamento:** tarefas 1 a 7, 10 e 12. As tarefas 8 e 9 são essenciais, mas não bloqueiam tecnicamente. **Faturas (11)** podem sair do primeiro lançamento se Djalma aceitar lançar cartão sem fatura, o que tiraria de 24 a 40 horas.

**Melhorias opcionais:** filtros em Movimentações (PF-14), importação CSV/OFX (PF-15), Assistente Financeiro (PF-16), Open Finance (PF-17), verificação do celular e automação dos testes de navegador no CI.

### Riscos para colocar em produção

- **Código de outras branches:** os merges `feat/core-*` na `dev-claude` usaram a estratégia `ours`, então constam como mesclados, mas o código deles não está aqui. O que está na árvore passou nos testes.
- **Tarefa de 30 minutos:** ela ainda exige a branch antiga e, por isso, não deve avançar. Se for apontada para a `dev-claude`, pode pegar itens deste roteiro ao mesmo tempo que outra sessão.
- **Duplicidade possível:** despesa lançada à mão para uma parcela só é reconhecida depois de vinculada ao pagamento. Até lá, o resultado previsto pode contá-la duas vezes, e a tela avisa disso.
- **Licença de logos:** logos de bancos continuam desligados por padrão, e não há imagens de bandeiras de cartão.

## E. Git

Branch `dev-claude`, à frente de `origin/dev-claude`, **sem push**. `main` e `dev-codex` não foram alteradas. Cinco commits de código, mais o commit deste relatório.

| Commit | Conteúdo |
| --- | --- |
| `ee4dcf2` | Planejamento; inclui o CSS e as rotas compartilhados com os outros módulos |
| `5b6b4b4` | Sair das Dívidas e migration 013 |
| `bf232de` | Perfil |
| `8f5ec46` | Integração, cancelados, proteção de uploads e roteiro |
| Último de código | Espaço entre os cards de Planejamento e Dívidas |

Arquivos modificados:

- **Telas:** `pages/planejamento.php`, `pages/sair-das-dividas.php`, `pages/perfil.php`, `components/debt-fields.php`, `routes.php`, `assets/css/dashboard.css` e `dashboard.min.css`
- **Serviços:** `Finance.php`, `Debts.php`, `UserProfile.php`, `Notifications.php`, `FinanceActions.php`, `DebtActions.php`, `ProfileActions.php`
- **Banco:** `013_extend_finance_debts.sql` e `013_extend_finance_debts.down.sql`
- **Testes:** `finance-015-planning.php`, `debt-003-control.php`, `integration-001-pf.php`, `profile-001-security.php`
- **Documentos e proteção:** `docs/planejamento.md`, `docs/sair-das-dividas.md`, `docs/ROTEIRO-DE-TRABALHO.md`, este relatório, `public/uploads/.htaccess`

Fora do Git continuam `.claude/` e `aplicar-migrations.sh`. Acrescentei a 013 a esse script, mas não o executei.
