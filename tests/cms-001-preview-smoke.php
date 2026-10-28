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
        'aria-current="page"', 'href="#cms-content"', 'id="cms-content"',
        'id="cms-page-title"', 'cms-preview-' . $slug] as $needle) {
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
    // CMS-A11Y-031: a prévia nunca deve permitir edição, submissão ou valores financeiros fictícios.
    if (preg_match('/<form\\b/i', $html) === 1 || preg_match('/R\\$\\s*[0-9]/u', $html) === 1) {
        throw new RuntimeException("CMS {$slug}: formulário ou valor fictício indevido na prévia.");
    }
    preg_match_all('/<(?:button|input|select|textarea)\\b[^>]*>/i', $html, $controls);
    foreach ($controls[0] as $control) {
        if (str_contains($control, 'id="cms-menu-toggle"')) {
            continue; // A navegação local é a única interação habilitada.
        }
        if (preg_match('/\\bdisabled\\b/i', $control) !== 1) {
            throw new RuntimeException("CMS {$slug}: controle administrativo habilitado: {$control}");
        }
    }
    preg_match_all('/\\bid="([^"]+)"/', $html, $ids);
    if (count($ids[1]) !== count(array_unique($ids[1]))) {
        throw new RuntimeException("CMS {$slug}: IDs duplicados no HTML.");
    }
    $pageIcons = ['pages' => 'pages', 'contents' => 'media', 'media' => 'media', 'users' => 'users'];
    if (isset($pageIcons[$slug]) && substr_count($html, 'data-cms-icon="' . $pageIcons[$slug] . '"') < 2) {
        throw new RuntimeException("CMS {$slug}: ícone SVG compartilhado ausente na página.");
    }
    echo "OK - CMS {$slug}: prévia renderizada e controles bloqueados" . PHP_EOL;
}
// CMS-UI-038: shared heading escapes all metadata and is rendered once per page.
$cmsHeading = [
    'eyebrow' => '<script>alert(1)</script>',
    'title' => '<img src=x onerror=alert(1)>',
    'description' => 'Descrição & detalhes',
    'badge' => 'Prévia',
];
ob_start();
require $root . '/components/page-heading.php';
$escapedHeading = (string) ob_get_clean();
if (str_contains($escapedHeading, '<script>')
    || str_contains($escapedHeading, '<img')
    || !str_contains($escapedHeading, '&lt;script&gt;')
    || !str_contains($escapedHeading, '&lt;img')
    || !str_contains($escapedHeading, 'Descrição &amp; detalhes')) {
    throw new RuntimeException('CMS-UI-038: componente de cabeçalho não escapou os metadados.');
}
echo 'OK - CMS-UI-038: cabeçalho reutilizado e metadados escapados.' . PHP_EOL;
// CMS-UI-041: shared empty tables must be accessible and never interpret metadata as HTML.
foreach (['plans' => ['Ofertas cadastradas', 4], 'logs' => ['Histórico de eventos administrativos', 5]] as $slug => [$caption, $columnCount]) {
    $html = $render($slug);
    if (!str_contains($html, '<caption class="cms-table-caption">' . $caption . '</caption>')
        || substr_count($html, '<th scope="col">') !== $columnCount
        || !str_contains($html, 'colspan="' . $columnCount . '"')) {
        throw new RuntimeException('CMS-UI-041: tabela vazia inacessível em ' . $slug);
    }
}
$cmsEmptyTable = [
    'caption' => '<script>alert(1)</script>',
    'columns' => ['Cliente & empresa', '<img src=x onerror=alert(1)>'],
    'title' => '<b>Falso</b>',
    'description' => 'Sem dados & sem operações',
];
ob_start();
require $root . '/components/empty-table.php';
$emptyTableHtml = (string) ob_get_clean();
if (str_contains($emptyTableHtml, '<script>')
    || str_contains($emptyTableHtml, '<img')
    || str_contains($emptyTableHtml, '<b>Falso</b>')
    || !str_contains($emptyTableHtml, '&lt;script&gt;')
    || !str_contains($emptyTableHtml, '&lt;img')
    || !str_contains($emptyTableHtml, 'Cliente &amp; empresa')
    || !str_contains($emptyTableHtml, 'colspan="2"')) {
    throw new RuntimeException('CMS-UI-041: dados da tabela não escapados corretamente.');
}
echo 'OK - CMS-UI-041: tabelas vazias acessíveis, compartilhadas e com HTML escapado.' . PHP_EOL;
$routes = file_get_contents($root . '/routes.php');
if (!is_string($routes)
    || !str_contains($routes, "getenv('REALIZZE_CMS_LOCAL_PREVIEW')")
    || !str_contains($routes, "getenv('APP_ENV')")
    || !str_contains($routes, 'REMOTE_ADDR')
    || !str_contains($routes, 'REALIZZE_CMS_PREVIEW_ALLOWED_IPS')
    || !str_contains($routes, 'components/preview-access.php')
    || !str_contains($routes, 'http_response_code(503)')) {
    throw new RuntimeException('Bloqueio de prévia local do CMS não identificado (checagem estática).');
}
echo 'CMS-001: renderização e bloqueio estático verificados. Não substitui teste HTTP de autorização.' . PHP_EOL;
