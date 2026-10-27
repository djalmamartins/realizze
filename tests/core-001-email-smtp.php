<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Email;

$recipient = getenv('TEST_MAIL_TO');
if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Defina TEST_MAIL_TO com um destinatário válido. Nenhum e-mail foi enviado.\n");
    exit(2);
}

$file = tempnam(sys_get_temp_dir(), 'realizze-mail-');
if ($file === false) {
    throw new RuntimeException('Falha ao criar anexo temporário.');
}

try {
    if (file_put_contents($file, "Teste de anexo do Realizze 2.0.\n") === false) {
        throw new RuntimeException('Falha ao gravar anexo temporário.');
    }
    (new Email())
        ->bootstrap(
            'Realizze 2.0 | Teste CORE-001',
            '<p>Este é um teste controlado do Support/Email com anexo.</p>',
            $recipient,
            'Destinatário de teste'
        )
        ->text('Este é um teste controlado do Support/Email com anexo.')
        ->attach($file, 'realizze-core-001.txt')
        ->send();

    echo "SMTP aceitou o envio com anexo. Confira o recebimento na caixa de entrada.\n";
} finally {
    @unlink($file);
}
