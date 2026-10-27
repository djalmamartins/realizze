<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Message;

function check(bool $value, string $label): void
{
    if (!$value) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    echo 'OK - ', $label, PHP_EOL;
}

$message = (new Message())->success('<script>alert("x")</script>');
check($message->getType() === 'success', 'Tipo de sucesso');
check(!str_contains($message->render(), '<script>'), 'HTML perigoso escapado');
check(str_contains($message->render(), '&lt;script&gt;'), 'Texto preservado de forma segura');
check((string) $message === $message->render(), 'Conversão para string');
check(str_contains((new Message())->warning('Atenção')->before('<b>')->after('</b>')->render(), '&lt;b&gt;'),
    'Prefixos e sufixos escapados');
foreach (['info', 'success', 'warning', 'error'] as $type) {
    check((new Message())->{$type}('Teste')->getType() === $type, 'Tipo ' . $type);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar sessão de teste.');
    }
}
(new Message())->error('Falha de teste')->flash();
$flash = Message::consumeFlash();
check($flash !== null && $flash->getType() === 'error' && $flash->getText() === 'Falha de teste',
    'Flash recuperado');
check(Message::consumeFlash() === null, 'Flash consumido uma única vez');
echo "TESTE CORE-003: APROVADO\n";
