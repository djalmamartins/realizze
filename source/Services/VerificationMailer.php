<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

class VerificationMailer
{
    public function __construct(
        private readonly Mail $mail = new Mail(),
        private readonly VerificationEmail $template = new VerificationEmail()
    ) {
    }

    public function send(
        string $email,
        string $firstName,
        string $token
    ): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new InvalidArgumentException(
                'Token de confirmação inválido.'
            );
        }

        $baseUrl = getenv('APP_URL');

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidArgumentException(
                'APP_URL não configurada.'
            );
        }

        $url = rtrim($baseUrl, '/')
            . '/confirmar-email?token='
            . rawurlencode($token);

        $message = $this->template->build($firstName, $url);

        $this->mail->send(
            $email,
            $message['subject'],
            $message['html'],
            $message['text']
        );
    }
}
