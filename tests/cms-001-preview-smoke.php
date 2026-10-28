<?php
declare(strict_types=1);

/**
 * Smoke test de marcação do CMS em prévia local.
 * Não acessa banco, sessão, APIs administrativas nem dados reais.
 * Execute: php tests/cms-001-preview-smoke.php
 */
$root = dirname(__DIR__) . '/container/cms/realizze/v1';
$sections = [
    'overview' => ['Visão geral', '/'],
    'pages' => ['Páginas', '/paginas'],
    'contents' => ['Conteúdos', '/conteudos'],
    'media' => ['Mídia', '/midia'],
    'users' => ['Usuários', '/usuarios'],
    'plans' => ['Planos e assinaturas', '/planos'],
    'logs' => ['Logs e auditoria', '/logs'],
    'settings' => ['Configurações', '/configuracoes'],
];
$render = static function (string $cmsSection) use ($root): string {
    ob_start();
    try {
        require $root . '/default.php';
        return (string) ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
};
foreach ($sections as $slug => [$label, $path]) {
    $html = $render($slug);
    foreach (['lang="pt-BR"', 'id="cms-navigation"', 'id="cms-menu-toggle"',
        'aria-controls="cms-navigation"', 'new-logo-realizze.svg',
        'aria-current="page"', 'href="#cms-content"', 'id="cms-content"', 'cms-preview-' . $slug] as $needle) {
        if (!str_contains($html, $needle)) {
            throw new RuntimeException("CMS {$slug}: elemento ausente: {$needle}");
        }
    }
    if (substr_count($html, 'aria-current="page"') !== 1) {
        throw new RuntimeException("CMS {$slug}: navegação ativa incorreta");
    }
    if (!str_contains($html, 'href="' . $path . '"')) {
        throw new RuntimeException("CMS {$slug}: rota de navegação ausente");
    }
    if (!str_contains($html, $label)) {
        throw new RuntimeException("CMS {$slug}: título da seção ausente");
    }
    echo "OK - CMS {$slug}: prévia renderizada" . PHP_EOL;
}
$routes = file_get_contents($root . '/routes.php');
if (!is_string($routes)
    || !str_contains($routes, "getenv('REALIZZE_CMS_LOCAL_PREVIEW') === '1'")
    || !str_contains($routes, "getenv('APP_ENV') === 'local'")
    || !str_contains($routes, 'http_response_code(503)')) {
    throw new RuntimeException('Bloqueio de prévia local do CMS não identificado (checagem estática).');
}
echo 'CMS-001: renderização e bloqueio estático verificados. Não substitui teste HTTP de autorização.' . PHP_EOL;
