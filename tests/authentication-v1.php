<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Authentication;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$auth = new Authentication();
$pdo->beginTransaction();

try {
    $email = 'teste-auth-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $password = 'SenhaTemporaria#123';

    $statement = $pdo->prepare(
        'INSERT INTO users
         (first_name, last_name, email, password, status)
         VALUES (?, ?, ?, ?, ?)'
    );

    $statement->execute([
        'Teste',
        'Autenticacao',
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        'active',
    ]);

    $userId = (int) $pdo->lastInsertId();

    $valid = $auth->attempt($email, $password);

    $blockedStatuses = ['registered', 'inactive', 'blocked'];
    $statusTests = [];

    $updateStatus = $pdo->prepare(
        'UPDATE users SET status = ? WHERE id = ?'
    );

    foreach ($blockedStatuses as $status) {
        $updateStatus->execute([$status, $userId]);

        $statusTests["Status {$status} bloqueado"] =
            $auth->attempt($email, $password) === null;
    }

    $updateStatus->execute(['active', $userId]);

    $tests = [
        'Senha correta' => $valid !== null
            && $valid['id'] === $userId,
        'Senha incorreta' => $auth->attempt($email, 'senha-errada') === null,
        'Email inexistente' => $auth->attempt(
            'inexistente@example.invalid',
            $password
        ) === null,
        'Senha vazia' => $auth->attempt($email, '') === null,
        'Senha não exposta' => $valid !== null
            && !array_key_exists('password', $valid),
    ] + $statusTests;

    $failed = 0;

    foreach ($tests as $name => $passed) {
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
