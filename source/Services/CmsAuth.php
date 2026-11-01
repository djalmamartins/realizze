<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/**
 * Administrador do CMS: usuário de SessionAuth vinculado a cms_admins.
 * O papel e as permissões vêm sempre do banco, nunca do navegador.
 */
final class CmsAuth
{
    public const PERMISSIONS = [
        'dashboard.view' => 'Ver dashboard',
        'pages.manage' => 'Gerenciar páginas',
        'content.manage' => 'Editar conteúdos',
        'content.publish' => 'Publicar conteúdo',
        'media.manage' => 'Gerenciar mídia',
        'menus.manage' => 'Gerenciar menus',
        'users.view' => 'Ver usuários',
        'users.manage' => 'Administrar usuários',
        'companies.view' => 'Ver empresas',
        'companies.manage' => 'Administrar empresas',
        'plans.view' => 'Ver planos',
        'plans.manage' => 'Alterar planos e preços',
        'subscriptions.view' => 'Ver assinaturas',
        'subscriptions.manage' => 'Alterar assinaturas',
        'charges.view' => 'Ver cobranças',
        'charges.manage' => 'Registrar pagamentos e falhas',
        'tickets.view' => 'Visualizar chamados',
        'tickets.reply' => 'Responder chamados',
        'tickets.assign' => 'Atribuir chamados',
        'tickets.categories' => 'Gerenciar categorias',
        'tickets.reports' => 'Ver relatórios de atendimento',
        'notifications.configure' => 'Configurar notificações',
        'reports.view' => 'Ver relatórios de acesso',
        'broadcasts.send' => 'Enviar comunicações',
        'versions.manage' => 'Publicar versões',
        'system_logs.view' => 'Ver e resolver log técnico',
        'admins.manage' => 'Gerenciar administradores',
        'permissions.manage' => 'Gerenciar permissões',
        'audit.view' => 'Ver auditoria',
        'settings.manage' => 'Alterar configurações',
    ];

    /** @var array<int, array|null> */
    private static array $cache = [];

    public static function current(): ?array
    {
        $user = SessionAuth::current();
        return $user === null ? null : self::forUser((int) $user['id']);
    }

    public static function forUser(int $userId): ?array
    {
        if (array_key_exists($userId, self::$cache)) {
            return self::$cache[$userId];
        }
        $stmt = Connection::get()->prepare(
            "SELECT a.id AS admin_id, a.role_id, r.role_key, r.name AS role_name,
                    u.id, u.first_name, u.last_name, u.email
             FROM cms_admins a
             JOIN cms_roles r ON r.id = a.role_id
             JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ? AND a.status = 'active' AND u.status = 'active' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($admin !== null) {
            if ($admin['role_key'] === 'superadmin') {
                $admin['permissions'] = array_keys(self::PERMISSIONS);
            } else {
                $perms = Connection::get()->prepare('SELECT permission_key FROM cms_role_permissions WHERE role_id = ?');
                $perms->execute([$admin['role_id']]);
                $admin['permissions'] = $perms->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        return self::$cache[$userId] = $admin;
    }

    public static function can(?array $admin, string $permission): bool
    {
        return $admin !== null && in_array($permission, $admin['permissions'] ?? [], true);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** Autentica um administrador. Usuário comum recebe a mesma recusa de senha errada. */
    public static function attempt(string $email, string $password, string $ip): ?array
    {
        $limiter = new AuthRateLimiter();
        $ipAllowed = $limiter->hit('cms-login-ip', $ip, 20, 900);
        $accountAllowed = $limiter->hit('cms-login-account', strtolower(trim($email)), 8, 900);
        if (!$ipAllowed || !$accountAllowed) {
            error_log('Realizze CMS: login limitado.');
            return null;
        }
        $user = (new Authentication())->attempt($email, $password);
        if ($user === null) {
            return null;
        }
        $admin = self::forUser((int) $user['id']);
        if ($admin === null) {
            CmsAudit::record((int) $user['id'], 'cms.login_denied', 'user', (string) $user['id']);
            return null;
        }
        SessionAuth::signIn((int) $user['id'], (int) $user['auth_version']);
        CmsAudit::record((int) $user['id'], 'cms.login', 'user', (string) $user['id']);
        return $admin;
    }
}
