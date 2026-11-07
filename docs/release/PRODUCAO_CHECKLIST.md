# Checklist de produção

Nada aqui foi ativado. Cada passo é de Djalma (ou de quem cuidar do servidor), na ordem.

## 1. Banco

1. Conferir o que falta, sem alterar nada:
   `php scripts/migrations-status.php --pendentes`
   (lê só o `information_schema`; mostra aplicada, pendente ou parcial por arquivo e marca números repetidos).
2. Backup completo, e restaurar esse backup num banco temporário para provar que ele volta.
3. Aplicar as pendentes. No ambiente local, `aplicar-migrations.sh` cobre da 006 à 022 e faz o backup antes.
4. Rodar o passo 1 de novo: o resumo deve dizer 0 pendente e 0 parcial.

Números repetidos (`003_` e `108_`) não impedem nada: são arquivos diferentes, já aplicados nos dois bancos verificados. Não renomear arquivos já aplicados.

## 2. Ambiente (`.env` de produção)

- `APP_ENV=production`, `APP_URL=https://www.<domínio>`.
- `REALIZZE_APP_ENABLED=1` (sem ele o App responde 503) e `REALIZZE_CMS_ENABLED=1`.
- SMTP real (`MAIL_*`) e remetente validado (SPF/DKIM).
- `REALIZZE_CMS_PREVIEW_ALLOWED_IPS` só se a prévia do CMS for usada.
- Não copiar o `.env` local: ele aponta para `realizze_dev`.

## 3. Tarefas agendadas (crontab do usuário do PHP)

```
* * * * *  cd /caminho/realizze && php scripts/process-mail-queue.php 50
* * * * *  cd /caminho/realizze && php scripts/process-notifications.php
15 2 * * * cd /caminho/realizze && php scripts/finance-recurring-due.php
30 2 * * * cd /caminho/realizze && php scripts/process-goal-schedules.php
```

Sem a primeira linha, e-mails de convite e outros da fila não saem. Conferir no dia seguinte que as recorrências e os aportes programados rodaram (há registro nos lançamentos e no histórico das metas).

## 4. Publicação

1. Integrar a `dev-claude` na `main` e enviar ao GitHub.
2. No servidor: atualizar o código, `php scripts/publish-app-assets.php`, `publish-web-assets.php` e `publish-cms-assets.php`.
3. Conferir: `/entrar` abre, cadastro envia e-mail, login com 2FA, uma receita e uma despesa com baixa e estorno, troca de empresa, exportação CSV.

## 5. Antes de abrir para o público

- Termos e privacidade revisados pelo jurídico.
- Teste em celular real (Android e iPhone), com cache normal.
- Limitador de login guarda tentativas em arquivo no diretório temporário: com mais de um servidor, cada um conta separado. Com um servidor só, funciona.
- Backup diário agendado e um teste de restauração por mês.
