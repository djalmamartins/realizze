<?php

use MovesCode\Router\Router;
use Moves\Core\EnvironmentRouter;

// Inicialização do Realizze 2.0
require_once dirname(__DIR__) . '/source/Boot/app.php';

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


\Moves\Services\SystemLog::register();

// Resolver ambiente por hostname configurado, sem aceitar caminhos da requisição.
try {
    $selected = EnvironmentRouter::resolve(
        (string) ($_SERVER['HTTP_HOST'] ?? ''),
        EnvironmentRouter::configuration(),
        BASE_PATH
    );
} catch (InvalidArgumentException|RuntimeException $exception) {
    error_log('Realizze router: ' . $exception->getMessage());
    http_response_code(404);
    echo 'Ambiente não encontrado.';
    exit;
}

// Inicialização, registro e despacho formam uma única fronteira de erro.
// Assim, falhas inesperadas anteriores ao dispatch também recebem a resposta
// segura do ambiente selecionado, sem stack trace ou caminhos internos.
try {
    require_once dirname(__DIR__) . '/source/Boot/database.php';
    $router = new Router(
        getenv('APP_URL') ?: 'https://www.realizze.lab'
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

    if (!$router->dispatch()) {
        \Moves\Services\SystemLog::exception($router->exception());
        $status = $router->error() ?? 500;
        http_response_code($status);
        if (($selected['type'] ?? null) === 'app' && in_array($status, [403, 404, 500], true)) {
            \Moves\Services\AppError::render($status);
        } else {
            echo 'Erro: ' . $status;
        }
    }
} catch (Throwable $exception) {
    \Moves\Services\SystemLog::exception($exception);
    if (($selected['type'] ?? null) === 'app') {
        \Moves\Services\AppError::render(500);
    } else {
        http_response_code(500);
        echo 'Erro: 500';
    }
}
