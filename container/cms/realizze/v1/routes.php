<?php
declare(strict_types=1);

use MovesCode\Router\Router;

return [
    'type' => 'cms',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        // Preview only on an explicitly allowed local client IP. Never trust X-Forwarded-For.
        // Docker gateways can be added individually via REALIZZE_CMS_PREVIEW_ALLOWED_IPS.
        $extraIps = array_filter(
            array_map('trim', explode(',', (string) (getenv('REALIZZE_CMS_PREVIEW_ALLOWED_IPS') ?: ''))),
            static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false
        );
        $allowedIps = array_merge(['127.0.0.1', '::1'], $extraIps);
        $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $previewEnabled = getenv('APP_ENV') === 'local'
            && getenv('REALIZZE_CMS_LOCAL_PREVIEW') === '1'
            && in_array($clientIp, $allowedIps, true);

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
            $router->get($path, static function () use ($section, $previewEnabled): void {
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
