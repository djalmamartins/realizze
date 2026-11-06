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
    $check(trait_exists(\MovesCode\Router\RouterTrait::class), 'Instalação do Router contém RouterTrait');
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

    foreach (['/p/2026', '/p/2026/11', '/p/2026-11-10/2026-12-15', '/p/receber/2026', '/p/receber/2026/11', '/p/receber/2026-11-10/2026-12-15', '/p/pagar/2026', '/p/pagar/2026/11', '/p/pagar/2026-11-10/2026-12-15', '/p/movimentacoes/2026/11', '/p/planejamento/2026', '/p/planejamento/2026/11', '/p/relatorios/2026', '/p/categorias/2026/11', '/e/' . $slug . '/2026/11', '/e/' . $slug . '/receber/2026', '/e/' . $slug . '/fluxo-de-caixa/2026/11?visao=dia', '/e/' . $slug . '/fluxo-de-caixa/2026-11-10/2026-12-15?agrupamento=dia'] as $path) { $response = $client->request($path); $check($response['status'] === 200, 'Período ' . $path); }
    foreach (['/p/2026/13', '/p/1999', '/p/receber/2026-02-30/2026-03-01', '/p/receber/2026-12-15/2026-11-10', '/p/planejamento/2026-11-10/2026-12-15', '/p/metas/2026', '/p/fluxo-de-caixa/2026/11', '/e/' . $slug . '/fluxo-de-caixa/2026', '/p/qualquer', '/p/qualquer/coisa'] as $path) { $response = $client->request($path); $check($response['status'] === 404, 'Período inválido responde 404: ' . $path); }
    foreach (['/p/pagar?mes=2026-11&busca=luz' => '/p/pagar/2026/11?busca=luz', '/p?visao=anual&ano=2026' => '/p/2026', '/p/receber?inicio=2026-11-10&fim=2026-12-15&situacao=overdue' => '/p/receber/2026-11-10/2026-12-15?situacao=overdue', '/p/receber?inicio=2026-11-01&fim=2026-11-30' => '/p/receber/2026/11', '/minha-conta?pagina=pagar&mes=2026-11' => '/p/pagar/2026/11', '/minha-conta?pagina=sair-das-dividas' => '/p/sair-das-dividas', '/e/' . $slug . '/pagar?mes=2026-11' => '/e/' . $slug . '/pagar/2026/11'] as $path => $target) { $response = $client->request($path); $check($response['status'] === 302 && $response['location'] === $target, 'Compatibilidade ' . $path . ' → ' . $target . ' (' . $response['location'] . ')'); }
    $check(str_contains($client->request('/p/pagar/2026/11')['body'], 'href="/p/pagar/2026/12"'), 'Navegação do período sai em segmentos');
    $check(str_contains($client->request('/p/2026')['body'], 'href="/p/2027"'), 'Navegação anual sai em segmentos');
    $check(str_contains($client->request('/p/2026-11-10/2026-12-15')['body'], '10/11/2026 a 15/12/2026'), 'Visão geral aplica período personalizado');
    $check(!str_contains($client->request('/p/pagar/2026/11?mes=2020-01')['body'], 'Janeiro de 2020'), 'Segmento prevalece sobre query antiga');

    $forbidden = $client->request('/minha-conta/contexto', ['csrf_token' => 'invalido', 'context_type' => 'personal']);
    $check($forbidden['status'] === 403 && str_contains($forbidden['body'], 'Acesso não autorizado') && str_contains($forbidden['body'], 'data-app-error-status="403"'), '403 personalizado preserva status');
    $missing = $client->request('/rota-inexistente');
    $check($missing['status'] === 404 && str_contains($missing['body'], 'Página não encontrada') && str_contains($missing['body'], 'A página que você está procurando não existe ou foi movida.') && str_contains($missing['body'], 'data-app-error-status="404"'), '404 personalizado preserva status');
    $other = $client->request('/e/empresa-sem-acesso/clientes');
    $check($other['status'] === 404, 'Slug empresarial não contorna autorização');
    $failure = $client->request('/__teste/erro-500');
    $check($failure['status'] === 500 && str_contains($failure['body'], 'Não conseguimos carregar esta página') && str_contains($failure['body'], 'data-app-error-status="500"') && !str_contains($failure['body'], 'RuntimeException'), '500 controlado não expõe detalhes');
} finally {
    if ($organizationId !== null) $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$organizationId]);
    if ($userId !== null) {
        foreach (['auth_tokens', 'user_legal_acceptances'] as $table) $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
if ($failed > 0) exit(1);
echo "APP-005: ROTAS E ERROS APROVADOS.\n";
