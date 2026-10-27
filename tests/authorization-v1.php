<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Authorization;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$authorization = new Authorization();

$pdo->beginTransaction();

try {
    $userId = $pdo->query(
        'SELECT id FROM users ORDER BY id LIMIT 1'
    )->fetchColumn();

    if ($userId === false) {
        throw new RuntimeException('Nenhum usuário disponível para o teste.');
    }

    $userId = (int) $userId;

    $insertOrganization = $pdo->prepare(
        'INSERT INTO organizations (owner_user_id, name)
         VALUES (?, ?)'
    );

    $insertOrganization->execute([$userId, 'Teste Autorização A']);
    $organizationA = (int) $pdo->lastInsertId();

    $insertOrganization->execute([$userId, 'Teste Autorização B']);
    $organizationB = (int) $pdo->lastInsertId();

    $insertRole = $pdo->prepare(
        "INSERT INTO organization_roles
         (organization_id, role_key, name)
         VALUES (?, 'admin', 'Administrador')"
    );

    $insertRole->execute([$organizationA]);
    $roleId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO organization_members
         (organization_id, user_id, role_id, joined_at)
         VALUES (?, ?, ?, NOW())'
    )->execute([$organizationA, $userId, $roleId]);

    $pdo->prepare(
        'INSERT INTO organization_role_permissions
         (role_id, permission_key)
         VALUES (?, ?)'
    )->execute([$roleId, 'organization.view']);

    $tests = [
        'Permissão válida' => [
            $authorization->can($userId, $organizationA, 'organization.view'),
            true
        ],
        'Permissão inexistente' => [
            $authorization->can($userId, $organizationA, 'organization.delete'),
            false
        ],
        'Outra organização' => [
            $authorization->can($userId, $organizationB, 'organization.view'),
            false
        ],
        'Usuário inválido' => [
            $authorization->can(0, $organizationA, 'organization.view'),
            false
        ],
    ];

    $failed = 0;

    foreach ($tests as $name => [$actual, $expected]) {
        $passed = $actual === $expected;

        echo ($passed ? 'OK' : 'FALHOU') . " - {$name}\n";

        if (!$passed) {
            $failed++;
        }
    }

    if ($failed > 0) {
        throw new RuntimeException("Falharam {$failed} testes.");
    }

    echo "Todos os testes passaram.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "ROLLBACK executado.\n";
    }
}
