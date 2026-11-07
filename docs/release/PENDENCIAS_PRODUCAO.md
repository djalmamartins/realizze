# Pendências para produção

Atualizado em 07/11/2026. P0 impede produção ou é risco crítico; P1 é essencial para a v1.0; P2 pode ficar para depois. "Quem" indica quem precisa agir.

## P0: antes de qualquer usuário real

| Pendência | Quem | Esforço |
|---|---|---|
| Aplicar as migrations 014 a 020 no `realizze_dev` com `aplicar-migrations.sh` (faz backup antes; estendido até a 020). Sem elas: Faturas, Conciliação, Centros de custo, pagamentos de fatura, papéis da equipe, logotipo, Obrigações, Ordens de serviço e DRE por grupo não funcionam. Repetir no banco de produção. | Djalma | 1 h |
| Integrar a `dev-claude` (29 commits locais acima da `main`) na `main` e enviar ao GitHub. `app.realizze.lab` serve a `main`. | Djalma | 1 h + revisão |
| Agendar os crons: `process-mail-queue.php`, `process-notifications.php`, `finance-recurring-due.php`, `process-goal-schedules.php`. Hoje nenhum roda: e-mails de confirmação e recuperação de senha ficam na fila. | Djalma / infra | 0,5 dia |
| `.env` de produção: `APP_ENV=production`, `REALIZZE_APP_ENABLED=1`, `REALIZZE_CMS_ENABLED=1`, SMTP real, `REALIZZE_CMS_PREVIEW_ALLOWED_IPS`. | Djalma | 0,5 dia |
| Backup diário do banco com teste de restauração e controle de migrations aplicadas (hoje o script testa caso a caso e está fora do Git). | Djalma + Claude | 1 dia |
| Termos de uso e privacidade revisados pelo jurídico (o cadastro já registra aceite em `user_legal_acceptances`). | Djalma / jurídico | externo |

## P1: essencial para a v1.0

| Pendência | Esforço |
|---|---|
| LGPD: exportar os próprios dados e excluir a conta (não há fluxo; excluir usuário com recorrência falha pela FK RESTRICT). | 2 dias |
| Teste em celular real e com cache normal (até agora só emulação). | 0,5 dia (Djalma) |
| Campo de enquadramento da empresa (MEI, Simples, outro), só informativo, sem regra fiscal. | 0,5 dia |
| Decidir as prévias `/p/assistente` e `/p/empresarial` (fora do menu): remover ou redirecionar. | 0,5 dia após decisão |
| Remover do `realizze_dev` o usuário temporário `pj-audit@example.invalid` e as empresas Auditoria Alfa/Beta. | 0,5 h |
| Worktree `realizze-homolog`: decidir o destino das alterações não commitadas. | Djalma |
| Fluxos HTTP `enterprise-company-flow` e `enterprise-invite-login-flow` dependem do estado do banco (falham na repetição). Ajustar para preparar o próprio estado. | 0,5 dia |
| Troca de e-mail da conta (campo desativado em Meu perfil). | 1 dia |
| Numeração repetida de migrations (dois `003_` e dois `108_`). | 0,5 dia |

## P2: depois da v1.0

- Conciliação com paginação (hoje até 500 linhas).
- Cartão "Conexão bancária" na sidebar com botão desativado; Open Finance e importação CSV/OFX na PF.
- Alerta de limite do MEI, lembretes de DAS e DASN-SIMEI e NFS-e nacional (dependem de contador; ver `CONFORMIDADE.md`).
- Cobrança real no CMS (gateway), planos e preços.
- Primeiros passos com progresso; filtros extras em Movimentações; SEO.
- Remover `source/Services/EmailVerification.php.bak` (fora do Git).

## Avaliação

1. **Páginas:** 41 no menu (15 PF, 26 PJ), mais login, cadastro, 2FA, convite, suporte (8 telas) e as duas prévias fora do menu.
2. **Totalmente funcionais (validadas por teste de gravação ou de leitura):** 37 das 41. **Com ressalva:** Categorias PF e PJ (funcionam, sem teste de gravação), Integrações PF e PJ (só informativa).
3. **Precisam de correção:** nenhuma página do menu com falha conhecida após esta rodada.
4. **Funcionalidades essenciais que faltam:** LGPD (exportar e excluir conta), enquadramento da empresa, troca de e-mail.
5. **Erros que impedem o lançamento:** nenhum erro de código conhecido. Os impedimentos são de ambiente: migrations, crons, `.env`, backup e termos.
6. **Migrations pendentes no `realizze_dev`:** 014, 015, 016, 017, 018, 019 e 020.
7. **Segurança:** CSRF, sessão, 2FA, limitador de login, permissões por papel (rota, menu e ação) e isolamento entre empresas estão cobertos por testes que passam. Falta: o limitador de login grava em arquivo no `/tmp` (não funciona com mais de um servidor) e não houve teste de intrusão externo.
8. **Múltiplas empresas com isolamento:** sim, por contexto financeiro; testado com o mesmo dono em duas empresas.
9. **Layout PF/PJ:** mesmo menu, mesmo cabeçalho e o mesmo padrão de filtros em Receber, Pagar, Obrigações, Ordens de serviço, Clientes, Fornecedores e Conciliação. A sidebar escura da PJ é identidade definida por Djalma.
10. **Para a beta:** os itens P0 técnicos (migrations, integração, crons, `.env`, backup). Estimativa: **3 a 5 dias úteis**, a maior parte dependendo de Djalma e do ambiente.
11. **Para a v1.0:** P0 completo, incluindo os termos, e os P1. Estimativa: **mais 2 a 3 semanas** depois da beta, contando a correção do que aparecer com usuários reais.
12. **Prazo total realista:** cerca de **3 a 4 semanas** até a v1.0, se as decisões de Djalma (prévias, termos, enquadramento) saírem na primeira semana. NFS-e e regras do MEI ficam fora da v1.0.
