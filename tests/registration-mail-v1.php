<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RegistrationFlow;
use Moves\Services\VerificationMailer;
use MovesCode\Model\Connection;

class FakeVerificationMailer extends VerificationMailer
{
    public array $messages = [];
    public bool $fail = false;

    public function send(
        string $email,
        string $firstName,
        string $token
    ): void {
        if ($this->fail) {
            throw new RuntimeException('Falha SMTP simulada.');
        }

        $this->messages[] = [
            'email' => $email,
            'name' => $firstName,
            'token_valid' => preg_match('/^[a-f0-9]{64}$/D', $token) === 1,
        ];
    }
}

$pdo = Connection::get();
$flow = new RegistrationFlow();
$tests = [];
$userIds = [];

try {
    $mailer = new FakeVerificationMailer();
    $email = 'mail-' . bin2hex(random_bytes(8)) . '@example.invalid';

    $result = $flow->registerAndSend(
        'Teste',
        'Email',
        $email,
        'SenhaSegura#123',
        true,
        true,
        '127.0.0.1',
        'Realizze-Test',
        $mailer
    );

    $userIds[] = (int) $result['user_id'];

    $tests['Cadastro concluído'] = $result['user_id'] > 0;
    $tests['Status pendente'] = $result['status'] === 'registered';
    $tests['Envio solicitado uma vez'] = count($mailer->messages) === 1;
    $tests['Token válido entregue ao mailer'] =
        $mailer->messages[0]['token_valid'] ?? false;
    $tests['Token não exposto no retorno'] =
        !array_key_exists('verification_token', $result);

    $failedMailer = new FakeVerificationMailer();
    $failedMailer->fail = true;

    $failedEmail = 'failure-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $smtpFailureCaught = false;

    try {
        $flow->registerAndSend(
            'Teste',
            'Falha',
            $failedEmail,
            'SenhaSegura#123',
            true,
            true,
            '127.0.0.1',
            'Realizze-Test',
            $failedMailer
        );
    } catch (RuntimeException $exception) {
        $smtpFailureCaught =
            $exception->getMessage() === 'Falha SMTP simulada.';
    }

    $statement = $pdo->prepare(
        'SELECT id, status FROM users WHERE email = ? LIMIT 1'
    );
    $statement->execute([$failedEmail]);
    $failedUser = $statement->fetch(PDO::FETCH_ASSOC);

    if ($failedUser) {
        $userIds[] = (int) $failedUser['id'];
    }

    $tests['Falha SMTP identificada'] = $smtpFailureCaught;
    $tests['Cadastro preservado após falha'] =
        $failedUser !== false && $failedUser['status'] === 'registered';

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
    // Exclusão restrita aos usuários criados por este teste.
    foreach ($userIds as $userId) {
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')
                ->execute([$userId]);

            $pdo->prepare('DELETE FROM user_legal_acceptances WHERE user_id = ?')
                ->execute([$userId]);

            $pdo->prepare('DELETE FROM users WHERE id = ?')
                ->execute([$userId]);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    echo "Limpeza dos dados de teste concluída.\n";
}
