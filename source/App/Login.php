<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\Authentication;
use Moves\Services\Csrf;
use Moves\Services\SessionAuth;
use Moves\Services\TwoFactorAuth;
use Moves\Services\UserActivity;
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

        if (TwoFactorAuth::status($user['id'])['enabled']) {
            session_regenerate_id(true);
            $_SESSION = [
                'auth_2fa_user_id' => $user['id'],
                'auth_2fa_version' => $user['auth_version'],
                'auth_2fa_at' => time(),
            ];
            return true;
        }
        SessionAuth::signIn($user['id'], $user['auth_version']);
        return true;
    }

    public function submitSecondFactor(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate($_POST['csrf_token'] ?? null)) return false;
        $userId = $_SESSION['auth_2fa_user_id'] ?? null;
        $version = $_SESSION['auth_2fa_version'] ?? null;
        $started = $_SESSION['auth_2fa_at'] ?? 0;
        $code = $_POST['code'] ?? null;
        if (!is_int($userId) || !is_int($version) || !is_int($started) || time() - $started > 600 || !is_string($code)) return false;
        $limiter = new \Moves\Services\AuthRateLimiter();
        if (!$limiter->hit('login-2fa', (string) $userId, 8, 900) || !TwoFactorAuth::verify($userId, $code)) {
            UserActivity::record($userId, 'auth.2fa', 'failure');
            return false;
        }
        SessionAuth::signIn($userId, $version);
        UserActivity::record($userId, 'auth.2fa');
        return true;
    }

    public static function pendingSecondFactor(): bool
    {
        return is_int($_SESSION['auth_2fa_user_id'] ?? null) && is_int($_SESSION['auth_2fa_at'] ?? null) && time() - $_SESSION['auth_2fa_at'] <= 600;
    }
}
