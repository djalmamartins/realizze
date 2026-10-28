<?php
declare(strict_types=1);

use MovesCode\Router\Router;

return [
    'type' => 'cms',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        // Prévia estritamente local, sem confiar em cabeçalhos de proxy.
        $previewAccess = require __DIR__ . '/components/preview-access.php';
        // Rotas reais para as telas, mas SEM acesso administrativo, dados ou mutações.
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
            $router->get($path, static function () use ($section, $previewAccess): void {
                // Reavaliar a cada requisição; nunca confiar no IP da inicialização do Router.
                $previewEnabled = $previewAccess(
                    (string) (getenv('APP_ENV') ?: ''),
                    (string) (getenv('REALIZZE_CMS_LOCAL_PREVIEW') ?: ''),
                    (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string) (getenv('REALIZZE_CMS_PREVIEW_ALLOWED_IPS') ?: '')
                );
                header('Cache-Control: no-store, private');
                header('X-Robots-Tag: noindex, nofollow');
                if (!$previewEnabled) {
                    http_response_code(503);
                    header('Retry-After: 3600');
                    echo 'CMS indisponível.';
                    return;
                }
                $cmsSection = $section;
                require __DIR__ . '/default.php';
            }, $name);
        }
    },
];
