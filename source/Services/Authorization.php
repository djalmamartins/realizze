<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

final class Authorization
{
    public function can(
        int $userId,
        int $organizationId,
        string $permission
    ): bool {
        if ($userId <= 0 || $organizationId <= 0 || $permission === '') {
            return false;
        }

        $sql = "
            SELECT 1
            FROM organization_members AS members
            INNER JOIN organization_roles AS roles
                ON roles.id = members.role_id
                AND roles.organization_id = members.organization_id
            INNER JOIN organizations AS organizations
                ON organizations.id = members.organization_id
            INNER JOIN organization_role_permissions AS permissions
                ON permissions.role_id = roles.id
            WHERE members.user_id = :user_id
              AND members.organization_id = :organization_id
              AND members.status = 'active'
              AND organizations.status = 'active'
              AND permissions.permission_key = :permission
            LIMIT 1
        ";

        $statement = Connection::get()->prepare($sql);

        $statement->execute([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'permission' => $permission,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
