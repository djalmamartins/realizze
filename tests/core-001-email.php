<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Email;

function checkEmail(bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHOU - {$name}\n");
        exit(1);
    }
    echo "OK - {$name}\n";
}

$email = new Email();
$reflection = new ReflectionClass($email);
checkEmail($reflection->getName() === Email::class, 'Support Email disponível');

try {
    $email->bootstrap('Assunto', 'Corpo', 'email-invalido');
    checkEmail(false, 'Destinatário inválido rejeitado');
} catch (InvalidArgumentException) {
    checkEmail(true, 'Destinatário inválido rejeitado');
}

$email->bootstrap('Teste', '<b>Olá</b>', 'teste@example.com', 'Teste')->text('Olá');
checkEmail(true, 'Mensagem preparada com texto alternativo');

try {
    $email->attach('/arquivo/inexistente.pdf');
    checkEmail(false, 'Anexo inexistente rejeitado');
} catch (InvalidArgumentException) {
    checkEmail(true, 'Anexo inexistente rejeitado');
}

$path = tempnam(sys_get_temp_dir(), 'mail-test-');
if ($path === false) {
    throw new RuntimeException('Não foi possível criar anexo temporário.');
}
try {
    file_put_contents($path, 'anexo de teste');
    $email->attach($path, 'teste.txt');
    checkEmail(true, 'Anexo legível aceito');
} finally {
    unlink($path);
}

echo "TESTE CORE-001: APROVADO (sem envio SMTP)\n";
