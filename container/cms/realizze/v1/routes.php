<?php
declare(strict_types=1);

use MovesCode\Router\Router;

return [
    'type' => 'cms',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        // Rotas reais para as telas, mas SEM acesso administrativo até RBAC/CSRF.
        $pages = [
            '/' => ['overview', 'cms.index'],
            '/paginas' => ['pages', 'cms.pages'],
            '/conteudos' => ['contents', 'cms.contents'],
            '/midia' => ['media', 'cms.media'],
            '/usuarios' => ['users', 'cms.users'],
            '/planos' => ['plans', 'cms.plans'],
            '/logs' => ['logs', 'cms.logs'],
            '/configuracoes' => ['settings', 'cms.settings'],
        ];
        foreach ($pages as $path => [$section, $name]) {
            $router->get($path, static function () use ($section): void {
                http_response_code(503);
                header('Cache-Control: no-store, private');
                header('X-Robots-Tag: noindex, nofollow');
                header('Retry-After: 3600');
                $cmsSection = $section;
                require __DIR__ . '/default.php';
            }, $name);
        }
    },
];
