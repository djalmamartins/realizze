<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

final class UserSessions
{
    private static ?bool $supported = null;

    public static function supported(): bool
    {
        if (self::$supported !== null) return self::$supported;
        try { Connection::get()->query('SELECT 1 FROM user_sessions LIMIT 1'); return self::$supported = true; }
        catch (\Throwable) { return self::$supported = false; }
    }

    public static function create(int $userId): void
    {
        if (!self::supported()) return;
        $token = bin2hex(random_bytes(32));
        $statement = Connection::get()->prepare(
            'INSERT INTO user_sessions (user_id,token_hash,php_session_hash,ip_address,user_agent,device_name) VALUES (?,?,?,?,?,?)'
        );
        $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $statement->execute([
            $userId,
            hash('sha256', $token),
            hash('sha256', session_id()),
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            $agent ?: null,
            self::deviceLabel($agent),
        ]);
        $_SESSION['app_session_token'] = $token;
        $_SESSION['app_session_id'] = (int) Connection::get()->lastInsertId();
        UserActivity::record($userId, 'auth.login');
    }

    public static function validate(int $userId): bool
    {
        if (!self::supported()) return true;
        $token = $_SESSION['app_session_token'] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            // Sessões anteriores à migration são registradas sem derrubar o usuário.
            self::create($userId);
            return true;
        }
        $statement = Connection::get()->prepare('SELECT id FROM user_sessions WHERE user_id=? AND token_hash=? AND revoked_at IS NULL LIMIT 1');
        $statement->execute([$userId, hash('sha256', $token)]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            return false;
        }
        $_SESSION['app_session_id'] = (int) $id;
        Connection::get()->prepare('UPDATE user_sessions SET last_activity_at=NOW() WHERE id=? AND last_activity_at < NOW() - INTERVAL 1 MINUTE')->execute([$id]);
        return true;
    }

    public static function listing(int $userId): array
    {
        if (!self::supported()) return [];
        $statement = Connection::get()->prepare('SELECT id,ip_address,user_agent,device_name,trusted_at,revoked_at,last_activity_at,created_at FROM user_sessions WHERE user_id=? ORDER BY revoked_at IS NULL DESC,last_activity_at DESC');
        $statement->execute([$userId]);
        $current = (int) ($_SESSION['app_session_id'] ?? 0);
        return array_map(static function (array $row) use ($current): array {
            $row['current'] = (int) $row['id'] === $current;
            $row['ip_masked'] = self::maskIp((string) ($row['ip_address'] ?? ''));
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function revoke(int $userId, int $sessionId): bool
    {
        if (!self::supported()) return false;
        if ($sessionId <= 0 || $sessionId === (int) ($_SESSION['app_session_id'] ?? 0)) return false;
        $statement = Connection::get()->prepare("UPDATE user_sessions SET revoked_at=NOW(),revoke_reason='user' WHERE id=? AND user_id=? AND revoked_at IS NULL");
        $statement->execute([$sessionId, $userId]);
        if ($statement->rowCount() > 0) UserActivity::record($userId, 'session.revoked', 'success', ['session_id' => $sessionId]);
        return $statement->rowCount() > 0;
    }

    public static function revokeOthers(int $userId): int
    {
        if (!self::supported()) return 0;
        $current = (int) ($_SESSION['app_session_id'] ?? 0);
        $statement = Connection::get()->prepare("UPDATE user_sessions SET revoked_at=NOW(),revoke_reason='user_all' WHERE user_id=? AND id<>? AND revoked_at IS NULL");
        $statement->execute([$userId, $current]);
        UserActivity::record($userId, 'session.others_revoked', 'success', ['session_id' => $current]);
        return $statement->rowCount();
    }

    public static function revokeCurrent(int $userId, string $reason = 'logout'): void
    {
        if (!self::supported()) return;
        $id = (int) ($_SESSION['app_session_id'] ?? 0);
        if ($id > 0) Connection::get()->prepare('UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,NOW()),revoke_reason=? WHERE id=? AND user_id=?')->execute([$reason, $id, $userId]);
        UserActivity::record($userId, 'auth.logout');
    }

    private static function deviceLabel(string $agent): string
    {
        $os = str_contains($agent, 'Mac OS') ? 'Mac' : (str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'iPhone') ? 'iPhone' : 'Dispositivo')));
        $browser = str_contains($agent, 'Edg/') ? 'Microsoft Edge' : (str_contains($agent, 'Chrome/') ? 'Google Chrome' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Navegador')));
        return $os . ' — ' . $browser;
    }

    private static function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return (string) preg_replace('/\.\d+\z/', '.•', $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return implode(':', array_slice(explode(':', $ip), 0, 3)) . ':…';
        return 'Não identificado';
    }
}
