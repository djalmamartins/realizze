<?php

use MovesCode\Router\Router;

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

// Carregar as rotas do tema Web
$theme = require BASE_PATH . '/container/web/realizze/v1/routes.php';

// Registrar as rotas
$theme['routes']($router);

// Executar o roteador
if (!$router->dispatch()) {
    http_response_code($router->error() ?? 500);
    echo 'Erro: ' . ($router->error() ?? 500);
}