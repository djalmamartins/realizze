<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDOException;
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
                "INSERT INTO organizations (owner_user_id, name, slug, document_type, document_value, status) VALUES (?, ?, ?, ?, ?, 'active')"
            )->execute([$ownerId, $name, null, $digits === '' ? null : 'cnpj', $digits === '' ? null : $digits]);
            $organizationId = (int) $pdo->lastInsertId();
            self::slugFor($organizationId, $name, $pdo);
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

    /**
     * Dados cadastrais da empresa. Exige team.manage (proprietário ou Administrador).
     * O endereço /e/{slug} não muda com o nome, para não quebrar links salvos.
     *
     * @return array<string, array{0:mixed,1:mixed}> campos alterados, com valor anterior e novo
     */
    public static function update(int $organizationId, int $actorId, array $data): array
    {
        if (!OrganizationTeam::can($actorId, $organizationId, 'team.manage')) {
            throw new InvalidArgumentException('Você não tem permissão para alterar os dados da empresa.');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) {
            throw new InvalidArgumentException('Informe a razão social, com até 180 caracteres.');
        }
        $tradeName = trim((string) ($data['trade_name'] ?? ''));
        if (mb_strlen($tradeName) > 180) {
            throw new InvalidArgumentException('O nome fantasia pode ter até 180 caracteres.');
        }
        $cnpj = preg_replace('/\D+/', '', (string) ($data['cnpj'] ?? '')) ?? '';
        if ($cnpj !== '' && !self::validCnpj($cnpj)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '' && (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?? '';
        if ($phone !== '' && !preg_match('/^\d{10,11}$/', $phone)) {
            throw new InvalidArgumentException('Informe o telefone com DDD, com 10 ou 11 números.');
        }
        $after = [
            'name' => $name,
            'trade_name' => $tradeName === '' ? null : $tradeName,
            'document_type' => $cnpj === '' ? null : 'cnpj',
            'document_value' => $cnpj === '' ? null : $cnpj,
            'email' => $email === '' ? null : $email,
            'phone' => $phone === '' ? null : $phone,
        ];
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare("SELECT name, trade_name, document_type, document_value, email, phone FROM organizations WHERE id = ? AND status = 'active' FOR UPDATE");
            $find->execute([$organizationId]);
            $before = $find->fetch(\PDO::FETCH_ASSOC);
            if ($before === false) {
                throw new InvalidArgumentException('Empresa não encontrada.');
            }
            $changes = [];
            foreach ($after as $field => $value) {
                if ((string) ($before[$field] ?? '') !== (string) ($value ?? '')) {
                    $changes[$field] = [$before[$field], $value];
                }
            }
            if ($changes !== []) {
                $pdo->prepare('UPDATE organizations SET name = ?, trade_name = ?, document_type = ?, document_value = ?, email = ?, phone = ? WHERE id = ?')
                    ->execute([...array_values($after), $organizationId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        if ($changes !== []) {
            OrganizationAudit::record($organizationId, $actorId, 'company.updated', 'organization', $organizationId, $changes);
        }
        return $changes;
    }

    public static function slugFor(int $organizationId, string $name, ?\PDO $pdo = null): string
    {
        $pdo ??= Connection::get();
        $find = $pdo->prepare('SELECT slug FROM organizations WHERE id = ?');
        $find->execute([$organizationId]);
        $slug = $find->fetchColumn();
        if (is_string($slug) && $slug !== '') return $slug;

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $name), '-'));
        $base = $base === '' ? 'empresa' : mb_substr($base, 0, 170);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $candidate = $attempt === 0 ? $base : $base . '-' . $organizationId;
            try {
                $update = $pdo->prepare("UPDATE organizations SET slug = ? WHERE id = ? AND (slug IS NULL OR slug = '')");
                $update->execute([$candidate, $organizationId]);
                if ($update->rowCount() === 1) return $candidate;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') throw $exception;
            }
            $find->execute([$organizationId]);
            $stored = $find->fetchColumn();
            if (is_string($stored) && $stored !== '') return $stored;
        }
        throw new \RuntimeException('Não foi possível definir o endereço da empresa.');
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
