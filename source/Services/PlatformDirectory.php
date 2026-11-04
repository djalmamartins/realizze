<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

/**
 * Usuários e empresas vistos pela administração da plataforma.
 * Nunca seleciona senha, documento nem dados de finance_*.
 */
final class PlatformDirectory
{
    public const USER_STATUS = ['registered' => 'Registrado', 'active' => 'Ativo', 'suspended' => 'Suspenso'];

    public static function users(array $filters, int $page): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ?)";
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        if (isset(self::USER_STATUS[$filters['status'] ?? ''])) {
            $where[] = 'u.status = ?';
            $args[] = $filters['status'];
        }
        if (($filters['admin'] ?? '') === '1') {
            $where[] = 'a.id IS NOT NULL';
        }
        return CmsQuery::page(
            'FROM users u LEFT JOIN cms_admins a ON a.user_id=u.id WHERE ' . implode(' AND ', $where),
            "u.id,u.first_name,u.last_name,u.email,u.status,u.created_at,a.id AS admin_id,
             (SELECT p.name FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.user_id=u.id AND s.status<>'canceled' ORDER BY s.id DESC LIMIT 1) AS plan_name",
            'u.id DESC', $args, $page
        );
    }

    public static function user(int $id): ?array
    {
        return CmsQuery::one('SELECT id,first_name,last_name,email,status,created_at,updated_at FROM users WHERE id=?', [$id]);
    }

    public static function userCompanies(int $id): array
    {
        return CmsQuery::all(
            "SELECT o.id,o.name,o.trade_name,o.status,r.name AS role_name, o.owner_user_id=? AS is_owner
             FROM organization_members m JOIN organizations o ON o.id=m.organization_id JOIN organization_roles r ON r.id=m.role_id
             WHERE m.user_id=? AND m.status='active' ORDER BY o.name",
            [$id, $id]
        );
    }

    public static function setUserSuspended(int $id, bool $suspended, string $reason, array $admin): void
    {
        $user = self::user($id);
        if ($user === null) {
            throw new InvalidArgumentException('Usuário não encontrado.');
        }
        if ($id === (int) $admin['id']) {
            throw new InvalidArgumentException('Você não pode suspender a própria conta.');
        }
        $target = CmsAuth::forUser($id);
        if ($target !== null && $target['role_key'] === 'superadmin' && $admin['role_key'] !== 'superadmin') {
            throw new InvalidArgumentException('Somente um superadministrador pode suspender outro.');
        }
        $reason = CmsContent::text($reason, 255, 'Motivo');
        if ($suspended && $reason === '') {
            throw new InvalidArgumentException('Informe o motivo da suspensão.');
        }
        if ($suspended === ($user['status'] === 'suspended')) {
            return;
        }
        if (!$suspended && $user['status'] !== 'suspended') {
            throw new InvalidArgumentException('Usuário não está suspenso.');
        }
        // auth_version invalida as sessões abertas do usuário.
        CmsQuery::run('UPDATE users SET status=?, auth_version=auth_version+1 WHERE id=?', [$suspended ? 'suspended' : 'active', $id]);
        CmsAuth::forget();
        CmsAudit::record((int) $admin['id'], $suspended ? 'user.suspended' : 'user.reactivated', 'user', (string) $id, ['reason' => $reason, 'from' => $user['status']]);
    }

    public static function companies(array $filters, int $page): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(o.name LIKE ? OR o.trade_name LIKE ? OR u.email LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $where[] = 'o.status = ?';
            $args[] = $filters['status'];
        }
        return CmsQuery::page(
            'FROM organizations o JOIN users u ON u.id=o.owner_user_id WHERE ' . implode(' AND ', $where),
            "o.id,o.name,o.trade_name,o.status,o.created_at,u.email AS owner_email,
             (SELECT COUNT(*) FROM organization_members m WHERE m.organization_id=o.id AND m.status='active') AS members_count,
             (SELECT p.name FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.organization_id=o.id AND s.status<>'canceled' ORDER BY s.id DESC LIMIT 1) AS plan_name",
            'o.id DESC', $args, $page
        );
    }

    public static function company(int $id): ?array
    {
        return CmsQuery::one(
            "SELECT o.id,o.name,o.trade_name,o.email,o.phone,o.status,o.created_at,o.owner_user_id,u.first_name AS owner_first_name,u.last_name AS owner_last_name,u.email AS owner_email
             FROM organizations o JOIN users u ON u.id=o.owner_user_id WHERE o.id=?",
            [$id]
        );
    }

    public static function companyMembers(int $id): array
    {
        return CmsQuery::all(
            "SELECT u.id,u.first_name,u.last_name,u.email,r.name AS role_name,m.joined_at,
                    (SELECT COUNT(*) FROM helpdesk_organization_access h WHERE h.organization_id=m.organization_id AND h.user_id=u.id) AS support_access
             FROM organization_members m JOIN users u ON u.id=m.user_id JOIN organization_roles r ON r.id=m.role_id
             WHERE m.organization_id=? AND m.status='active' ORDER BY r.id, u.first_name",
            [$id]
        );
    }

    public static function setCompanyStatus(int $id, bool $active, string $reason, int $actorId): void
    {
        $reason = CmsContent::text($reason, 255, 'Motivo');
        if (!$active && $reason === '') {
            throw new InvalidArgumentException('Informe o motivo da inativação.');
        }
        if (CmsQuery::run('UPDATE organizations SET status=? WHERE id=?', [$active ? 'active' : 'inactive', $id]) === 0 && self::company($id) === null) {
            throw new InvalidArgumentException('Empresa não encontrada.');
        }
        CmsAudit::record($actorId, $active ? 'company.reactivated' : 'company.inactivated', 'company', (string) $id, ['reason' => $reason]);
    }

    /** Dashboard: somente contagens da plataforma, nunca finanças de clientes. */
    public static function dashboard(): array
    {
        $count = static fn (string $sql, array $args = []): int => (int) CmsQuery::value($sql, $args);
        $ticketStatus = [];
        foreach (CmsQuery::all('SELECT status, COUNT(*) AS total FROM helpdesk_tickets GROUP BY status') as $row) {
            $ticketStatus[$row['status']] = (int) $row['total'];
        }
        $subStatus = [];
        foreach (CmsQuery::all('SELECT status, COUNT(*) AS total FROM subscriptions GROUP BY status') as $row) {
            $subStatus[$row['status']] = (int) $row['total'];
        }
        return [
            'users_total' => $count('SELECT COUNT(*) FROM users'),
            'users_active' => $count("SELECT COUNT(*) FROM users WHERE status='active'"),
            'users_new_30' => $count('SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 30 DAY'),
            'companies_total' => $count('SELECT COUNT(*) FROM organizations'),
            'subs' => $subStatus,
            'plans_contracted' => $count("SELECT COUNT(DISTINCT plan_id) FROM subscriptions WHERE status IN ('active','past_due')"),
            'charges_pending' => $count("SELECT COUNT(*) FROM platform_charges WHERE status IN ('pending','overdue')"),
            'mrr' => (float) CmsQuery::value("SELECT COALESCE(SUM(IF(billing_cycle='annual', contracted_amount/12, contracted_amount)),0) FROM subscriptions WHERE status='active'"),
            'tickets' => $ticketStatus,
            'tickets_pending' => ($ticketStatus['open'] ?? 0) + ($ticketStatus['waiting_agent'] ?? 0),
            'first_response_avg' => CmsQuery::value('SELECT AVG(TIMESTAMPDIFF(MINUTE,created_at,first_response_at)) FROM helpdesk_tickets WHERE first_response_at IS NOT NULL'),
            'resolution_avg' => CmsQuery::value('SELECT AVG(TIMESTAMPDIFF(MINUTE,created_at,resolved_at)) FROM helpdesk_tickets WHERE resolved_at IS NOT NULL'),
        ];
    }
}
