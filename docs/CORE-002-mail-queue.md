# CORE-002 — Fila de e-mails

## Instalação (manual)
1. Fazer backup do banco Realizze.
2. Revisar e executar `database/migrations/20261027_core_002_mail_queue.sql` no banco correto via phpMyAdmin ou cliente MySQL.
3. Rodar `php tests/core-002-mail-queue.php` dentro do container. O teste usa transação e desfaz as inserções; não envia SMTP.
4. Somente depois, executar o worker explicitamente: `php bin/mail-queue-worker.php 10`. Ele **envia** mensagens pendentes reais.
5. Em produção, agendar o worker via cron/supervisor, com credenciais mínimas e logs.

## API
```php
$id = (new \Moves\Support\Email())
    ->bootstrap('Assunto', '<p>Conteúdo</p>', 'destinatario@example.com')
    ->text('Conteúdo')
    ->queue($pdo);
```

O envio síncrono `->send()` continua disponível para mensagens transacionais.

## Garantias e limitações
- MySQL 8, InnoDB, `FOR UPDATE SKIP LOCKED` e horário UTC no banco.
- Reserva por 120 segundos; SMTP com timeout de 15 segundos; tentativas exponenciais até 5 por padrão.
- **Pelo menos uma entrega**: se o SMTP aceitar o e-mail e o processo cair antes de gravar `sent`, poderá ocorrer duplicação. Não há garantia de entrega exatamente uma vez.
- Não inclui anexos na fila: arquivos temporários não são duráveis. `queue()` rejeita anexos; envio imediato com anexos permanece disponível. Suporte a anexos privados requer armazenamento persistente, permissões e limpeza.
- Worker não deve ser exposto via HTTP. Não há execução automática ao instalar.
- Em caso de falha, o erro detalhado não é persistido na tabela para evitar exposição de credenciais ou conteúdo.
- Futuro: isolamento por tenant/produto, idempotência de negócio, métricas e painel de operação.
