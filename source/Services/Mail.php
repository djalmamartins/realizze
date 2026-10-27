<?php
declare(strict_types=1);

namespace Moves\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class Mail
{
    public function send(
        string $to,
        string $subject,
        string $html,
        string $plainText = ''
    ): void {
        $required = [
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
        ];

        foreach ($required as $key) {
            if (getenv($key) === false || getenv($key) === '') {
                throw new RuntimeException(
                    "Configuração de e-mail ausente: {$key}"
                );
            }
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário inválido.');
        }

        $mailer = new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->Host = (string) getenv('MAIL_HOST');
        $mailer->Port = (int) getenv('MAIL_PORT');
        $mailer->SMTPAuth = true;
        $mailer->Username = (string) getenv('MAIL_USERNAME');
        $mailer->Password = (string) getenv('MAIL_PASSWORD');
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Timeout = 15;

        $mailer->setFrom(
            (string) getenv('MAIL_FROM_ADDRESS'),
            (string) getenv('MAIL_FROM_NAME')
        );

        $mailer->addAddress($to);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = $plainText !== ''
            ? $plainText
            : strip_tags($html);

        try {
            $mailer->send();
        } catch (\PHPMailer\PHPMailer\Exception $exception) {
            throw new RuntimeException(
                'Não foi possível enviar o e-mail.',
                0,
                $exception
            );
        }
    }
}
