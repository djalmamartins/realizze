<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use Throwable;

/** Registro das ações administrativas de uma empresa em organization_audit_logs. Só acrescenta, nunca altera. */
final class OrganizationAudit
{
    public const EVENTS = [
        'company.updated' => 'Dados da empresa alterados',
        'team.invited' => 'Convite enviado',
        'team.invite_resent' => 'Convite reenviado',
        'team.invite_revoked' => 'Convite cancelado',
        'team.invite_accepted' => 'Convite aceito',
        'team.invite_declined' => 'Convite recusado',
        'team.role_changed' => 'Papel alterado',
        'team.suspended' => 'Acesso suspenso',
        'team.activated' => 'Acesso reativado',
        'team.removed' => 'Acesso revogado',
    ];

    /** Falha ao registrar não desfaz a ação já concluída; fica no log do servidor. */
    public static function record(int $organizationId, ?int $actorId, string $event, string $resourceType, string|int|null $resourceId = null, array $changes = []): void
    {
        if ($organizationId <= 0 || !isset(self::EVENTS[$event])) {
            return;
        }
        try {
            Connection::get()->prepare(
                'INSERT INTO organization_audit_logs (organization_id, actor_user_id, event_type, resource_type, resource_id, changes_json) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $organizationId,
                $actorId,
                $event,
                mb_substr($resourceType, 0, 80),
                $resourceId === null ? null : mb_substr((string) $resourceId, 0, 80),
                $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $exception) {
            error_log('Realizze organization audit: ' . $exception->getMessage());
        }
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public static function listing(int $organizationId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['l.organization_id = ?'];
        $args = [$organizationId];
        if (is_string($filters['tipo'] ?? null) && isset(self::EVENTS[$filters['tipo']])) {
            $where[] = 'l.event_type = ?';
            $args[] = $filters['tipo'];
        }
        $actor = filter_var($filters['pessoa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actor !== false) {
            $where[] = 'l.actor_user_id = ?';
            $args[] = $actor;
        }
        foreach (['de' => '>=', 'ate' => '<'] as $key => $operator) {
            if (is_string($filters[$key] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $filters[$key]) === 1) {
                $where[] = $key === 'de' ? 'l.created_at >= ?' : 'l.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
                $args[] = $key === 'de' ? $filters[$key] . ' 00:00:00' : $filters[$key];
            }
        }
        $sql = ' FROM organization_audit_logs AS l LEFT JOIN users AS u ON u.id = l.actor_user_id WHERE ' . implode(' AND ', $where);
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $count = Connection::get()->prepare('SELECT COUNT(*)' . $sql);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $query = Connection::get()->prepare(
            'SELECT l.id, l.actor_user_id, l.event_type, l.resource_type, l.resource_id, l.changes_json, l.created_at,
                    TRIM(CONCAT_WS(" ", u.first_name, u.last_name)) AS actor_name, u.email AS actor_email'
            . $sql . ' ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $query->execute($args);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['changes'] = is_string($row['changes_json']) ? (json_decode($row['changes_json'], true) ?: []) : [];
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** Quem já apareceu no registro da empresa, para o filtro por pessoa. */
    public static function actors(int $organizationId): array
    {
        $statement = Connection::get()->prepare(
            'SELECT DISTINCT u.id, TRIM(CONCAT_WS(" ", u.first_name, u.last_name)) AS name
             FROM organization_audit_logs AS l INNER JOIN users AS u ON u.id = l.actor_user_id
             WHERE l.organization_id = ? ORDER BY name'
        );
        $statement->execute([$organizationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
