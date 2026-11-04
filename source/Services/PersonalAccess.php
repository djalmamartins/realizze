<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

final class PersonalAccess
{
    public const PERMISSIONS = ['dashboard.view','income.view','expenses.view','income.create','expenses.create','entries.edit','payments.register','categories.manage','accounts.view','reports.view','goals.manage'];
    public const PERMISSION_LABELS = [
        'dashboard.view' => 'Ver visão geral',
        'income.view' => 'Ver contas a receber',
        'expenses.view' => 'Ver contas a pagar',
        'income.create' => 'Criar contas a receber',
        'expenses.create' => 'Criar contas a pagar',
        'entries.edit' => 'Editar lançamentos',
        'payments.register' => 'Registrar pagamentos',
        'categories.manage' => 'Gerenciar categorias',
        'accounts.view' => 'Ver contas e cartões',
        'reports.view' => 'Ver relatórios',
        'goals.manage' => 'Gerenciar metas',
    ];

    public static function invite(int $ownerId, string $email, array $permissions): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('E-mail do convidado inválido.');
        $owner = Connection::get()->prepare('SELECT email FROM users WHERE id=?'); $owner->execute([$ownerId]);
        if (strcasecmp($email, (string) $owner->fetchColumn()) === 0) throw new \InvalidArgumentException('Use o e-mail da pessoa convidada.');
        $permissions = array_values(array_intersect(self::PERMISSIONS, array_map('strval', $permissions)));
        if ($permissions === []) throw new \InvalidArgumentException('Selecione ao menos uma permissão.');
        $token = bin2hex(random_bytes(32));
        $pdo = Connection::get(); $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE personal_access_invites SET status='revoked',revoked_at=NOW() WHERE owner_user_id=? AND invited_email=? AND status='pending'")->execute([$ownerId, $email]);
            $pdo->prepare("INSERT INTO personal_access_invites (owner_user_id,invited_email,token_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))")->execute([$ownerId, $email, hash('sha256', $token)]);
            $id = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO personal_access_permissions (invite_id,permission_key) VALUES (?,?)');
            foreach ($permissions as $permission) $insert->execute([$id, $permission]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        UserActivity::record($ownerId, 'access.invite_sent', 'success', ['invite_id' => $id]);
        return $token;
    }

    public static function accept(int $userId, string $email, string $token): bool
    {
        $statement = Connection::get()->prepare("UPDATE personal_access_invites SET status='accepted',invited_user_id=?,accepted_at=NOW() WHERE invited_email=? AND token_hash=? AND status='pending' AND expires_at>NOW()");
        $statement->execute([$userId, strtolower($email), hash('sha256', $token)]);
        if ($statement->rowCount() !== 1) return false;
        $find = Connection::get()->prepare('SELECT id,owner_user_id FROM personal_access_invites WHERE invited_user_id=? AND token_hash=?'); $find->execute([$userId, hash('sha256', $token)]); $row = $find->fetch(PDO::FETCH_ASSOC);
        if ($row) UserActivity::record((int) $row['owner_user_id'], 'access.invite_accepted', 'success', ['invite_id' => (int) $row['id']], $userId);
        return true;
    }

    public static function decline(int $userId, string $email, string $token): bool
    {
        $statement = Connection::get()->prepare("UPDATE personal_access_invites SET status='declined',invited_user_id=? WHERE invited_email=? AND token_hash=? AND status='pending' AND expires_at>NOW()");
        $statement->execute([$userId, strtolower($email), hash('sha256', $token)]);
        return $statement->rowCount() === 1;
    }

    public static function revoke(int $ownerId, int $inviteId): bool
    {
        $statement = Connection::get()->prepare("UPDATE personal_access_invites SET status='revoked',revoked_at=NOW() WHERE id=? AND owner_user_id=? AND status IN ('pending','accepted')");
        $statement->execute([$inviteId, $ownerId]);
        if ($statement->rowCount()) UserActivity::record($ownerId, 'access.revoked', 'success', ['invite_id' => $inviteId]);
        return $statement->rowCount() === 1;
    }

    public static function listing(int $ownerId): array
    {
        $statement = Connection::get()->prepare("SELECT i.id,i.invited_email,i.status,i.expires_at,i.accepted_at,i.created_at,GROUP_CONCAT(p.permission_key ORDER BY p.permission_key) permissions FROM personal_access_invites i LEFT JOIN personal_access_permissions p ON p.invite_id=i.id WHERE i.owner_user_id=? GROUP BY i.id ORDER BY i.id DESC");
        $statement->execute([$ownerId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function allowed(int $actorId, int $ownerId, string $permission): bool
    {
        if ($actorId === $ownerId) return true;
        $statement = Connection::get()->prepare("SELECT 1 FROM personal_access_invites i JOIN personal_access_permissions p ON p.invite_id=i.id WHERE i.owner_user_id=? AND i.invited_user_id=? AND i.status='accepted' AND p.permission_key=? LIMIT 1");
        $statement->execute([$ownerId, $actorId, $permission]);
        return $statement->fetchColumn() !== false;
    }

    public static function hasAnyAccess(int $actorId, int $ownerId): bool
    {
        $statement = Connection::get()->prepare("SELECT 1 FROM personal_access_invites WHERE owner_user_id=? AND invited_user_id=? AND status='accepted' LIMIT 1");
        $statement->execute([$ownerId, $actorId]);
        return $statement->fetchColumn() !== false;
    }

    public static function sharedWith(int $actorId): array
    {
        $statement = Connection::get()->prepare("SELECT DISTINCT i.owner_user_id id,CONCAT_WS(' ',u.first_name,u.last_name) name FROM personal_access_invites i JOIN users u ON u.id=i.owner_user_id WHERE i.invited_user_id=? AND i.status='accepted' ORDER BY name");
        $statement->execute([$actorId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
