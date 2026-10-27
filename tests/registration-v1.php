<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Registration;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$registration = new Registration();
$email = 'teste-reg-' . bin2hex(random_bytes(8)) . '@example.invalid';
$password = 'SenhaSegura#123';

$pdo->beginTransaction();

try {
    $userId = $registration->register(
        'Teste',
        'Cadastro',
        $email,
        $password,
        true,
        true,
        '1.0',
        '1.0',
        '127.0.0.1',
        'Realizze-Test'
    );

    $statement = $pdo->prepare(
        'SELECT status, password FROM users WHERE id = ?'
    );
    $statement->execute([$userId]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare(
        'SELECT document_type, document_version
         FROM user_legal_acceptances
         WHERE user_id = ?'
    );
    $statement->execute([$userId]);
    $acceptances = $statement->fetchAll(PDO::FETCH_ASSOC);

    $documents = array_column(
        $acceptances,
        'document_version',
        'document_type'
    );

    $tests = [
        'Usuário criado' => $user !== false,
        'Status registered' => $user !== false
            && $user['status'] === 'registered',
        'Senha protegida' => $user !== false
            && $user['password'] !== $password
            && password_verify($password, $user['password']),
        'Termos registrados' =>
            ($documents['terms_of_use'] ?? null) === '1.0',
        'Privacidade registrada' =>
            ($documents['privacy_policy'] ?? null) === '1.0',
        'Transação externa preservada' => $pdo->inTransaction(),
    ];

    foreach ([
        ['Sem aceite dos termos', false, true],
        ['Sem aceite da privacidade', true, false],
    ] as [$name, $terms, $privacy]) {
        try {
            $registration->register(
                'Teste',
                'Bloqueado',
                'bloqueado-' . bin2hex(random_bytes(8)) . '@example.invalid',
                $password,
                $terms,
                $privacy,
                '1.0',
                '1.0'
            );

            $tests[$name] = false;
        } catch (InvalidArgumentException $exception) {
            $tests[$name] = true;
        }
    }

    try {
        $registration->register(
            'Teste',
            'Duplicado',
            $email,
            $password,
            true,
            true,
            '1.0',
            '1.0'
        );

        $tests['E-mail duplicado bloqueado'] = false;
    } catch (InvalidArgumentException $exception) {
        $tests['E-mail duplicado bloqueado'] = true;
    }

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
