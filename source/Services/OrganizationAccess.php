<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Consultas de escopo empresarial; nunca confie em organization_id vindo da interface. */
final class OrganizationAccess
{
    public static function accessibleForCurrentUser(): array
    {
        $user = SessionAuth::current();
        if ($user === null) {
            return [];
        }
        $statement = Connection::get()->prepare(
            'SELECT o.id, o.legal_name, o.trade_name, m.role '
            . 'FROM organizations o INNER JOIN organization_members m ON m.organization_id = o.id '
            . 'WHERE m.user_id = :user_id AND m.status = :membership_status AND o.status = :org_status '
            . 'ORDER BY o.legal_name, o.id'
        );
        $statement->execute([
            'user_id' => (int) $user['id'],
            'membership_status' => 'active',
            'org_status' => 'active',
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function authorizedOrganization(int $organizationId): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }
        $user = SessionAuth::current();
        if ($user === null) {
            return null;
        }
        $statement = Connection::get()->prepare(
            'SELECT o.id, o.legal_name, o.trade_name, m.role '
            . 'FROM organizations o INNER JOIN organization_members m ON m.organization_id = o.id '
            . 'WHERE o.id = :organization_id AND m.user_id = :user_id '
            . 'AND m.status = :membership_status AND o.status = :org_status LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'user_id' => (int) $user['id'],
            'membership_status' => 'active',
            'org_status' => 'active',
        ]);
        $organization = $statement->fetch(PDO::FETCH_ASSOC);
        return $organization ?: null;
    }
}
