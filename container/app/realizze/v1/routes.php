<?php
declare(strict_types=1);

use MovesCode\Router\Router;
use MovesCode\View\Engine;

return [
    'type' => 'app',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        $previewEnabled = getenv('APP_ENV') === 'local'
            && getenv('REALIZZE_APP_LOCAL_PREVIEW') === '1';
        $webView = new Engine(BASE_PATH . '/container/web/realizze/v1');
        $webView->addFolder('components', BASE_PATH . '/container/web/realizze/v1/components');

        $unavailable = static function (): void {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Retry-After: 3600');
            require __DIR__ . '/default.php';
        };
        $requirePreview = static function () use ($previewEnabled, $unavailable): bool {
            if ($previewEnabled) {
                return true;
            }
            $unavailable();
            return false;
        };
        $renderLogin = static function (bool $loginError = false) use ($webView): void {
            echo $webView->render('pages/login', ['loginError' => $loginError]);
        };
        $renderDashboard = static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            require __DIR__ . '/pages/dashboard.php';
        };

        $router->get('/', $renderDashboard, 'app.index');
        $router->get('/minha-conta', $renderDashboard, 'app.account');

        $router->get('/entrar', static function () use ($requirePreview, $renderLogin): void {
            if (!$requirePreview()) {
                return;
            }
            if (\Moves\Services\SessionAuth::current() !== null) {
                header('Location: /', true, 302);
                return;
            }
            $renderLogin();
        }, 'app.login');

        $router->post('/entrar', static function () use ($requirePreview, $renderLogin): void {
            if (!$requirePreview()) {
                return;
            }
            if ((new \Moves\App\Login())->submit()) {
                header('Location: /', true, 302);
                return;
            }
            http_response_code(401);
            $renderLogin(true);
        }, 'app.login.submit');

        $router->post('/minha-conta/contexto', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            $type = $_POST['context_type'] ?? null;
            if ($type === 'personal') {
                $changed = \Moves\Services\OrganizationContext::selectPersonal($_POST['csrf_token']);
            } elseif ($type === 'organization') {
                $rawId = $_POST['organization_id'] ?? null;
                $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]) : false;
                if ($id === false) {
                    http_response_code(422);
                    return;
                }
                $changed = \Moves\Services\OrganizationContext::select($id, $_POST['csrf_token']);
            } else {
                http_response_code(422);
                return;
            }
            if (!$changed) {
                http_response_code(403);
                return;
            }
            header('Cache-Control: no-store');
            header('Location: /minha-conta?pagina=empresarial', true, 303);
        }, 'app.context.switch');

        $router->post('/sair', static function () use ($requirePreview): void {
            if (!$requirePreview()) {
                return;
            }
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            \Moves\Services\SessionAuth::signOut();
            header('Location: /entrar', true, 302);
        }, 'app.logout');
    },
];
