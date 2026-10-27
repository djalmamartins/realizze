<?php
declare(strict_types=1);

namespace Moves\Services;

final class PasswordResetMailer
{
    public function __construct(private readonly Mail $mail = new Mail())
    {
    }

    public function send(string $email, string $token): void
    {
        $base = getenv('APP_URL');
        if (!is_string($base) || !filter_var($base, FILTER_VALIDATE_URL) ||
            !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new \RuntimeException('Configuração de recuperação inválida.');
        }
        $url = rtrim($base, '/') . '/redefinir-senha?token=' . rawurlencode($token);
        $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->mail->send(
            $email,
            'Redefinição de senha | Realizze',
            '<p>Recebemos uma solicitação para redefinir sua senha.</p><p><a href="' . $safe . '">Redefinir senha</a></p><p>O link expira em 30 minutos. Se não solicitou, ignore este e-mail.</p>',
            "Recebemos uma solicitação para redefinir sua senha.\n\nAcesse: {$url}\n\nO link expira em 30 minutos. Se não solicitou, ignore este e-mail."
        );
    }
}
