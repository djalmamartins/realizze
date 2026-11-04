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
        $site = \Moves\Services\CmsSite::class;
        $render = static function (string $page, array $data = []) use ($view): void {
            echo $view->render('pages/' . $page, $data);
        };
        // Metadados administrados no CMS valem para todos os templates da rota.
        $renderRoute = static function (string $template, string $path) use ($view, $render, $site): void {
            \Moves\Services\CmsAnalytics::record($path);
            $view->share(['seo' => $site::routeMeta($path)]);
            $render($template);
        };
        $renderCmsPage = static function (array $page) use ($view, $render, $site): void {
            \Moves\Services\CmsAnalytics::record('/' . $page['slug']);
            $view->share(['seo' => $site::autoMeta($page, $page['title'], $page['excerpt'], $page['content'], '/' . $page['slug'], $page['image_path'])]);
            $render('cms-page', ['page' => $page]);
        };
        $pages = [
            '/' => ['home', 'web.home'],
            '/sobre' => ['about', 'web.about'],
            '/solucoes' => ['solutions', 'web.solutions'],
            '/contato' => ['contact', 'web.contact'],
            '/termos' => ['terms', 'web.terms'],
            '/privacidade' => ['privacy', 'web.privacy'],
        ];
        foreach ($pages as $path => [$template, $name]) {
            $router->get($path, static function () use ($renderRoute, $renderCmsPage, $site, $template, $path): void {
                // Página publicada no CMS substitui o texto do template; sem ela, nada muda.
                $slug = ltrim($path, '/');
                $page = in_array($slug, \Moves\Services\CmsContent::TEMPLATE_PAGES, true) ? $site::publishedPage($slug) : null;
                if ($page !== null) {
                    $renderCmsPage($page);
                    return;
                }
                $renderRoute($template, $path);
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

        // O App tem host próprio: acesso e sessão ficam nele, para os links do menu funcionarem.
        $redirectToApp = static function (string $path, int $status = 302): bool {
            $host = (string) (getenv('ROUTE_APP_HOST') ?: '');
            if ($host === '') {
                return false;
            }
            header('Cache-Control: no-store');
            header('Location: https://' . $host . $path, true, $status);
            return true;
        };

        $router->get('/entrar', static function () use ($render, $redirectToApp): void {
            if ($redirectToApp('/entrar')) {
                return;
            }
            if (\Moves\Services\SessionAuth::current() !== null) {
                header('Location: /minha-conta', true, 302);
                return;
            }
            $render('login', ['loginError' => false]);
        }, 'web.login');

        $router->get('/cadastrar', static function () use ($render, $redirectToApp): void {
            if ($redirectToApp('/cadastrar')) {
                return;
            }
            header('Cache-Control: no-store');
            $render('app-register', ['registerError' => null, 'registerSuccess' => isset($_GET['enviado'])]);
        }, 'web.register');

        $router->post('/cadastrar', static function () use ($view, $redirectToApp): void {
            if ($redirectToApp('/cadastrar', 303)) {
                return;
            }
            \Moves\App\Register::handle($view);
        }, 'web.register.submit');

        $router->post('/entrar', static function () use ($render, $redirectToApp): void {
            if ($redirectToApp('/entrar', 303)) {
                return;
            }
            if ((new \Moves\App\Login())->submit()) {
                header('Location: /minha-conta', true, 302);
                return;
            }
            http_response_code(401);
            $render('login', ['loginError' => true]);
        }, 'web.login.submit');

        $router->get('/minha-conta', static function () use ($redirectToApp): void {
            if (!$redirectToApp('/')) {
                http_response_code(404);
            }
        }, 'web.account');

        $router->get('/reenviar-confirmacao', static function () use ($render): void {
            header('Cache-Control: no-store');
            $render('email-resend', ['requested' => false]);
        }, 'web.email.resend');

        $router->post('/reenviar-confirmacao', static function () use ($render): void {
            header('Cache-Control: no-store');
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                return;
            }
            (new \Moves\App\Register())->resend();
            $render('email-resend', ['requested' => true]);
        }, 'web.email.resend.submit');

        $router->get('/confirmar-email', static function () use ($render): void {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
            $token = $_GET['token'] ?? '';

            $render('email-confirmation', [
                'confirmed' => null,
                'token' => is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
                    ? $token
                    : '',
            ]);
        }, 'web.email.confirm');

        $router->post('/confirmar-email', static function () use ($render): void {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
            $confirmed = (new \Moves\App\EmailConfirmation())->confirm();

            $render('email-confirmation', [
                'confirmed' => $confirmed,
                'token' => '',
            ]);
        }, 'web.email.confirm.submit');

        $router->get('/planos', static function () use ($view, $render, $site): void {
            \Moves\Services\CmsAnalytics::record('/planos');
            $view->share(['seo' => $site::routeMeta('/planos')]);
            $render('plans', ['plans' => \Moves\Services\PlatformBilling::publicPlans()]);
        }, 'web.plans');

        $router->get('/blog', static function () use ($view, $render, $site): void {
            \Moves\Services\CmsAnalytics::record('/blog');
            $view->share(['seo' => $site::routeMeta('/blog')]);
            $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
            $render('blog', ['result' => $site::publishedPosts($page)]);
        }, 'web.blog');

        $router->get('/blog/{slug}', static function (array $params) use ($view, $render, $site): void {
            $post = $site::publishedPost((string) ($params['slug'] ?? ''));
            if ($post === null) {
                http_response_code(404);
                $render('not-found');
                return;
            }
            \Moves\Services\CmsAnalytics::record('/blog/' . $post['uri']);
            $view->share(['seo' => $site::autoMeta($post, $post['title'], $post['subtitle'], $post['content'], '/blog/' . $post['uri'], $post['cover'])]);
            $render('post', ['post' => $post]);
        }, 'web.post');

        $router->get('/suporte', static function () use ($view, $render, $site): void {
            \Moves\Services\CmsAnalytics::record('/suporte');
            $view->share(['seo' => $site::routeMeta('/suporte')]);
            $old = $_SESSION['support_contact_old'] ?? [];
            $flash = $_SESSION['support_contact_flash'] ?? null;
            unset($_SESSION['support_contact_old'], $_SESSION['support_contact_flash']);
            $search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 120)) : '';
            $folder = filter_var($_GET['pasta'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
            header('Cache-Control: no-store');
            $render('support', ['search' => $search, 'folder' => $folder, 'result' => $site::helpArticles($search, $page, 12, false, $folder), 'folders' => $site::publicHelpFolders(), 'settings' => $site::publicSettings(), 'old' => is_array($old) ? $old : [], 'flash' => $flash]);
        }, 'web.support');

        $router->post('/suporte/mensagem', static function (): void {
            header('Cache-Control: no-store');
            if (!\Moves\Services\Csrf::validate($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                echo 'Sessão expirada. Volte e tente novamente.';
                return;
            }
            // Campo oculto: robôs preenchem, pessoas não. Responde como sucesso e não grava.
            $isBot = ($_POST['website'] ?? '') !== '';
            try {
                if (!$isBot) {
                    if (!(new \Moves\Services\AuthRateLimiter())->hit('support-contact', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 5, 3600)) {
                        throw new InvalidArgumentException('Muitas mensagens em pouco tempo. Tente novamente mais tarde.');
                    }
                    \Moves\Services\ContactMessages::create($_POST);
                }
                $_SESSION['support_contact_flash'] = ['success', 'Mensagem enviada. Vamos responder pelo contato informado.'];
            } catch (InvalidArgumentException $error) {
                $_SESSION['support_contact_flash'] = ['error', $error->getMessage()];
                $_SESSION['support_contact_old'] = array_map(static fn ($v): string => is_string($v) ? mb_substr($v, 0, 4000) : '', array_intersect_key($_POST, array_flip(['name', 'phone', 'email', 'subject', 'message', 'whatsapp_ok'])));
            } catch (\Throwable $error) {
                error_log('Realizze site: mensagem de contato não registrada.');
                $_SESSION['support_contact_flash'] = ['error', 'Não foi possível enviar agora. Tente novamente em instantes.'];
            }
            header('Location: /suporte#mensagem', true, 303);
        }, 'web.support.message');

        $router->get('/suporte/artigo/{uri}', static function (array $params) use ($view, $render, $site): void {
            $article = $site::helpArticle('uri', (string) ($params['uri'] ?? ''));
            if ($article === null) {
                http_response_code(404);
                $render('not-found');
                return;
            }
            \Moves\Services\CmsAnalytics::record('/suporte/artigo/' . $article['uri']);
            \Moves\Services\CmsQuery::run('UPDATE posts SET views=views+1 WHERE id=?', [$article['id']]);
            $view->share(['seo' => $site::autoMeta($article, $article['title'], $article['subtitle'], $article['content'], '/suporte/artigo/' . $article['uri'], $article['cover'])]);
            $render('support-article', ['article' => $article]);
        }, 'web.support.article');

        $router->get('/sitemap.xml', static function () use ($site): void {
            header('Content-Type: application/xml; charset=utf-8');
            echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($site::sitemapUrls() as $url => $updated) {
                echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . ($updated ? '<lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' : '') . '</url>';
            }
            echo '</urlset>';
        }, 'web.sitemap');

        $router->get('/robots.txt', static function (): void {
            header('Content-Type: text/plain; charset=utf-8');
            echo "User-agent: *\nDisallow: /entrar\nDisallow: /minha-conta\nDisallow: /recuperar-senha\nDisallow: /redefinir-senha\nDisallow: /confirmar-email\nDisallow: /reenviar-confirmacao\nSitemap: " . rtrim((string) (getenv('APP_URL') ?: ''), '/') . "/sitemap.xml\n";
        }, 'web.robots');

        // Páginas criadas no CMS. Fica por último: rotas fixas têm precedência.
        $router->get('/{slug}', static function (array $params) use ($render, $renderCmsPage, $site): void {
            $page = $site::publishedPage((string) ($params['slug'] ?? ''));
            if ($page === null) {
                http_response_code(404);
                $render('not-found');
                return;
            }
            $renderCmsPage($page);
        }, 'web.cms.page');
    },
];
