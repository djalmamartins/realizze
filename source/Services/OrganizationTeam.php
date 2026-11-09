<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use Throwable;

/** Papéis, permissões, membros e convites de uma empresa. Toda decisão de acesso fica aqui, no servidor. */
final class OrganizationTeam
{
    public const PERMISSIONS = [
        'organization.view' => ['Empresa', 'Acessar a empresa'],
        'finance.view' => ['Financeiro', 'Ver lançamentos e movimentações'],
        'finance.manage' => ['Financeiro', 'Criar, editar e baixar lançamentos'],
        'contacts.view' => ['Cadastros', 'Ver clientes e fornecedores'],
        'contacts.manage' => ['Cadastros', 'Cadastrar e editar clientes e fornecedores'],
        'accounts.view' => ['Bancos', 'Ver contas, cartões e saldos'],
        'reports.view' => ['Relatórios', 'Ver relatórios'],
        'reports.export' => ['Relatórios', 'Exportar relatórios'],
        'planning.view' => ['Gestão', 'Ver planejamento e metas'],
        'team.manage' => ['Administração', 'Convidar pessoas e alterar acessos'],
    ];

    /** O proprietário tem tudo por definição e não é concedido por convite. */
    public const ROLES = [
        'admin' => ['Administrador', 'Gerencia dados e pessoas da empresa.', ['organization.view', 'finance.view', 'finance.manage', 'contacts.view', 'contacts.manage', 'accounts.view', 'reports.view', 'reports.export', 'planning.view', 'team.manage']],
        'finance' => ['Financeiro', 'Lança e baixa contas, exporta relatórios.', ['organization.view', 'finance.view', 'finance.manage', 'contacts.view', 'contacts.manage', 'accounts.view', 'reports.view', 'reports.export', 'planning.view']],
        'operational' => ['Operacional', 'Consulta o financeiro e mantém os cadastros.', ['organization.view', 'finance.view', 'contacts.view', 'contacts.manage']],
        'accountant' => ['Contabilidade', 'Consulta e exporta, sem alterar.', ['organization.view', 'finance.view', 'contacts.view', 'accounts.view', 'reports.view', 'reports.export']],
        'viewer' => ['Visualizador', 'Apenas consulta.', ['organization.view', 'finance.view', 'contacts.view', 'accounts.view', 'reports.view', 'planning.view']],
    ];

    /**
     * Permissão exigida por cada tela da empresa. A rota e o menu leem esta mesma lista:
     * um item só aparece no menu quando a tela abre para o papel.
     */
    public const SECTION_PERMISSIONS = [
        'overview' => 'finance.view', 'receber' => 'finance.view', 'pagar' => 'finance.view', 'movimentacoes' => 'finance.view',
        'recorrencias' => 'finance.view', 'fluxo-de-caixa' => 'finance.view', 'faturas' => 'finance.view', 'conciliacao' => 'finance.view',
        'centros-de-custo' => 'finance.view', 'obrigacoes' => 'finance.view', 'ordens-de-servico' => 'finance.view', 'categorias' => 'finance.view',
        'contas' => 'accounts.view', 'clientes' => 'contacts.view', 'fornecedores' => 'contacts.view', 'contatos' => 'contacts.view',
        'contato-detalhe' => 'contacts.view', 'relatorios' => 'reports.view', 'dre' => 'reports.view',
        'planejamento' => 'planning.view', 'metas' => 'planning.view', 'auditoria' => 'team.manage',
    ];

    private const INVITE_DAYS = 7;

    public static function isOwner(int $userId, int $organizationId): bool
    {
        $member = self::memberRow($organizationId, $userId);
        return $member !== null && $member['role_key'] === 'owner' && $member['status'] === 'active';
    }

    public static function can(int $userId, int $organizationId, string $permission): bool
    {
        return self::isOwner($userId, $organizationId) || (new Authorization())->can($userId, $organizationId, $permission);
    }

    /** @return list<string> */
    public static function permissionsFor(int $userId, int $organizationId): array
    {
        return array_values(array_filter(array_keys(self::PERMISSIONS), static fn (string $key): bool => self::can($userId, $organizationId, $key)));
    }

    /** Cria os papéis padrão que faltam na empresa. Pode ser chamado mais de uma vez. */
    public static function ensureRoles(int $organizationId): void
    {
        $pdo = Connection::get();
        $find = $pdo->prepare('SELECT id FROM organization_roles WHERE organization_id = ? AND role_key = ?');
        foreach (self::ROLES as $key => [$name, , $permissions]) {
            $find->execute([$organizationId, $key]);
            if ($find->fetchColumn() !== false) {
                continue;
            }
            $pdo->prepare('INSERT INTO organization_roles (organization_id, role_key, name, is_system) VALUES (?, ?, ?, 1)')
                ->execute([$organizationId, $key, $name]);
            $roleId = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO organization_role_permissions (role_id, permission_key) VALUES (?, ?)');
            foreach ($permissions as $permission) {
                $insert->execute([$roleId, $permission]);
            }
        }
    }

    public static function members(int $organizationId): array
    {
        $statement = Connection::get()->prepare(
            "SELECT m.user_id, u.first_name, u.last_name, u.email, r.role_key, r.name AS role, m.status, m.joined_at
             FROM organization_members AS m
             INNER JOIN users AS u ON u.id = m.user_id
             INNER JOIN organization_roles AS r ON r.id = m.role_id AND r.organization_id = m.organization_id
             WHERE m.organization_id = ? AND m.removed_at IS NULL
             ORDER BY r.role_key = 'owner' DESC, u.first_name"
        );
        $statement->execute([$organizationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function pendingInvitations(int $organizationId): array
    {
        $statement = Connection::get()->prepare(
            "SELECT i.id, i.email, i.expires_at, i.created_at, r.role_key, r.name AS role
             FROM organization_invitations AS i
             INNER JOIN organization_roles AS r ON r.id = i.role_id
             WHERE i.organization_id = ? AND i.status = 'pending' AND i.expires_at > ?
             ORDER BY i.created_at DESC, i.id DESC"
        );
        $statement->execute([$organizationId, self::now()]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return string token em claro, só para o link do convite. O banco guarda apenas o hash. */
    public static function invite(int $organizationId, int $actorId, string $email, string $roleKey): string
    {
        self::assertManager($organizationId, $actorId);
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        $roleId = self::assignableRole($organizationId, $actorId, $roleKey);
        $member = Connection::get()->prepare(
            'SELECT 1 FROM organization_members AS m INNER JOIN users AS u ON u.id = m.user_id
             WHERE m.organization_id = ? AND LOWER(u.email) = ? AND m.removed_at IS NULL'
        );
        $member->execute([$organizationId, $email]);
        if ($member->fetchColumn() !== false) {
            throw new InvalidArgumentException('Esta pessoa já faz parte da empresa.');
        }
        $pdo = Connection::get();
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE organization_invitations SET status = 'revoked', revoked_at = ? WHERE organization_id = ? AND email_hash = ? AND status = 'pending'")
                ->execute([self::now(), $organizationId, hash('sha256', $email)]);
            $pdo->prepare(
                "INSERT INTO organization_invitations (organization_id, role_id, invited_by_user_id, email, email_hash, token_hash, status, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)"
            )->execute([$organizationId, $roleId, $actorId, $email, hash('sha256', $email), hash('sha256', $token), self::expiry()]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        return $token;
    }

    /** Troca o token: o link anterior deixa de valer. @return array{token:string,email:string} */
    public static function resend(int $organizationId, int $actorId, int $invitationId): array
    {
        self::assertManager($organizationId, $actorId);
        $token = bin2hex(random_bytes(32));
        $pdo = Connection::get();
        $update = $pdo->prepare("UPDATE organization_invitations SET token_hash = ?, expires_at = ? WHERE id = ? AND organization_id = ? AND status = 'pending'");
        $update->execute([hash('sha256', $token), self::expiry(), $invitationId, $organizationId]);
        if ($update->rowCount() !== 1) {
            throw new InvalidArgumentException('Convite não encontrado.');
        }
        $find = $pdo->prepare('SELECT email FROM organization_invitations WHERE id = ?');
        $find->execute([$invitationId]);
        return ['token' => $token, 'email' => (string) $find->fetchColumn()];
    }

    public static function revoke(int $organizationId, int $actorId, int $invitationId): void
    {
        self::assertManager($organizationId, $actorId);
        $update = Connection::get()->prepare("UPDATE organization_invitations SET status = 'revoked', revoked_at = ? WHERE id = ? AND organization_id = ? AND status = 'pending'");
        $update->execute([self::now(), $invitationId, $organizationId]);
        if ($update->rowCount() !== 1) {
            throw new InvalidArgumentException('Convite não encontrado.');
        }
    }

    /** Dados do convite válido, para a página de aceite. */
    public static function invitationByToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }
        $statement = Connection::get()->prepare(
            "SELECT i.id, i.organization_id, i.role_id, i.email, i.email_hash, o.name AS organization, r.name AS role
             FROM organization_invitations AS i
             INNER JOIN organizations AS o ON o.id = i.organization_id AND o.status = 'active'
             INNER JOIN organization_roles AS r ON r.id = i.role_id
             WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > ?"
        );
        $statement->execute([hash('sha256', $token), self::now()]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** O convite só vale para a conta com o mesmo e-mail. @return int empresa */
    public static function accept(string $token, int $userId, string $userEmail): int
    {
        $invitation = self::invitationByToken($token);
        if ($invitation === null || !hash_equals((string) $invitation['email_hash'], hash('sha256', mb_strtolower(trim($userEmail))))) {
            throw new InvalidArgumentException('Convite inválido, expirado ou de outra conta.');
        }
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $claim = $pdo->prepare("UPDATE organization_invitations SET status = 'accepted', accepted_by_user_id = ?, accepted_at = ? WHERE id = ? AND status = 'pending'");
            $claim->execute([$userId, self::now(), $invitation['id']]);
            if ($claim->rowCount() !== 1) {
                throw new InvalidArgumentException('Convite já utilizado.');
            }
            $organizationId = (int) $invitation['organization_id'];
            $existing = self::memberRow($organizationId, $userId, true);
            if ($existing === null) {
                $pdo->prepare("INSERT INTO organization_members (organization_id, user_id, role_id, status, joined_at) VALUES (?, ?, ?, 'active', ?)")
                    ->execute([$organizationId, $userId, $invitation['role_id'], self::now()]);
            } elseif ($existing['role_key'] !== 'owner') {
                $pdo->prepare("UPDATE organization_members SET role_id = ?, status = 'active', removed_at = NULL WHERE organization_id = ? AND user_id = ?")
                    ->execute([$invitation['role_id'], $organizationId, $userId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        return $organizationId;
    }

    public static function decline(string $token, string $userEmail): void
    {
        $invitation = self::invitationByToken($token);
        if ($invitation === null || !hash_equals((string) $invitation['email_hash'], hash('sha256', mb_strtolower(trim($userEmail))))) {
            throw new InvalidArgumentException('Convite inválido, expirado ou de outra conta.');
        }
        Connection::get()->prepare("UPDATE organization_invitations SET status = 'declined', declined_at = ? WHERE id = ?")->execute([self::now(), $invitation['id']]);
    }

    public static function changeRole(int $organizationId, int $actorId, int $userId, string $roleKey): void
    {
        self::assertCanManageMember($organizationId, $actorId, $userId);
        $roleId = self::assignableRole($organizationId, $actorId, $roleKey);
        Connection::get()->prepare('UPDATE organization_members SET role_id = ? WHERE organization_id = ? AND user_id = ?')->execute([$roleId, $organizationId, $userId]);
    }

    public static function setActive(int $organizationId, int $actorId, int $userId, bool $active): void
    {
        self::assertCanManageMember($organizationId, $actorId, $userId);
        Connection::get()->prepare('UPDATE organization_members SET status = ? WHERE organization_id = ? AND user_id = ?')
            ->execute([$active ? 'active' : 'suspended', $organizationId, $userId]);
    }

    public static function remove(int $organizationId, int $actorId, int $userId): void
    {
        self::assertCanManageMember($organizationId, $actorId, $userId);
        Connection::get()->prepare("UPDATE organization_members SET status = 'removed', removed_at = ? WHERE organization_id = ? AND user_id = ?")
            ->execute([self::now(), $organizationId, $userId]);
    }

    private static function assertManager(int $organizationId, int $actorId): void
    {
        if (!self::can($actorId, $organizationId, 'team.manage')) {
            throw new InvalidArgumentException('Você não tem permissão para gerenciar a equipe.');
        }
    }

    private static function assertCanManageMember(int $organizationId, int $actorId, int $userId): void
    {
        self::assertManager($organizationId, $actorId);
        if ($actorId === $userId) {
            throw new InvalidArgumentException('Você não pode alterar o próprio acesso.');
        }
        $target = self::memberRow($organizationId, $userId);
        if ($target === null) {
            throw new InvalidArgumentException('Membro não encontrado.');
        }
        if ($target['role_key'] === 'owner') {
            throw new InvalidArgumentException('O acesso do proprietário não pode ser alterado.');
        }
        $own = self::permissionsFor($actorId, $organizationId);
        if (array_diff(self::ROLES[$target['role_key']][2] ?? array_keys(self::PERMISSIONS), $own) !== []) {
            throw new InvalidArgumentException('Este membro tem mais acesso do que você.');
        }
    }

    /** Ninguém concede o que não tem, e o papel de proprietário nunca é concedido. */
    private static function assignableRole(int $organizationId, int $actorId, string $roleKey): int
    {
        if (!isset(self::ROLES[$roleKey])) {
            throw new InvalidArgumentException('Escolha um papel válido.');
        }
        if (array_diff(self::ROLES[$roleKey][2], self::permissionsFor($actorId, $organizationId)) !== []) {
            throw new InvalidArgumentException('Você não pode conceder um acesso maior que o seu.');
        }
        self::ensureRoles($organizationId);
        $find = Connection::get()->prepare('SELECT id FROM organization_roles WHERE organization_id = ? AND role_key = ?');
        $find->execute([$organizationId, $roleKey]);
        return (int) $find->fetchColumn();
    }

    private static function memberRow(int $organizationId, int $userId, bool $includeRemoved = false): ?array
    {
        $statement = Connection::get()->prepare(
            'SELECT m.status, r.role_key FROM organization_members AS m
             INNER JOIN organization_roles AS r ON r.id = m.role_id AND r.organization_id = m.organization_id
             WHERE m.organization_id = ? AND m.user_id = ?' . ($includeRemoved ? '' : ' AND m.removed_at IS NULL')
        );
        $statement->execute([$organizationId, $userId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function expiry(): string
    {
        return date('Y-m-d H:i:s', time() + self::INVITE_DAYS * 86400);
    }
}
