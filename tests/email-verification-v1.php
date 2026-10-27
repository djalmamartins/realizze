<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\EmailVerification;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$verification = new EmailVerification();
$pdo->beginTransaction();

try {
    $email = 'verify-' . bin2hex(random_bytes(8)) . '@example.invalid';

    $statement = $pdo->prepare(
        "INSERT INTO users
         (first_name, last_name, email, password, status)
         VALUES (?, ?, ?, ?, 'registered')"
    );

    $statement->execute([
        'Teste',
        'Verificacao',
        $email,
        password_hash('SenhaTemporaria#123', PASSWORD_DEFAULT),
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Sem aceites, a emissão do token deve ser bloqueada.
    $blockedWithoutConsent = false;

    try {
        $verification->issue($userId);
    } catch (RuntimeException $exception) {
        $blockedWithoutConsent = true;
    }

    // Registra somente os Termos de Uso.
    $acceptance = $pdo->prepare(
        "INSERT INTO user_legal_acceptances
         (user_id, document_type, document_version, accepted_at)
         VALUES (?, ?, ?, NOW())"
    );

    $acceptance->execute([
        $userId,
        'terms_of_use',
        'v1',
    ]);

    // Um único aceite também deve ser insuficiente.
    $blockedWithOneConsent = false;

    try {
        $verification->issue($userId);
    } catch (RuntimeException $exception) {
        $blockedWithOneConsent = true;
    }

    // Registra a Política de Privacidade.
    $acceptance->execute([
        $userId,
        'privacy_policy',
        'v1',
    ]);

    $token = $verification->issue($userId);

    // Simula a ausência dos aceites após a emissão do token.
    $pdo->prepare(
        "DELETE FROM user_legal_acceptances WHERE user_id = ?"
    )->execute([$userId]);

    $blockedConfirmationWithoutConsent = !$verification->confirm($token);

    // Restaura os aceites para testar a ativação normal.
    $acceptance->execute([$userId, 'terms_of_use', 'v1']);
    $acceptance->execute([$userId, 'privacy_policy', 'v1']);

    $tests = [
        'Confirmação sem aceites bloqueada' => $blockedConfirmationWithoutConsent,
        'Sem aceites bloqueado' => $blockedWithoutConsent,
        'Um aceite bloqueado' => $blockedWithOneConsent,
        'Token gerado' => preg_match('/^[a-f0-9]{64}$/D', $token) === 1,
        'Token inválido rejeitado' => !$verification->confirm('invalido'),
        'Token válido aceito' => $verification->confirm($token),
        'Token reutilizado rejeitado' => !$verification->confirm($token),
    ];

    $statement = $pdo->prepare(
        'SELECT status FROM users WHERE id = ?'
    );
    $statement->execute([$userId]);

    $tests['Usuário ativado'] =
        $statement->fetchColumn() === 'active';

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
