<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RegistrationFlow;
use Moves\Services\EmailVerification;
use Moves\Services\PasswordReset;
use Moves\Services\Authentication;
use MovesCode\Model\Connection;

function checkReset(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo 'OK - ' . $message . PHP_EOL;
}

$pdo = Connection::get();
$email = 'auth003-' . bin2hex(random_bytes(8)) . '@example.invalid';
$oldPassword = 'AntigaSenha@12345';
$newPassword = 'NovaSenha@987654';
$userId = null;
try {
    $registered = (new RegistrationFlow())->register('Teste', 'Reset', $email, $oldPassword, true, true);
    $userId = (int) $registered['user_id'];
    $reset = new PasswordReset();

    checkReset($reset->issue($email) === null, 'Conta não confirmada não recebe token');
    checkReset((new EmailVerification())->confirm($registered['verification_token']), 'Conta ativada');
    checkReset($reset->issue('nao-existe-' . $email) === null, 'E-mail inexistente não recebe token');

    $before = (new Authentication())->attempt($email, $oldPassword);
    checkReset($before !== null, 'Senha original autentica');

    $token = $reset->issue($email);
    checkReset(is_string($token) && strlen($token) === 64, 'Token gerado');
    checkReset($reset->valid($token), 'Token válido');
    checkReset(!$reset->valid('invalido'), 'Token malformado rejeitado');
    checkReset(!$reset->consume($token, 'curta'), 'Senha curta rejeitada');

    $second = $reset->issue($email);
    checkReset(!$reset->valid($token) && $reset->valid($second), 'Novo token invalida o anterior');
    checkReset($reset->consume($second, $newPassword), 'Senha redefinida');
    checkReset(!$reset->consume($second, $oldPassword), 'Token não reutilizável');
    checkReset((new Authentication())->attempt($email, $oldPassword) === null, 'Senha antiga rejeitada');
    $after = (new Authentication())->attempt($email, $newPassword);
    checkReset($after !== null, 'Nova senha autentica');
    checkReset($after['auth_version'] > $before['auth_version'], 'Sessões anteriores invalidadas por versão');
    echo 'TESTE AUTH-003: APROVADO' . PHP_EOL;
} finally {
    if ($userId !== null) {
        foreach (['auth_tokens', 'user_legal_acceptances'] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND email = ?');
        $stmt->execute([$userId, $email]);
        echo 'Dados temporários removidos.' . PHP_EOL;
    }
}
