<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

final class VerificationEmail
{
    public function build(
        string $firstName,
        string $verificationUrl
    ): array {
        if (!filter_var($verificationUrl, FILTER_VALIDATE_URL)
            || parse_url($verificationUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException(
                'O link de confirmação deve utilizar HTTPS.'
            );
        }

        $name = htmlspecialchars(
            trim($firstName),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $url = htmlspecialchars(
            $verificationUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirme seu e-mail</title>
</head>
<body style="font-family:Arial,sans-serif;color:#242424;line-height:1.6">
    <h2>Bem-vindo ao Realizze!</h2>
    <p>Olá, {$name}!</p>
    <p>Para ativar sua conta, confirme seu endereço de e-mail.</p>
    <p>
        <a href="{$url}"
           style="display:inline-block;background:#079653;color:#ffffff;
                  padding:12px 24px;text-decoration:none;border-radius:6px">
            Confirmar meu e-mail
        </a>
    </p>
    <p>Este link é válido por 24 horas.</p>
    <p>Se você não realizou esse cadastro, ignore esta mensagem.</p>
    <p>Equipe Realizze</p>
</body>
</html>
HTML;

        return [
            'subject' => 'Confirme seu e-mail - Realizze',
            'html' => $html,
            'text' => "Olá, {$firstName}!\n\n"
                . "Confirme seu e-mail acessando:\n"
                . "{$verificationUrl}\n\n"
                . "Este link é válido por 24 horas.\n\n"
                . "Equipe Realizze",
        ];
    }

    public function buildAlreadyRegistered(
        string $firstName,
        string $recoveryUrl
    ): array {
        $name = htmlspecialchars(trim($firstName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = htmlspecialchars($recoveryUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Você já tem uma conta</title>
</head>
<body style="font-family:Arial,sans-serif;color:#242424;line-height:1.6">
    <p>Olá, {$name}!</p>
    <p>Alguém tentou criar uma conta no Realizze com este e-mail, mas ele já está cadastrado. Sua senha não mudou.</p>
    <p>Se foi você e esqueceu a senha, <a href="{$url}">crie uma nova aqui</a>.</p>
    <p>Se não foi você, ignore esta mensagem.</p>
    <p>Equipe Realizze</p>
</body>
</html>
HTML;

        return [
            'subject' => 'Você já tem uma conta no Realizze',
            'html' => $html,
            'text' => "Olá, {$firstName}!\n\n"
                . "Alguém tentou criar uma conta no Realizze com este e-mail, mas ele já está cadastrado. Sua senha não mudou.\n\n"
                . "Se foi você e esqueceu a senha, crie uma nova em:\n{$recoveryUrl}\n\n"
                . "Se não foi você, ignore esta mensagem.\n\n"
                . "Equipe Realizze",
        ];
    }
}
