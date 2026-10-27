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

        $attempts = $_SESSION['login_attempts'] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }
        $attempts = array_values(array_filter(
            $attempts,
            static fn ($at): bool => is_int($at) && $at > time() - 900
        ));
        if (count($attempts) >= 10) {
            $_SESSION['login_attempts'] = $attempts;
            return false;
        }

        try {
            $user = (new Authentication())->attempt($email, $password);
        } catch (Throwable $exception) {
            error_log('Realizze: erro interno na autenticação.');
            return false;
        }

        if ($user === null) {
            $attempts[] = time();
            $_SESSION['login_attempts'] = $attempts;
            return false;
        }

        SessionAuth::signIn($user['id'], $user['auth_version']);
        return true;
    }
}
