<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

/** Administradores e matriz de permissões. Papel superadmin é imutável e tem tudo. */
final class CmsAdmins
{
    public static function listing(): array
    {
        return CmsQuery::all(
            'SELECT a.id,a.user_id,a.status,a.role_id,a.created_at,r.name AS role_name,r.role_key,u.first_name,u.last_name,u.email
             FROM cms_admins a JOIN users u ON u.id=a.user_id JOIN cms_roles r ON r.id=a.role_id ORDER BY a.id'
        );
    }

    public static function roles(): array
    {
        return CmsQuery::all('SELECT * FROM cms_roles ORDER BY id');
    }

    public static function grant(string $email, int $roleId, array $actor): void
    {
        $user = CmsQuery::one("SELECT id FROM users WHERE email=? AND status='active'", [trim($email)]);
        if ($user === null) {
            throw new InvalidArgumentException('Não há usuário ativo com este e-mail.');
        }
        self::assertAssignable($roleId, $actor);
        CmsQuery::run(
            "INSERT INTO cms_admins (user_id,role_id,status,created_by) VALUES (?,?,'active',?)
             ON DUPLICATE KEY UPDATE role_id=VALUES(role_id), status='active'",
            [$user['id'], $roleId, $actor['id']]
        );
        CmsAuth::forget();
        CmsAudit::record((int) $actor['id'], 'admin.granted', 'admin', (string) $user['id'], ['role_id' => $roleId]);
    }

    public static function update(int $adminId, int $roleId, string $status, array $actor): void
    {
        $target = CmsQuery::one('SELECT a.*, r.role_key FROM cms_admins a JOIN cms_roles r ON r.id=a.role_id WHERE a.id=?', [$adminId]);
        if ($target === null) {
            throw new InvalidArgumentException('Administrador não encontrado.');
        }
        if ((int) $target['user_id'] === (int) $actor['id']) {
            throw new InvalidArgumentException('Você não pode alterar o próprio acesso.');
        }
        if ($target['role_key'] === 'superadmin' && $actor['role_key'] !== 'superadmin') {
            throw new InvalidArgumentException('Somente um superadministrador pode alterar outro.');
        }
        self::assertAssignable($roleId, $actor);
        $status = $status === 'inactive' ? 'inactive' : 'active';
        CmsQuery::run('UPDATE cms_admins SET role_id=?, status=? WHERE id=?', [$roleId, $status, $adminId]);
        CmsAuth::forget();
        CmsAudit::record((int) $actor['id'], 'admin.updated', 'admin', (string) $target['user_id'], ['role_id' => $roleId, 'status' => $status]);
    }

    private static function assertAssignable(int $roleId, array $actor): void
    {
        $role = CmsQuery::one('SELECT role_key FROM cms_roles WHERE id=?', [$roleId]);
        if ($role === null) {
            throw new InvalidArgumentException('Papel inválido.');
        }
        if ($role['role_key'] === 'superadmin' && $actor['role_key'] !== 'superadmin') {
            throw new InvalidArgumentException('Somente um superadministrador pode conceder este papel.');
        }
    }

    public static function matrix(): array
    {
        $matrix = [];
        foreach (CmsQuery::all('SELECT role_id, permission_key FROM cms_role_permissions') as $row) {
            $matrix[(int) $row['role_id']][$row['permission_key']] = true;
        }
        return $matrix;
    }

    public static function saveMatrix(array $input, int $actorId): void
    {
        CmsQuery::transaction(static function () use ($input, $actorId): void {
            foreach (self::roles() as $role) {
                if ($role['role_key'] === 'superadmin') {
                    continue;
                }
                $requested = is_array($input[$role['id']] ?? null) ? array_keys($input[$role['id']]) : [];
                $requested = array_values(array_intersect($requested, array_keys(CmsAuth::PERMISSIONS)));
                CmsQuery::run('DELETE FROM cms_role_permissions WHERE role_id=?', [$role['id']]);
                foreach ($requested as $permission) {
                    CmsQuery::run('INSERT INTO cms_role_permissions (role_id,permission_key) VALUES (?,?)', [$role['id'], $permission]);
                }
                CmsAudit::record($actorId, 'permissions.updated', 'role', (string) $role['id'], ['permissions' => $requested]);
            }
        });
        CmsAuth::forget();
    }
}
