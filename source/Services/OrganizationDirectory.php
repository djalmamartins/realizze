<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Leitura dos dados e dos membros de uma empresa já autorizada pelo contexto atual. */
final class OrganizationDirectory
{
    public static function profile(int $organizationId): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT id, name, trade_name, document_type, document_value, email, phone, status, created_at FROM organizations WHERE id = ?'
        );
        $statement->execute([$organizationId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function user(int $userId): ?array
    {
        $statement = Connection::get()->prepare('SELECT first_name, last_name, email, status, created_at FROM users WHERE id = ?');
        $statement->execute([$userId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function members(int $organizationId): array
    {
        $statement = Connection::get()->prepare(
            "SELECT u.first_name, u.last_name, u.email, r.name AS role, m.status, m.joined_at
             FROM organization_members AS m
             INNER JOIN users AS u ON u.id = m.user_id
             INNER JOIN organization_roles AS r ON r.id = m.role_id
             WHERE m.organization_id = ? AND m.removed_at IS NULL
             ORDER BY m.joined_at, u.first_name"
        );
        $statement->execute([$organizationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
