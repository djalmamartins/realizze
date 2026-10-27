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
            '/entrar' => ['login', 'web.login'],
            '/cadastrar' => ['register', 'web.register'],
            '/recuperar-senha' => ['forgot', 'web.forgot'],
            '/termos' => ['terms', 'web.terms'],
            '/privacidade' => ['privacy', 'web.privacy'],
        ];
        foreach ($pages as $path => [$template, $name]) {
            $router->get($path, static function () use ($render, $template): void {
                $render($template);
            }, $name);
        }
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
