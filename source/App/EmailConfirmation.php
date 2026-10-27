<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\EmailVerification;
use Throwable;

final class EmailConfirmation
{
    public function confirm(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
            return false;
        }

        $token = $_POST['token'] ?? null;

        if (!is_string($token) ||
            preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return false;
        }

        try {
            return (new EmailVerification())->confirm($token);
        } catch (Throwable $exception) {
            error_log('Realizze: falha interna na confirmação de e-mail.');
            return false;
        }
    }
}
