<?php
declare(strict_types=1);

/** SECURITY-001: fail closed on corrupted session state, without database access. */
require dirname(__DIR__) . '/source/Services/Csrf.php';

use Moves\Services\Csrf;

if (!session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão de teste.');
}

$check = static function (bool $passed, string $message): void {
    if (!$passed) {
        throw new RuntimeException('SECURITY-001: ' . $message);
    }
    echo 'OK - ' . $message . PHP_EOL;
};

unset($_SESSION['csrf_token']);
$token = Csrf::token();
$check(preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 'Token criptográfico de 32 bytes em hexadecimal.');
$check(Csrf::token() === $token && Csrf::validate($token), 'Token estável e válido durante a sessão.');
$check(!Csrf::validate(['csrf' => $token]) && !Csrf::validate(null) && !Csrf::validate(''), 'Entradas inválidas são rejeitadas.');

$_SESSION['csrf_token'] = ['corrompido'];
$check(!Csrf::validate($token), 'Token de sessão não textual é rejeitado sem exceção.');
$replacement = Csrf::token();
$check($replacement !== $token && Csrf::validate($replacement) && !Csrf::validate($token), 'Sessão corrompida recebe novo token e invalida o antigo.');

$_SESSION['csrf_token'] = 'fraco';
$check(!Csrf::validate('fraco'), 'Token legado fraco não é aceito.');
$check(Csrf::token() !== 'fraco' && Csrf::validate(Csrf::token()), 'Token fraco é regenerado com entropia adequada.');

unset($_SESSION['csrf_token']);
$check(!Csrf::validate($replacement), 'Token ausente é rejeitado.');
session_write_close();
$check(!Csrf::validate($replacement), 'Validação sem sessão ativa falha fechada.');
try {
    Csrf::token();
    throw new RuntimeException('SECURITY-001: geração fora da sessão deveria falhar.');
} catch (RuntimeException $error) {
    $check($error->getMessage() === 'Sessão não iniciada.', 'Geração sem sessão ativa é bloqueada.');
}
echo "SECURITY-001: CSRF robusto aprovado sem acesso a banco.\n";
