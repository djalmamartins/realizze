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

        $renderContextDashboard = static function (string $context, string $section = 'overview') use ($renderDashboard): void {
            $organization = \Moves\Services\OrganizationContext::current();
            $isOrganization = $organization !== null;
            if (($context === 'pj') !== $isOrganization) {
                header('Cache-Control: no-store');
                header('Location: ' . ($isOrganization ? '/pj/minha-empresa' : '/pf/pessoal'), true, 302);
                return;
            }
            if (in_array($section, ['contatos', 'clientes', 'fornecedores', 'equipe', 'empresa'], true) && !$isOrganization) {
                http_response_code(404);
                return;
            }
            $_GET['pagina'] = $section;
            if ($section === 'clientes') $_GET['papel'] = 'customer';
            if ($section === 'fornecedores') $_GET['papel'] = 'supplier';
            $renderDashboard();
        };

        $router->get('/', $renderDashboard, 'app.index');
        $router->get('/minha-conta', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
            $organization = \Moves\Services\OrganizationContext::current();
            $base = $organization === null ? '/pf/pessoal' : '/pj/minha-empresa';
            $page = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'overview';
            $slugs = ['overview' => '', 'receber' => 'receber', 'pagar' => 'pagar', 'movimentacoes' => 'movimentacoes', 'contas' => 'cartoes', 'relatorios' => 'relatorios', 'planejamento' => 'planejamento', 'metas' => 'metas', 'categorias' => 'categorias', 'contatos' => 'contatos', 'assistente' => 'assistente', 'configuracoes' => 'configuracoes', 'empresarial' => 'empresarial'];
            $slug = $slugs[$page] ?? '';
            $query = $_GET;
            unset($query['pagina']);
            header('Cache-Control: no-store');
            header('Location: ' . $base . ($slug === '' ? '' : '/' . $slug) . ($query === [] ? '' : '?' . http_build_query($query)), true, 302);
        }, 'app.account');

        $semantic = ['' => 'overview', 'notificacoes' => 'notificacoes', 'integracoes' => 'integracoes', 'perfil' => 'perfil', 'receber' => 'receber', 'pagar' => 'pagar', 'movimentacoes' => 'movimentacoes', 'cartoes' => 'contas', 'relatorios' => 'relatorios', 'planejamento' => 'planejamento', 'metas' => 'metas', 'categorias' => 'categorias', 'assistente' => 'assistente', 'configuracoes' => 'configuracoes', 'empresarial' => 'empresarial'];
        foreach (['pf' => '/pf/pessoal', 'pj' => '/pj/minha-empresa'] as $context => $base) {
            foreach ($semantic as $slug => $section) {
                $router->get($base . ($slug === '' ? '' : '/' . $slug), static fn () => $renderContextDashboard($context, $section), 'app.' . $context . '.' . $section);
            }
        }
        foreach (['contatos', 'clientes', 'fornecedores', 'equipe', 'empresa'] as $section) {
            $router->get('/pj/minha-empresa/' . $section, static fn () => $renderContextDashboard('pj', $section), 'app.pj.' . $section);
        }

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
            header('Location: ' . ($type === 'personal' ? '/pf/pessoal' : '/pj/minha-empresa'), true, 303);
        }, 'app.context.switch');

        $router->get('/minha-conta/financeiro/exportar', static function () use ($requirePreview): void {
            if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            (new \Moves\App\FinanceActions())->export();
        }, 'app.finance.export');

        foreach (['create' => 'lancamento', 'settle' => 'baixa', 'delete' => 'excluir', 'account' => 'conta', 'transfer' => 'transferencia', 'company' => 'empresa', 'category' => 'categoria', 'category_delete' => 'categoria-excluir', 'budget' => 'orcamento', 'goal' => 'meta', 'goal_add' => 'meta-valor', 'goal_delete' => 'meta-excluir'] as $action => $slug) {
            $router->post('/minha-conta/financeiro/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                    return;
                }
                (new \Moves\App\FinanceActions())->handle($action);
            }, 'app.finance.' . $action);
        }

        foreach (['create' => 'recebimento', 'update' => 'recebimento-editar', 'settle' => 'recebimento-baixa', 'cancel' => 'recebimento-cancelar'] as $action => $slug) {
            $router->post('/minha-conta/financeiro/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) {
                    return;
                }
                (new \Moves\App\ReceivableActions())->handle($action);
            }, 'app.receivables.' . $action);
        }

        foreach (['create' => 'salvar', 'update' => 'atualizar', 'status' => 'status'] as $action => $slug) {
            $router->post('/minha-conta/contatos/' . $slug, static function () use ($requirePreview, $action): void {
                if (!$requirePreview() || !\Moves\Services\AccessPolicy::requireAccess('app')) return;
                (new \Moves\App\ContactActions())->handle($action);
            }, 'app.contacts.' . $action);
        }


        (require __DIR__ . '/components/support-routes.php')($router, $requirePreview);

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
