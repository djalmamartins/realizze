<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/**
 * Consulta organizações do usuário autenticado usando o modelo existente.
 * A permissão organization.view é verificada pelo serviço Authorization.
 */
final class OrganizationAccess
{
    public static function accessibleForCurrentUser(): array
    {
        $user = SessionAuth::current();
        if ($user === null) {
            return [];
        }

        $statement = Connection::get()->prepare(
            "SELECT DISTINCT o.id FROM organizations AS o
             INNER JOIN organization_members AS m ON m.organization_id = o.id
             WHERE m.user_id = :user_id AND m.status = 'active'
               AND o.status = 'active' ORDER BY o.id"
        );
        $statement->execute(['user_id' => (int) $user['id']]);

        $organizations = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $organizationId) {
            $organization = self::authorizedForUser((int) $user['id'], (int) $organizationId);
            if ($organization !== null) {
                $organizations[] = $organization;
            }
        }
        return $organizations;
    }

    public static function authorizedOrganization(int $organizationId): ?array
    {
        $user = SessionAuth::current();
        if ($user === null) {
            return null;
        }
        return self::authorizedForUser((int) $user['id'], $organizationId);
    }

    private static function authorizedForUser(int $userId, int $organizationId): ?array
    {
        if ($userId <= 0 || $organizationId <= 0 ||
            !(new Authorization())->can($userId, $organizationId, 'organization.view')) {
            return null;
        }

        $statement = Connection::get()->prepare(
            "SELECT o.id, o.name, r.role_key AS role
             FROM organizations AS o
             INNER JOIN organization_members AS m ON m.organization_id = o.id
             INNER JOIN organization_roles AS r ON r.id = m.role_id
                 AND r.organization_id = o.id
             WHERE o.id = :organization_id AND m.user_id = :user_id
               AND m.status = 'active' AND o.status = 'active' LIMIT 1"
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'user_id' => $userId,
        ]);
        $organization = $statement->fetch(PDO::FETCH_ASSOC);
        return $organization ?: null;
    }
}
