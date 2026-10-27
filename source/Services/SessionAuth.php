<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

final class SessionAuth
{
    public static function signIn(int $id, int $authVersion): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Sessão indisponível.');
        }
        session_regenerate_id(true);
        $_SESSION = [
            'auth_user_id' => $id,
            'auth_version' => $authVersion,
            'auth_at' => time(),
        ];
    }

    public static function current(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE ||
            !is_int($_SESSION['auth_user_id'] ?? null) ||
            !is_int($_SESSION['auth_version'] ?? null)) {
            return null;
        }
        $stmt = Connection::get()->prepare(
            'SELECT id, first_name, email, auth_version FROM users WHERE id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['auth_user_id'], 'active']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int) $user['auth_version'] !== $_SESSION['auth_version']) {
            return null;
        }
        return $user;
    }

    public static function signOut(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
