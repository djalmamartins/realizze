<?php
declare(strict_types=1);

use MovesCode\Router\Router;

return [
    'type' => 'erp',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        // Ambiente reservado: nenhuma funcionalidade privada é exposta nesta etapa.
        $router->get('/', static function (): void {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Retry-After: 3600');
            require __DIR__ . '/default.php';
        }, 'erp.index');
    },
];
