<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Auditoria imutável das ações sensíveis do titular e de convidados PF. */
final class UserActivity
{
    public static function record(int $ownerId, string $event, string $result = 'success', array $context = [], ?int $actorId = null): void
    {
        if ($ownerId <= 0 || preg_match('/\A[a-z0-9_.-]{2,60}\z/', $event) !== 1) {
            return;
        }
        $safe = array_intersect_key($context, array_flip(['field', 'invite_id', 'permission', 'session_id', 'method', 'reason']));
        $sessionId = is_int($_SESSION['app_session_id'] ?? null) ? $_SESSION['app_session_id'] : null;
        $statement = Connection::get()->prepare(
            'INSERT INTO user_activity_logs (owner_user_id,actor_user_id,session_id,event_type,result,context_json,ip_address,user_agent)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $statement->execute([
            $ownerId,
            $actorId ?? $ownerId,
            $sessionId,
            $event,
            in_array($result, ['success', 'failure', 'blocked'], true) ? $result : 'failure',
            $safe === [] ? null : json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
        ]);
    }

    public static function listing(int $ownerId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['l.owner_user_id = ?'];
        $args = [$ownerId];
        if (is_string($filters['type'] ?? null) && preg_match('/\A[a-z0-9_.-]{2,60}\z/', $filters['type'])) {
            $where[] = 'l.event_type = ?';
            $args[] = $filters['type'];
        }
        if (is_string($filters['from'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $filters['from'])) {
            $where[] = 'l.created_at >= ?';
            $args[] = $filters['from'] . ' 00:00:00';
        }
        if (is_string($filters['to'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $filters['to'])) {
            $where[] = 'l.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $args[] = $filters['to'];
        }
        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $where[] = '(l.event_type LIKE ? OR CONCAT_WS(" ",u.first_name,u.last_name) LIKE ?)';
            $args[] = '%' . mb_substr($search, 0, 80) . '%';
            $args[] = '%' . mb_substr($search, 0, 80) . '%';
        }
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $count = Connection::get()->prepare('SELECT COUNT(*) FROM user_activity_logs l LEFT JOIN users u ON u.id=l.actor_user_id WHERE ' . implode(' AND ', $where));
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $query = Connection::get()->prepare(
            'SELECT l.id,l.event_type,l.result,l.context_json,l.ip_address,l.user_agent,l.created_at,
                    CONCAT_WS(" ",u.first_name,u.last_name) actor_name
             FROM user_activity_logs l LEFT JOIN users u ON u.id=l.actor_user_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $query->execute($args);
        return ['rows' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
