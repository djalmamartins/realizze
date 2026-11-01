<?php
declare(strict_types=1);

use Moves\App\CmsActions;
use Moves\Services\CmsAuth;
use Moves\Services\Csrf;
use MovesCode\Router\Router;
use MovesCode\View\Engine;

return [
    'type' => 'cms',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        // Portão de ambiente antes de qualquer tela; não substitui login nem permissões.
        $previewAccess = require __DIR__ . '/components/preview-access.php';
        $gate = static function () use ($previewAccess): bool {
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex, nofollow');
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            $open = $previewAccess(
                (string) (getenv('APP_ENV') ?: ''),
                (string) (getenv('REALIZZE_CMS_LOCAL_PREVIEW') ?: ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) (getenv('REALIZZE_CMS_PREVIEW_ALLOWED_IPS') ?: '')
            ) || (getenv('APP_ENV') !== 'local' && getenv('REALIZZE_CMS_ENABLED') === '1');
            if (!$open) {
                http_response_code(503);
                header('Retry-After: 3600');
                echo 'CMS indisponível.';
            }
            return $open;
        };
        $view = new Engine(__DIR__);
        $view->addFolder('components', __DIR__ . '/components');
        $flash = static function (): ?array {
            $value = $_SESSION['cms_flash'] ?? null;
            unset($_SESSION['cms_flash']);
            return is_array($value) ? $value : null;
        };
        $render = static function (string $page, array $data = []) use ($view, $flash): void {
            $view->share(['flash' => $flash()] + $data);
            echo $view->render('pages/' . $page, $data);
        };
        $guard = static function (?string $permission) use ($gate, $view): ?array {
            if (!$gate()) {
                return null;
            }
            $admin = CmsAuth::current();
            if ($admin === null) {
                header('Location: /entrar', true, 302);
                return null;
            }
            if ($permission !== null && !CmsAuth::can($admin, $permission)) {
                http_response_code(403);
                $view->share(['admin' => $admin, 'title' => 'Acesso negado', 'section' => '', 'group' => '', 'params' => [], 'flash' => null]);
                echo $view->render('pages/forbidden');
                return null;
            }
            return $admin;
        };

        // caminho => [template, permissão, título, grupo do menu, item do menu]
        $pages = [
            '/' => ['dashboard', 'dashboard.view', 'Dashboard', 'Visão geral', 'dashboard'],
            '/paginas' => ['pages', 'pages.manage', 'Páginas', 'Site', 'pages'],
            '/paginas/novo' => ['page-form', 'pages.manage', 'Nova página', 'Site', 'pages'],
            '/paginas/{id}' => ['page-form', 'pages.manage', 'Editar página', 'Site', 'pages'],
            '/paginas/{id}/previa' => ['page-preview', 'pages.manage', 'Prévia da página', 'Site', 'pages'],
            '/conteudos' => ['contents', 'content.manage', 'Conteúdos', 'Site', 'contents'],
            '/conteudos/novo' => ['content-form', 'content.manage', 'Novo conteúdo', 'Site', 'contents'],
            '/conteudos/categorias' => ['content-categories', 'content.manage', 'Categorias de conteúdo', 'Site', 'contents'],
            '/conteudos/faq' => ['faq', 'content.manage', 'Perguntas frequentes', 'Site', 'contents'],
            '/conteudos/{id}' => ['content-form', 'content.manage', 'Editar conteúdo', 'Site', 'contents'],
            '/midia' => ['media', 'media.manage', 'Mídia', 'Site', 'media'],
            '/menus' => ['menus', 'menus.manage', 'Menus', 'Site', 'menus'],
            '/seo' => ['seo', 'seo.manage', 'SEO', 'Site', 'seo'],
            '/usuarios' => ['users', 'users.view', 'Usuários', 'Plataforma', 'users'],
            '/usuarios/{id}' => ['user-detail', 'users.view', 'Usuário', 'Plataforma', 'users'],
            '/empresas' => ['companies', 'companies.view', 'Empresas', 'Plataforma', 'companies'],
            '/empresas/{id}' => ['company-detail', 'companies.view', 'Empresa', 'Plataforma', 'companies'],
            '/planos' => ['plans', 'plans.view', 'Planos e preços', 'Plataforma', 'plans'],
            '/planos/novo' => ['plan-form', 'plans.manage', 'Novo plano', 'Plataforma', 'plans'],
            '/planos/{id}' => ['plan-form', 'plans.view', 'Plano', 'Plataforma', 'plans'],
            '/assinaturas' => ['subscriptions', 'subscriptions.view', 'Assinaturas', 'Plataforma', 'subscriptions'],
            '/assinaturas/{id}' => ['subscription-detail', 'subscriptions.view', 'Assinatura', 'Plataforma', 'subscriptions'],
            '/cobrancas' => ['charges', 'charges.view', 'Cobranças', 'Plataforma', 'charges'],
            '/cobrancas/{id}' => ['charge-detail', 'charges.view', 'Cobrança', 'Plataforma', 'charges'],
            '/chamados' => ['tickets', 'tickets.view', 'Chamados', 'Atendimento', 'tickets'],
            '/chamados/novo' => ['ticket-new', 'tickets.reply', 'Novo chamado', 'Atendimento', 'tickets'],
            '/chamados/fila' => ['tickets-queue', 'tickets.view', 'Fila de atendimento', 'Atendimento', 'queue'],
            '/chamados/categorias' => ['ticket-categories', 'tickets.categories', 'Categorias de chamado', 'Atendimento', 'ticket-categories'],
            '/chamados/respostas' => ['quick-replies', 'tickets.categories', 'Respostas rápidas', 'Atendimento', 'quick-replies'],
            '/chamados/equipe' => ['ticket-team', 'tickets.view', 'Equipe de atendimento', 'Atendimento', 'ticket-team'],
            '/chamados/relatorios' => ['ticket-reports', 'tickets.reports', 'Relatórios de atendimento', 'Atendimento', 'ticket-reports'],
            '/chamados/notificacoes' => ['ticket-notifications', 'notifications.configure', 'Configurações de notificações', 'Atendimento', 'ticket-notifications'],
            '/chamados/{id}' => ['ticket-detail', 'tickets.view', 'Chamado', 'Atendimento', 'tickets'],
            '/ajuda' => ['help', null, 'Ajuda', '', ''],
            '/ajuda/{id}' => ['help-article', null, 'Ajuda', '', ''],
            '/notificacoes' => ['notifications', null, 'Notificações', '', ''],
            '/administradores' => ['admins', 'admins.manage', 'Administradores', 'Administração', 'admins'],
            '/permissoes' => ['permissions', 'permissions.manage', 'Permissões', 'Administração', 'permissions'],
            '/auditoria' => ['audit', 'audit.view', 'Auditoria', 'Administração', 'audit'],
            '/configuracoes' => ['settings', 'settings.manage', 'Configurações', 'Administração', 'settings'],
        ];
        foreach ($pages as $path => [$template, $permission, $title, $group, $section]) {
            $router->get($path, static function (array $params = []) use ($guard, $render, $template, $permission, $title, $group, $section): void {
                $admin = $guard($permission);
                if ($admin === null) {
                    return;
                }
                $id = isset($params['id']) ? filter_var($params['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
                if ($id === false) {
                    http_response_code(404);
                    echo 'Não encontrado.';
                    return;
                }
                $render($template, compact('admin', 'title', 'group', 'section', 'id'));
            });
        }

        $router->get('/entrar', static function () use ($gate, $render): void {
            if (!$gate()) {
                return;
            }
            if (CmsAuth::current() !== null) {
                header('Location: /', true, 302);
                return;
            }
            $render('login', ['loginError' => false]);
        }, 'cms.login');

        $router->post('/entrar', static function () use ($gate, $render): void {
            if (!$gate()) {
                return;
            }
            $email = $_POST['email'] ?? null;
            $password = $_POST['password'] ?? null;
            $admin = null;
            if (Csrf::validate($_POST['csrf_token'] ?? null) && is_string($email) && is_string($password) && strlen($email) <= 254 && strlen($password) <= 4096) {
                try {
                    $admin = CmsAuth::attempt($email, $password, (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
                } catch (\Throwable $error) {
                    error_log('Realizze CMS: falha interna no login.');
                }
            }
            if ($admin !== null) {
                header('Location: /', true, 303);
                return;
            }
            http_response_code(401);
            $render('login', ['loginError' => true]);
        }, 'cms.login.submit');

        $router->post('/sair', static function () use ($gate): void {
            if (!$gate()) {
                return;
            }
            if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            \Moves\Services\SessionAuth::signOut();
            header('Location: /entrar', true, 303);
        }, 'cms.logout');

        $router->get('/chamados/anexo/{id}', static function (array $params) use ($guard): void {
            if ($guard('tickets.view') === null) {
                return;
            }
            $attachment = \Moves\Services\Helpdesk::attachment((int) ($params['id'] ?? 0));
            if ($attachment === null) {
                http_response_code(404);
                return;
            }
            \Moves\Services\Helpdesk::streamAttachment($attachment);
        }, 'cms.ticket.attachment');

        $router->post('/acao/{name}', static function (array $params) use ($gate): void {
            if ($gate()) {
                (new CmsActions())->handle((string) ($params['name'] ?? ''));
            }
        }, 'cms.action');
    },
];
