<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;

/**
 * Resolve o contexto financeiro da sessão: a empresa selecionada, validada a cada
 * requisição por OrganizationContext, ou a pessoa física do usuário autenticado.
 */
final class FinanceContext
{
    public static function currentId(): ?int
    {
        $user = SessionAuth::current();
        if ($user === null) {
            return null;
        }
        $organization = OrganizationContext::current();
        return $organization !== null
            ? self::forOrganization((int) $organization['id'])
            : self::forUser(PersonalAccessContext::ownerId() ?? (int) $user['id']);
    }

    public static function forUser(int $userId): int
    {
        return self::resolve('personal', 'personal_user_id', $userId);
    }

    public static function forOrganization(int $organizationId): int
    {
        return self::resolve('organization', 'organization_id', $organizationId);
    }

    private static function resolve(string $type, string $column, int $ownerId): int
    {
        $pdo = Connection::get();
        $find = $pdo->prepare("SELECT id FROM financial_contexts WHERE {$column} = ?");
        $find->execute([$ownerId]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $pdo->prepare("INSERT IGNORE INTO financial_contexts (context_type, {$column}) VALUES (?, ?)")
                ->execute([$type, $ownerId]);
            $find->execute([$ownerId]);
            $id = $find->fetchColumn();
        }
        return (int) $id;
    }
}
