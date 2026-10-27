<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\Authentication;
use Moves\Services\Csrf;
use Moves\Services\SessionAuth;
use Throwable;

final class Login
{
    public function submit(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
            !Csrf::validate($_POST['csrf_token'] ?? null)) {
            return false;
        }

        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        if (!is_string($email) || !is_string($password) ||
            strlen($email) > 254 || strlen($password) > 4096) {
            return false;
        }

        // Limite por IP e por conta, independente da sessão/cookie.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $limiter = new \Moves\Services\AuthRateLimiter();
        try {
            $ipAllowed = $limiter->hit('login-ip', $ip, 30, 900);
            $accountAllowed = $limiter->hit('login-account', strtolower(trim($email)), 10, 900);
            if (!$ipAllowed || !$accountAllowed) {
                error_log('Realizze auth: login rate limited.');
                return false;
            }
        } catch (Throwable $exception) {
            error_log('Realizze auth: rate limiter unavailable.');
            return false;
        }

        try {
            $user = (new Authentication())->attempt($email, $password);
        } catch (Throwable $exception) {
            error_log('Realizze: erro interno na autenticação.');
            return false;
        }

        if ($user === null) {
            error_log('Realizze auth: login rejected.');
            return false;
        }

        SessionAuth::signIn($user['id'], $user['auth_version']);
        return true;
    }
}
