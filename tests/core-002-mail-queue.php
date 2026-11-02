<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\MailQueue;
use Moves\Support\Email;

function ok(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU - ' . $label);
    }
    echo 'OK - ', $label, PHP_EOL;
}

$pdo->beginTransaction();
try {
    $queue = new MailQueue($pdo);
    $id = $queue->enqueue('core002@example.invalid', 'Teste fila', '<p>Teste</p>', 'Teste');
    ok($id > 0, 'Enfileiramento retorna identificador');

    $stmt = $pdo->prepare('SELECT status, attempts, recipient_email FROM mail_queue WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    ok($row !== false && $row['status'] === 'pending' && (int) $row['attempts'] === 0,
        'Mensagem criada pendente e sem tentativas');

    $id2 = (new Email())->bootstrap('Teste', '<p>Mensagem</p>', 'core002@example.invalid')->queue($pdo);
    ok($id2 > $id, 'Support Email utiliza a fila');

    try {
        $queue->enqueue('inválido', 'Teste', '<p>Teste</p>');
        ok(false, 'Endereço inválido rejeitado');
    } catch (InvalidArgumentException) {
        ok(true, 'Endereço inválido rejeitado');
    }
    echo "TESTE CORE-002: APROVADO (sem envio SMTP)\n";
} finally {
    $pdo->rollBack();
    echo "Transação revertida; nenhum e-mail ficou na fila.\n";
}
