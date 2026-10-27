<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RegistrationFlow;
use MovesCode\Model\Connection;

$pdo = Connection::get();

$email = 'test-confirm-' . bin2hex(random_bytes(8)) . '@example.invalid';
$userId = null;
$cookieFile = tempnam(sys_get_temp_dir(), 'realizze-cookie-');

function check(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $description);
    }

    echo 'OK - ' . $description . PHP_EOL;
}

function request(
    string $url,
    string $cookieFile,
    ?array $post = null
): array {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $html = curl_exec($ch);

    if ($html === false) {
        throw new RuntimeException('Erro HTTP: ' . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $html];
}

function userStatus(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    return (string) $stmt->fetchColumn();
}

try {
    $result = (new RegistrationFlow())->register(
        'Teste',
        'Confirmacao',
        $email,
        'Teste@123456789',
        true,
        true
    );

    $userId = (int) $result['user_id'];
    $token = $result['verification_token'];

    check(userStatus($pdo, $userId) === 'registered', 'Usuário inicialmente registrado');

    $url = 'https://www.realizze.lab/confirmar-email';

    [$status, $html] = request(
        $url . '?token=' . urlencode($token),
        $cookieFile
    );

    check($status === 200, 'Página GET acessível');
    check(userStatus($pdo, $userId) === 'registered', 'GET não ativa a conta');

    preg_match(
        '/name="csrf_token"\s+value="([^"]+)"/',
        $html,
        $matches
    );

    $csrf = $matches[1] ?? '';

    check(strlen($csrf) === 64, 'CSRF gerado no formulário');

    $cookies = is_file($cookieFile)
        ? file_get_contents($cookieFile)
        : '';

    echo 'DIAGNÓSTICO - Cookie de sessão: '
        . (str_contains($cookies, 'PHPSESSID')
            ? 'PRESENTE'
            : 'AUSENTE')
        . PHP_EOL;


    [$status, $html] = request($url, $cookieFile, [
        'token' => $token,
        'csrf_token' => 'invalido',
    ]);

    check(userStatus($pdo, $userId) === 'registered', 'CSRF inválido bloqueado');

    [$status, $html] = request($url, $cookieFile, [
        'token' => $token,
        'csrf_token' => $csrf,
    ]);

    check($status === 200, 'POST de confirmação acessível');

    $currentStatus = userStatus($pdo, $userId);

    echo 'DIAGNÓSTICO - Status após POST: '
        . $currentStatus . PHP_EOL;

    echo 'DIAGNÓSTICO - Resposta: '
        . (str_contains($html, 'E-mail confirmado!')
            ? 'SUCESSO'
            : (str_contains($html, 'Não foi possível confirmar o e-mail')
                ? 'RECUSADA'
                : 'DESCONHECIDA'))
        . PHP_EOL;

    if ($currentStatus !== 'active') {
        $direct = (new \Moves\Services\EmailVerification())->confirm($token);

        echo 'DIAGNÓSTICO - Confirmação direta: '
            . ($direct ? 'ACEITA' : 'RECUSADA')
            . PHP_EOL;

        echo 'DIAGNÓSTICO - Status após confirmação direta: '
            . userStatus($pdo, $userId)
            . PHP_EOL;
    }

    check($currentStatus === 'active', 'Conta ativada');

    [$status, $html] = request($url, $cookieFile, [
        'token' => $token,
        'csrf_token' => $csrf,
    ]);

    check(
        str_contains($html, 'Não foi possível confirmar o e-mail'),
        'Token não pode ser reutilizado'
    );

    echo PHP_EOL . 'TESTE COMPLETO: APROVADO' . PHP_EOL;

} finally {
    if ($userId !== null) {
        $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare('DELETE FROM user_legal_acceptances WHERE user_id = ?');
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND email = ?');
        $stmt->execute([$userId, $email]);

        echo 'Dados temporários removidos.' . PHP_EOL;
    }

    if (is_string($cookieFile) && file_exists($cookieFile)) {
        unlink($cookieFile);
    }
}
