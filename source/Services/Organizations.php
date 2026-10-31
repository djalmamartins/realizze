<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use Throwable;

/** Cadastro de empresas: quem cadastra vira dono, com papel e permissões próprios. */
final class Organizations
{
    public static function create(int $ownerId, string $name, string $cnpj): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 180) {
            throw new InvalidArgumentException('Informe o nome da empresa.');
        }
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';
        if ($digits !== '' && !self::validCnpj($digits)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO organizations (owner_user_id, name, document_type, document_value, status) VALUES (?, ?, ?, ?, 'active')"
            )->execute([$ownerId, $name, $digits === '' ? null : 'cnpj', $digits === '' ? null : $digits]);
            $organizationId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO organization_roles (organization_id, role_key, name, is_system) VALUES (?, 'owner', 'Proprietário', 1)"
            )->execute([$organizationId]);
            $roleId = (int) $pdo->lastInsertId();
            $permission = $pdo->prepare('INSERT INTO organization_role_permissions (role_id, permission_key) VALUES (?, ?)');
            foreach (['organization.view', 'finance.manage'] as $key) {
                $permission->execute([$roleId, $key]);
            }
            $pdo->prepare(
                "INSERT INTO organization_members (organization_id, user_id, role_id, status, joined_at) VALUES (?, ?, ?, 'active', NOW())"
            )->execute([$organizationId, $ownerId, $roleId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        return $organizationId;
    }

    public static function validCnpj(string $value): bool
    {
        if (!preg_match('/^\d{14}$/', $value) || preg_match('/^(\d)\1+$/', $value)) {
            return false;
        }
        foreach ([12, 13] as $size) {
            $sum = 0;
            $weight = $size - 7;
            for ($i = 0; $i < $size; $i++) {
                $sum += (int) $value[$i] * $weight--;
                if ($weight < 2) {
                    $weight = 9;
                }
            }
            $rest = $sum % 11;
            if ((int) $value[$size] !== ($rest < 2 ? 0 : 11 - $rest)) {
                return false;
            }
        }
        return true;
    }
}
