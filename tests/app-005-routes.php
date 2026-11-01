<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Organizations;
use MovesCode\Model\Connection;

final class AppRouteClient
{
    private \CurlHandle $curl;
    public function __construct()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
    }
    /** @return array{status:int,body:string,location:string} */
    public function request(string $path, ?array $post = null): array
    {
        curl_setopt_array($this->curl, [CURLOPT_URL => 'https://app.realizze.lab' . $path, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15, CURLOPT_HEADER => true, CURLOPT_HTTPGET => $post === null]);
        if ($post !== null) { curl_setopt($this->curl, CURLOPT_POST, true); curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $raw = curl_exec($this->curl);
        if (!is_string($raw)) throw new RuntimeException(curl_error($this->curl));
        $headerSize = (int) curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headerSize);
        preg_match('/^location:\s*(\S+)/mi', $headers, $match);
        return ['status' => (int) curl_getinfo($this->curl, CURLINFO_HTTP_CODE), 'body' => substr($raw, $headerSize), 'location' => $match[1] ?? ''];
    }
}

$pdo = Connection::get();
$email = 'app-routes-' . bin2hex(random_bytes(5)) . '@example.invalid';
$password = 'Rotas-' . bin2hex(random_bytes(8));
$userId = $organizationId = null;
$failed = 0;
$check = static function (bool $ok, string $label) use (&$failed): void { echo ($ok ? 'OK' : 'FALHOU') . ' - ' . $label . PHP_EOL; if (!$ok) $failed++; };
try {
    $pdo->prepare("INSERT INTO users (first_name,last_name,email,password,status) VALUES ('Rotas','App',?,?, 'active')")
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $organizationId = Organizations::create($userId, 'Empresa Rotas ' . substr(hash('sha256', $email), 0, 6), '');
    $slug = Organizations::slugFor($organizationId, 'Empresa Rotas');

    $client = new AppRouteClient();
    $login = $client->request('/entrar');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $login['body'], $token);
    $logged = $client->request('/entrar', ['email' => $email, 'password' => $password, 'csrf_token' => $token[1] ?? '']);
    $check($logged['status'] === 302 && $logged['location'] === '/p', 'Login permanece no App e aponta para /p');

    $personal = ['', '/receber', '/pagar', '/movimentacoes', '/cartoes', '/relatorios', '/planejamento', '/metas', '/categorias', '/configuracoes'];
    foreach ($personal as $path) { $response = $client->request('/p' . $path); $check($response['status'] === 200, 'Rota PF /p' . $path); }
    $company = ['', '/receber', '/pagar', '/movimentacoes', '/clientes', '/fornecedores', '/cartoes', '/relatorios', '/planejamento', '/metas', '/categorias', '/equipe', '/empresa'];
    foreach ($company as $path) { $response = $client->request('/e/' . $slug . $path); $check($response['status'] === 200, 'Rota PJ /e/' . $slug . $path); }

    $forbidden = $client->request('/minha-conta/contexto', ['csrf_token' => 'invalido', 'context_type' => 'personal']);
    $check($forbidden['status'] === 403 && str_contains($forbidden['body'], 'Acesso não autorizado'), '403 personalizado preserva status');
    $missing = $client->request('/rota-inexistente');
    $check($missing['status'] === 404 && str_contains($missing['body'], 'Página não encontrada'), '404 personalizado preserva status');
    $other = $client->request('/e/empresa-sem-acesso/clientes');
    $check($other['status'] === 404, 'Slug empresarial não contorna autorização');
    $failure = $client->request('/__teste/erro-500');
    $check($failure['status'] === 500 && str_contains($failure['body'], 'Não foi possível concluir') && !str_contains($failure['body'], 'RuntimeException'), '500 controlado não expõe detalhes');
} finally {
    if ($organizationId !== null) $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$organizationId]);
    if ($userId !== null) {
        foreach (['auth_tokens', 'user_legal_acceptances'] as $table) $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
if ($failed > 0) exit(1);
echo "APP-005: ROTAS E ERROS APROVADOS.\n";
