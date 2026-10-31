<?php
declare(strict_types=1);

namespace Moves\Services;

use Throwable;

/**
 * Contexto PJ opt-in. Nunca usa CNPJ/ID recebido por GET como autorização.
 * O contexto PF continua sendo o padrão e os módulos financeiros atuais não
 * consomem este estado até terem queries explicitamente escopadas e testadas.
 */
final class OrganizationContext
{
    private const SESSION_KEY = 'realizze_organization_context';

    public static function enabled(): bool
    {
        return getenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED') === '1';
    }

    public static function current(): ?array
    {
        if (!self::enabled() || session_status() !== PHP_SESSION_ACTIVE) {
            self::clear();
            return null;
        }

        $saved = $_SESSION[self::SESSION_KEY] ?? null;
        if ($saved === null) {
            return null;
        }
        $user = SessionAuth::current();
        if (!is_array($saved) || $user === null ||
            !is_int($saved['organization_id'] ?? null) ||
            !is_int($saved['user_id'] ?? null) ||
            !is_int($saved['auth_version'] ?? null) ||
            $saved['user_id'] !== (int) $user['id'] ||
            $saved['auth_version'] !== (int) $user['auth_version']) {
            self::clear();
            return null;
        }

        try {
            // A associação e a permissão são revalidadas em CADA requisição.
            $organization = OrganizationAccess::authorizedOrganization($saved['organization_id']);
        } catch (Throwable $exception) {
            error_log('Realizze: contexto empresarial indisponível.');
            $organization = null;
        }
        if ($organization === null) {
            self::clear();
        }
        return $organization;
    }

    public static function select(int $organizationId, mixed $csrfToken): bool
    {
        if (!self::enabled() || !Csrf::validate($csrfToken) ||
            session_status() !== PHP_SESSION_ACTIVE || $organizationId <= 0) {
            return false;
        }
        $user = SessionAuth::current();
        if ($user === null) {
            return false;
        }
        try {
            $organization = OrganizationAccess::authorizedOrganization($organizationId);
        } catch (Throwable $exception) {
            error_log('Realizze: falha ao validar acesso empresarial.');
            $organization = null;
        }
        if ($organization === null) {
            self::clear();
            return false;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'organization_id' => (int) $organization['id'],
            'user_id' => (int) $user['id'],
            'auth_version' => (int) $user['auth_version'],
        ];
        return true;
    }

    public static function selectPersonal(mixed $csrfToken): bool
    {
        if (!Csrf::validate($csrfToken) ||
            session_status() !== PHP_SESSION_ACTIVE || SessionAuth::current() === null) {
            return false;
        }
        session_regenerate_id(true);
        self::clear();
        return true;
    }

    public static function clear(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }
}
