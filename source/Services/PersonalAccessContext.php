<?php
declare(strict_types=1);

namespace Moves\Services;

/** Contexto PF delegado. O convidado continua autenticado como ele mesmo. */
final class PersonalAccessContext
{
    private const KEY = 'realizze_personal_access_owner';

    /** Telas PF que um acesso delegado pode abrir. As demais respondem 403 e ficam fora do menu. */
    public const SECTION_PERMISSIONS = [
        'overview' => 'dashboard.view', 'receber' => 'income.view', 'pagar' => 'expenses.view', 'contas' => 'accounts.view',
        'relatorios' => 'reports.view', 'metas' => 'goals.manage', 'categorias' => 'categories.manage',
    ];

    /** A tela abre no contexto PF atual? Sem delegação, o titular abre todas. */
    public static function canOpen(string $section): bool
    {
        if (self::ownerId() === null) return true;
        $permission = self::SECTION_PERMISSIONS[$section] ?? null;
        return $permission !== null && self::can($permission);
    }

    public static function ownerId(): ?int
    {
        $actor = SessionAuth::current();
        $owner = $_SESSION[self::KEY] ?? null;
        if ($actor === null || !is_int($owner) || $owner <= 0 || $owner === (int) $actor['id']) return null;
        if (!PersonalAccess::hasAnyAccess((int) $actor['id'], $owner)) { unset($_SESSION[self::KEY]); return null; }
        return $owner;
    }

    public static function select(int $ownerId, mixed $csrf): bool
    {
        $actor = SessionAuth::current();
        if ($actor === null || !Csrf::validate($csrf) || !PersonalAccess::hasAnyAccess((int) $actor['id'], $ownerId)) return false;
        OrganizationContext::clear();
        session_regenerate_id(true);
        $_SESSION[self::KEY] = $ownerId;
        return true;
    }

    public static function clear(): void { unset($_SESSION[self::KEY]); }

    public static function can(string $permission): bool
    {
        $actor = SessionAuth::current();
        $owner = self::ownerId();
        return $actor !== null && ($owner === null || PersonalAccess::allowed((int) $actor['id'], $owner, $permission));
    }
}
