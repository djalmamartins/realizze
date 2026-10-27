<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RegistrationFlow;
use Moves\Services\EmailVerification;
use MovesCode\Model\Connection;

function authCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo 'OK - ' . $message . PHP_EOL;
}

function authRequest(string $url, ?array $post = null): array
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, $post === null);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException(curl_error($ch));
    }
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $body];
}

$pdo = Connection::get();
$email = 'auth-002-' . bin2hex(random_bytes(8)) . '@example.invalid';
$password = 'Teste@123456789';
$id = null;
try {
    $result = (new RegistrationFlow())->register('Teste', 'Login', $email, $password, true, true);
    $id = (int) $result['user_id'];
    $base = 'https://www.realizze.lab';

    [$status] = authRequest($base . '/minha-conta');
    authCheck($status === 302, 'Área privada exige autenticação');

    [$status, $body] = authRequest($base . '/entrar');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $matches);
    $csrf = $matches[1] ?? '';
    authCheck($status === 200 && strlen($csrf) === 64, 'Formulário de login possui CSRF');

    [$status] = authRequest($base . '/entrar', ['email' => $email, 'password' => $password, 'csrf_token' => $csrf]);
    authCheck($status === 401, 'Conta não confirmada não entra');

    authCheck((new EmailVerification())->confirm($result['verification_token']), 'Conta ativada para o teste');

    [$status] = authRequest($base . '/entrar', ['email' => $email, 'password' => 'senha-errada', 'csrf_token' => $csrf]);
    authCheck($status === 401, 'Senha incorreta bloqueada');

    [$status] = authRequest($base . '/entrar', ['email' => $email, 'password' => $password, 'csrf_token' => 'invalido']);
    authCheck($status === 401, 'CSRF inválido bloqueado');

    [$status] = authRequest($base . '/entrar', ['email' => $email, 'password' => $password, 'csrf_token' => $csrf]);
    authCheck($status === 302, 'Login válido redireciona');

    [$status, $body] = authRequest($base . '/minha-conta');
    authCheck($status === 200 && str_contains($body, 'Bem-vindo, Teste'), 'Área privada acessível');

    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $matches);
    $logoutCsrf = $matches[1] ?? '';
    [$status] = authRequest($base . '/sair', ['csrf_token' => 'invalido']);
    authCheck($status === 403, 'Logout exige CSRF válido');

    [$status] = authRequest($base . '/sair', ['csrf_token' => $logoutCsrf]);
    authCheck($status === 302, 'Logout redireciona');

    [$status] = authRequest($base . '/minha-conta');
    authCheck($status === 302, 'Sessão encerrada bloqueia área privada');

    echo 'TESTE AUTH-002: APROVADO' . PHP_EOL;
} finally {
    if ($id !== null) {
        foreach (['auth_tokens', 'user_legal_acceptances'] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->execute([$id]);
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND email = ?');
        $stmt->execute([$id, $email]);
    }
}
