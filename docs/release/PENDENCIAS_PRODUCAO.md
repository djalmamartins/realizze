# Pendências para produção

Atualizado em 07/11/2026. P0 impede produção ou é risco crítico; P1 é essencial para a v1.0; P2 pode ficar para depois. "Quem" indica quem precisa agir.

## P0: antes de qualquer usuário real

| Pendência | Quem | Esforço |
|---|---|---|
| Aplicar as migrations 014 a 022 no `realizze_dev` com `aplicar-migrations.sh` (faz backup antes; estendido até a 022; 021 = enquadramento, 022 = troca de e-mail). Sem elas: Faturas, Conciliação, Centros de custo, pagamentos de fatura, papéis da equipe, logotipo, Obrigações, Ordens de serviço e DRE por grupo não funcionam. Repetir no banco de produção. | Djalma | 1 h |
| Integrar a `dev-claude` (commits locais acima da `main`; ver `git log main..dev-claude`) na `main` e enviar ao GitHub. `app.realizze.lab` serve a `main`. | Djalma | 1 h + revisão |
| Agendar os crons: `process-mail-queue.php`, `process-notifications.php`, `finance-recurring-due.php`, `process-goal-schedules.php`. Hoje nenhum roda: e-mails de confirmação e recuperação de senha ficam na fila. | Djalma / infra | 0,5 dia |
| `.env` de produção: `APP_ENV=production`, `REALIZZE_APP_ENABLED=1`, `REALIZZE_CMS_ENABLED=1`, SMTP real, `REALIZZE_CMS_PREVIEW_ALLOWED_IPS`. | Djalma | 0,5 dia |
| Backup diário do banco com teste de restauração. Controle de migrations: `scripts/migrations-status.php` (só leitura) mostra o que falta; passo a passo em `PRODUCAO_CHECKLIST.md`. | Djalma | 0,5 dia |
| Termos de uso e privacidade revisados pelo jurídico (o cadastro já registra aceite em `user_legal_acceptances`). | Djalma / jurídico | externo |

## P1: essencial para a v1.0

Feitos em 07/11 (testes passando no banco isolado):
- LGPD: baixar os próprios dados e excluir a conta, na aba Privacidade de Meu perfil (`privacy-lgpd-flow`, 13 OK). A exclusão também resolve a FK RESTRICT de recorrências.
- Enquadramento da empresa, só informativo (migration 021; `enterprise-company-flow`, 21 OK).
- Troca do e-mail de acesso com confirmação no novo endereço (migration 022; `email-change-flow`, 11 OK).
- Fluxos de empresa e convite preparam o próprio estado (passam repetidos).
- Categorias com teste de gravação em PF e PJ (`categories-flow`, 7 OK).

Ainda abertos:

| Pendência | Quem | Esforço |
|---|---|---|
| Teste em celular real e com cache normal (até agora só emulação). | Djalma | 0,5 dia |
| Decidir as prévias `/p/assistente` e `/p/empresarial` (fora do menu): remover ou redirecionar. | Djalma decide; 0,5 dia depois | |
| Remover do `realizze_dev` o usuário temporário `pj-audit@example.invalid` e as empresas Auditoria Alfa/Beta (não alterei o `realizze_dev` sem autorização). | Djalma | 0,5 h |
| Worktree `realizze-homolog`: decidir o destino das alterações não commitadas. | Djalma | |
| Numeração repetida de migrations (dois `003_` e dois `108_`): sinalizada pelo `migrations-status.php`; não renomear arquivos já aplicados. Resolvido como documentação. | | |
| Editar nome e cor de uma categoria (hoje só criar e excluir). Não mexi porque a tela de Categorias foi dada como satisfatória. | Djalma decide | 0,5 dia |

## P2: depois da v1.0

- ~~Conciliação com paginação~~ feito em 07/11 (50 por página, `reconciliation-filters-flow`).
- Cartão "Conexão bancária" na sidebar com botão desativado; Open Finance e importação CSV/OFX na PF.
- Alerta de limite do MEI, lembretes de DAS e DASN-SIMEI e NFS-e nacional (dependem de contador; ver `CONFORMIDADE.md`).
- Cobrança real no CMS (gateway), planos e preços.
- Primeiros passos com progresso; filtros extras em Movimentações; SEO.
- Remover `source/Services/EmailVerification.php.bak` (fora do Git).

## Avaliação

1. **Páginas:** 41 no menu (15 PF, 26 PJ), mais login, cadastro, 2FA, convite, suporte (8 telas) e as duas prévias fora do menu.
2. **Totalmente funcionais (validadas por teste de gravação ou de leitura):** 39 das 41. **Com ressalva:** Integrações PF e PJ (só informativa, sem integração bancária).
3. **Precisam de correção:** nenhuma página do menu com falha conhecida após esta rodada.
4. **Funcionalidades essenciais que faltam:** nenhuma de código entre as listadas como P1; restam decisões e testes de Djalma (prévias, celular real, limpeza do `realizze_dev`).
5. **Erros que impedem o lançamento:** nenhum erro de código conhecido. Os impedimentos são de ambiente: migrations, crons, `.env`, backup e termos.
6. **Migrations pendentes no `realizze_dev`:** 014 a 022.
7. **Segurança:** CSRF, sessão, 2FA, limitador de login, permissões por papel (rota, menu e ação) e isolamento entre empresas estão cobertos por testes que passam. Falta: o limitador de login grava em arquivo no `/tmp` (não funciona com mais de um servidor) e não houve teste de intrusão externo.
8. **Múltiplas empresas com isolamento:** sim, por contexto financeiro; testado com o mesmo dono em duas empresas.
9. **Layout PF/PJ:** mesmo menu, mesmo cabeçalho e o mesmo padrão de filtros em Receber, Pagar, Obrigações, Ordens de serviço, Clientes, Fornecedores e Conciliação. A sidebar escura da PJ é identidade definida por Djalma.
10. **Para a beta:** os itens P0 técnicos (migrations, integração, crons, `.env`, backup). Estimativa: **3 a 5 dias úteis**, a maior parte dependendo de Djalma e do ambiente.
11. **Para a v1.0:** P0 completo, incluindo os termos, e as decisões P1 que restam. Estimativa: **mais 1 a 2 semanas** depois da beta, contando a correção do que aparecer com usuários reais.
12. **Prazo total realista:** cerca de **2 a 3 semanas** até a v1.0, se as decisões de Djalma (prévias, termos, enquadramento) saírem na primeira semana. NFS-e e regras do MEI ficam fora da v1.0.

> A auditoria do Codex de 07/11 (filtros e catálogo de relatórios) está em `docs/release/V1_*.md`; esta lista consolida as duas.
