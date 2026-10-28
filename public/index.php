<?php

use MovesCode\Router\Router;
use Moves\Core\EnvironmentRouter;

// Inicialização do Realizze 2.0
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


// Configuração do roteador
$router = new Router(
    getenv('APP_URL') ?: 'https://www.realizze.lab'
);

// Resolver ambiente por hostname configurado, sem aceitar caminhos da requisição.
try {
    $selected = EnvironmentRouter::resolve(
        (string) ($_SERVER['HTTP_HOST'] ?? ''),
        EnvironmentRouter::configuration(),
        BASE_PATH
    );
    $theme = require $selected['path'];
    if (!is_array($theme)
        || ($theme['type'] ?? null) !== $selected['type']
        || ($theme['name'] ?? null) !== $selected['theme']
        || ($theme['version'] ?? null) !== $selected['version']
        || !is_callable($theme['routes'] ?? null)) {
        throw new RuntimeException('Metadados de rotas incompatíveis.');
    }
    $theme['routes']($router);
} catch (InvalidArgumentException|RuntimeException $exception) {
    error_log('Realizze router: ' . $exception->getMessage());
    http_response_code(404);
    echo 'Ambiente não encontrado.';
    exit;
}

// Executar o roteador
if (!$router->dispatch()) {
    http_response_code($router->error() ?? 500);
    echo 'Erro: ' . ($router->error() ?? 500);
}