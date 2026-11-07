# Realizze 2.0 — checklist de produção da V1

Nenhum item deste documento autoriza alteração em produção. Marcar somente com evidência anexada à release.

## 1. Código e release

- [ ] Escopo da V1 congelado; itens V1.1 ocultos ou claramente identificados.
- [ ] 28 commits exclusivos da `dev-claude` revisados e integrados/descartados conscientemente.
- [ ] `dev-codex` confirmado contido na candidata.
- [ ] Revisão candidata tem hash/tag imutável e árvore limpa.
- [ ] Dependências instaladas pelo lock, sem pacote vulnerável crítico conhecido.
- [ ] Assets App/Web/CMS publicados pelo processo MovesCode e verificados.
- [ ] Changelog e rollback apontam para hashes/artefatos exatos.

## 2. Banco

- [ ] Inventário de migrations aplicado/pendente/parcial gerado por ambiente.
- [ ] Prefixos repetidos `003` e `108` tratados pelo controle por arquivo/checksum.
- [ ] Backup completo criado e restauração testada em banco novo.
- [ ] Migrations 014–020 e eventuais 021/022 validadas em clone recente.
- [ ] Índices, FKs, valores em centavos e idempotência conferidos.
- [ ] Tempo de migration medido com volume representativo.
- [ ] Rollback lógico/documentado; nenhuma migration destrutiva sem plano.
- [ ] Produção não usa credenciais nem nome de banco local.

## 3. Testes

- [ ] Runner em base descartável executa toda a suíte e falha quando um teste é recusado.
- [ ] Cadastro, confirmação, login, recuperação, 2FA e logout aprovados.
- [ ] PF e PJ: contas, lançamentos, baixas parciais/totais, estorno e transferências aprovados.
- [ ] Compra, parcelamento, fatura, pagamento parcial/total e estorno aprovados.
- [ ] Relatórios conferidos lançamento a lançamento; CSV protegido contra fórmula.
- [ ] Dois usuários e duas empresas comprovam isolamento e IDOR.
- [ ] Papéis comprovam acesso permitido e 403 negado no backend.
- [ ] Uploads privados (logo/anexos) recusam acesso cruzado e MIME inválido.
- [ ] Rotas/menu/AJAX/refresh/histórico aprovados.
- [ ] Android, iPhone, 1440, 1280, 768, 390 e 360 validados.
- [ ] Teclado, foco, contraste, leitores de tela e console sem bloqueador.
- [ ] Baseline de performance aprovado com volume representativo.

## 4. Segurança e privacidade

- [ ] `APP_ENV=production`, debug desativado e erros internos ocultos.
- [ ] Cookies `Secure`, `HttpOnly`, `SameSite` e expiração de sessão revisados.
- [ ] CSRF em todas as mutações; validação/escape no servidor.
- [ ] Rate limit compartilhado entre instâncias ou implantação comprovadamente única.
- [ ] 2FA, recuperação e troca de e-mail têm expiração, uso único e auditoria.
- [ ] Exportação/exclusão LGPD testadas e política de retenção definida.
- [ ] Segredos fora do Git, rotacionados e com menor privilégio.
- [ ] Varredura de dependências e teste de segurança/pentest executados.
- [ ] Logs não contêm senha, token, cartão ou dados financeiros desnecessários.
- [ ] Termos e privacidade aprovados; aceite versionado.

## 5. E-mail e tarefas

- [ ] SMTP de produção validado; SPF, DKIM e DMARC conferidos.
- [ ] `process-mail-queue.php` executa, reprocessa e alerta falhas.
- [ ] `process-notifications.php` agendado e observado.
- [ ] `finance-recurring-due.php` e `process-goal-schedules.php` idempotentes e agendados.
- [ ] Jobs têm lock, timeout, logs, métricas e política de retry.
- [ ] Relógio/timezone `America/Sao_Paulo` e UTC no servidor revisados.

## 6. Infraestrutura

- [ ] Domínio e HTTPS públicos válidos, renovação automática testada.
- [ ] PHP 8.2+ e extensões compatíveis com `composer.lock`.
- [ ] Banco com backups diários criptografados, retenção e restore mensal.
- [ ] Storage privado persistente, backup e permissões mínimas.
- [ ] Health checks de web, banco, fila e storage.
- [ ] Métricas/alertas de erro 5xx, latência, CPU, disco, banco, fila e cron.
- [ ] Centralização/retenção de logs e correlação de requisição.
- [ ] Capacidade, limites e plano de crescimento definidos.
- [ ] Acesso administrativo restrito, auditado e com MFA quando disponível.

## 7. Deploy, rollback e go-live

- [ ] Deploy ensaiado em homologação idêntica à produção.
- [ ] Janela, responsáveis, canal de incidente e critérios de abortar definidos.
- [ ] Backup verificado imediatamente antes da mudança de schema.
- [ ] Assets e código publicados atomicamente ou em ordem compatível.
- [ ] Smoke pós-deploy: login, PF, PJ, baixa/estorno, relatório, e-mail e jobs.
- [ ] Rollback de código e estratégia para schema executados em ensaio.
- [ ] Suporte e comunicação para beta preparados.
- [ ] Go/no-go assinado por produto, engenharia, infra e jurídico.
- [ ] Monitoramento intensivo nas primeiras 24–72 horas.

## Resultado desta auditoria

- [x] Apache local serve a worktree correta.
- [x] Commits `556cab6`, `510d526` e `9c3700e` estão na `main`.
- [x] Testes estruturais de UI/assets e 38 asserções financeiras passaram.
- [ ] A revisão candidata contém todo o código aprovado.
- [ ] O schema servido é compatível com todos os módulos da V1.
- [ ] Homologação completa, reproduzível e limpa foi aprovada.
- [ ] Infraestrutura pública foi comprovada.

**Estado: NO-GO para produção; preparar beta somente após fechar todos os P0 técnicos.**
