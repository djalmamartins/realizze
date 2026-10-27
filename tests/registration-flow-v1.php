<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RegistrationFlow;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email = 'flow-' . bin2hex(random_bytes(8)) . '@example.invalid';
$userId = null;

try {
    $flow = new RegistrationFlow();

    $result = $flow->register(
        'Teste',
        'Integracao',
        $email,
        'SenhaSegura#123',
        true,
        true,
        '127.0.0.1',
        'Realizze-Test'
    );

    $userId = (int) $result['user_id'];

    $statement = $pdo->prepare(
        'SELECT status FROM users WHERE id = ?'
    );
    $statement->execute([$userId]);
    $status = $statement->fetchColumn();

    $statement = $pdo->prepare(
        'SELECT COUNT(DISTINCT document_type)
         FROM user_legal_acceptances
         WHERE user_id = ?'
    );
    $statement->execute([$userId]);
    $acceptances = (int) $statement->fetchColumn();

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM auth_tokens
         WHERE user_id = ?
           AND type = 'email_verification'
           AND used_at IS NULL
           AND expires_at > NOW()"
    );
    $statement->execute([$userId]);
    $tokens = (int) $statement->fetchColumn();

    $tests = [
        'Usuário criado' => $userId > 0,
        'Conta aguardando confirmação' => $status === 'registered',
        'Dois aceites registrados' => $acceptances === 2,
        'Token armazenado' => $tokens === 1,
        'Token retornado pelo serviço' =>
            preg_match('/^[a-f0-9]{64}$/D', $result['verification_token']) === 1,
    ];

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
    // O fluxo faz COMMIT próprio; removemos apenas os dados deste teste.
    if ($userId !== null) {
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')
                ->execute([$userId]);

            $pdo->prepare('DELETE FROM user_legal_acceptances WHERE user_id = ?')
                ->execute([$userId]);

            $pdo->prepare('DELETE FROM users WHERE id = ?')
                ->execute([$userId]);

            $pdo->commit();
            echo "Dados de teste removidos.\n";
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
