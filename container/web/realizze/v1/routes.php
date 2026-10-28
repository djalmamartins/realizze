<?php
use MovesCode\Router\Router;
use MovesCode\View\Engine;

return [
    'type' => 'web',
    'name' => 'realizze',
    'version' => 'v1',
    'controller' => null,
    'routes' => static function (Router $router): void {
        $themePath = dirname(__FILE__);
        $view = new Engine($themePath);
        $view->addFolder('components', $themePath . '/components');
        $render = static function (string $page, array $data = []) use ($view): void {
            echo $view->render('pages/' . $page, $data);
        };
        $pages = [
            '/' => ['home', 'web.home'],
            '/sobre' => ['about', 'web.about'],
            '/solucoes' => ['solutions', 'web.solutions'],
            '/blog' => ['blog', 'web.blog'],
            '/contato' => ['contact', 'web.contact'],
            '/cadastrar' => ['register', 'web.register'],
            '/termos' => ['terms', 'web.terms'],
            '/privacidade' => ['privacy', 'web.privacy'],
        ];
        foreach ($pages as $path => [$template, $name]) {
            $router->get($path, static function () use ($render, $template): void {
                $render($template);
            }, $name);
        }
        $router->get('/recuperar-senha', static function () use ($render): void {
            header('Cache-Control: no-store');
            $render('forgot', ['requested' => false]);
        }, 'web.password.forgot');

        $router->post('/recuperar-senha', static function () use ($render): void {
            header('Cache-Control: no-store');
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            $email = $_POST['email'] ?? '';
            if (is_string($email) && strlen($email) <= 254) {
                try {
                    $limiter = new \Moves\Services\AuthRateLimiter();
                    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
                    $ipAllowed = $limiter->hit('reset-ip', $ip, 15, 1800);
                    $emailAllowed = $limiter->hit('reset-account', strtolower(trim($email)), 3, 1800);
                    if ($ipAllowed && $emailAllowed) {
                        $reset = new \Moves\Services\PasswordReset();
                        $token = $reset->issue($email);
                        if ($token !== null) {
                            (new \Moves\Services\PasswordResetMailer())->send($email, $token);
                        }
                    } else {
                        error_log('Realizze auth: password reset rate limited.');
                    }
                } catch (\Throwable $exception) {
                    error_log('Realizze: falha ao solicitar recuperação de senha.');
                }
            }
            $render('forgot', ['requested' => true]);
        }, 'web.password.forgot.submit');

        $router->get('/redefinir-senha', static function () use ($render): void {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
            $token = $_GET['token'] ?? '';
            $token = is_string($token) ? $token : '';
            $render('password-reset', [
                'token' => $token,
                'tokenValid' => (new \Moves\Services\PasswordReset())->valid($token),
                'resetDone' => false,
                'resetError' => false,
            ]);
        }, 'web.password.reset');

        $router->post('/redefinir-senha', static function () use ($render): void {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmation = $_POST['password_confirmation'] ?? '';
            $token = is_string($token) ? $token : '';
            $done = false;
            if (is_string($password) && is_string($confirmation) &&
                hash_equals($password, $confirmation)) {
                try {
                    $done = (new \Moves\Services\PasswordReset())->consume($token, $password);
                } catch (\Throwable $exception) {
                    error_log('Realizze: falha ao redefinir senha.');
                }
            }
            if (!$done) {
                http_response_code(422);
            }
            $render('password-reset', [
                'token' => $token,
                'tokenValid' => !$done && (new \Moves\Services\PasswordReset())->valid($token),
                'resetDone' => $done,
                'resetError' => !$done,
            ]);
        }, 'web.password.reset.submit');

        $router->get('/entrar', static function () use ($render): void {
            if (\Moves\Services\SessionAuth::current() !== null) {
                header('Location: /minha-conta', true, 302);
                return;
            }
            $render('login', ['loginError' => false]);
        }, 'web.login');

        $router->post('/entrar', static function () use ($render): void {
            if ((new \Moves\App\Login())->submit()) {
                header('Location: /minha-conta', true, 302);
                return;
            }
            http_response_code(401);
            $render('login', ['loginError' => true]);
        }, 'web.login.submit');

        // Prévia protegida do onboarding; sem persistência ou operações bancárias.
        $router->get('/configuracao-inicial', static function () use ($render): void {
            if (!\\Moves\\Services\\AccessPolicy::requireAccess('app')) {
                return;
            }
            header('Cache-Control: no-store');
            $render('onboarding');
        }, 'app.onboarding.preview');

        $router->get('/minha-conta', static function (): void {
            if (!\Moves\Services\AccessPolicy::requireAccess('app')) {
                return;
            }
            require BASE_PATH . '/container/app/realizze/v1/pages/dashboard.php';
        }, 'app.account');

        // POST + CSRF: contexto empresarial é vinculado à sessão e revalidado no banco.
        $router->post('/minha-conta/contexto', static function (): void {
            if (!\Moves\Services\AccessPolicy::requireAccess('app')) {
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

        $router->post('/sair', static function (): void {
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            \Moves\Services\SessionAuth::signOut();
            header('Location: /entrar', true, 302);
        }, 'web.logout');

        $router->get('/confirmar-email', static function () use ($render): void {
            $token = $_GET['token'] ?? '';

            $render('email-confirmation', [
                'confirmed' => null,
                'token' => is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
                    ? $token
                    : '',
            ]);
        }, 'web.email.confirm');

        $router->post('/confirmar-email', static function () use ($render): void {
            $confirmed = (new \Moves\App\EmailConfirmation())->confirm();

            $render('email-confirmation', [
                'confirmed' => $confirmed,
                'token' => '',
            ]);
        }, 'web.email.confirm.submit');

        $router->get('/blog/{slug}', static function (array $params) use ($render): void {
            $posts = require __DIR__ . '/components/posts-data.php';
            $slug = $params['slug'] ?? '';
            if (!isset($posts[$slug])) {
                http_response_code(404);
                $render('not-found');
                return;
            }
            $render('post', ['post' => $posts[$slug]]);
        }, 'web.post');
    },
];
