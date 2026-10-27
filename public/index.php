
<?php

use MovesCode\Router\Router;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$router = new Router('https://realizze.lab');

$theme = require BASE_PATH . '/container/web/realizze/v1/routes.php';

$theme['routes']($router);

if (!$router->dispatch()) {
    http_response_code($router->error() ?? 500);

    echo 'Erro: ' . ($router->error() ?? 500);
}
