<?php
declare(strict_types=1);

/** Rotas de suporte do App. Autenticação e portão de prévia iguais às demais rotas privadas. */
use Moves\Services\AccessPolicy;
use MovesCode\Router\Router;

return static function (Router $router, callable $requirePreview): void {
    $allowed = static fn (): bool => $requirePreview() && AccessPolicy::requireAccess('app');
    $page = static function (string $file, array $vars = []) use ($allowed): void {
        if (!$allowed()) {
            return;
        }
        extract($vars, EXTR_SKIP);
        require dirname(__DIR__) . '/pages/' . $file . '.php';
    };
    $router->get('/suporte', static fn () => $page('suporte'), 'app.support');
    $router->get('/suporte/novo', static fn () => $page('suporte-novo'), 'app.support.new');
    $router->get('/suporte/anexo/{id}', static function (array $params) use ($allowed): void {
        if ($allowed()) {
            (new \Moves\App\SupportActions())->download((int) ($params['id'] ?? 0));
        }
    }, 'app.support.attachment');
    $router->post('/suporte/abrir', static function () use ($allowed): void {
        if ($allowed()) {
            (new \Moves\App\SupportActions())->handle('open');
        }
    }, 'app.support.open');
    $router->post('/suporte/notificacoes/lidas', static function () use ($allowed): void {
        if ($allowed()) {
            (new \Moves\App\SupportActions())->handle('read-all');
        }
    }, 'app.support.read');
    $router->get('/suporte/{protocol}', static fn (array $params) => $page('suporte-chamado', ['protocol' => (string) ($params['protocol'] ?? '')]), 'app.support.ticket');
    foreach (['reply' => 'responder', 'resolve' => 'resolver', 'confirm' => 'confirmar', 'reopen' => 'reabrir', 'rate' => 'avaliar'] as $action => $slug) {
        $router->post('/suporte/{protocol}/' . $slug, static function (array $params) use ($allowed, $action): void {
            if ($allowed()) {
                (new \Moves\App\SupportActions())->handle($action, (string) ($params['protocol'] ?? ''));
            }
        }, 'app.support.' . $action);
    }
};
