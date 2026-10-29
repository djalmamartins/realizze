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
foreach (['pages' => ['Páginas cadastradas', 4], 'users' => ['Usuários administrativos', 4], 'plans' => ['Ofertas cadastradas', 4], 'logs' => ['Histórico de eventos administrativos', 5], 'contents' => ['Conteúdos editoriais', 5], 'media' => ['Arquivos da biblioteca', 4]] as $slug => [$caption, $columnCount]) {
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
echo 'OK - CMS-UI-047: tabelas de páginas, usuários, planos, logs, conteúdos e mídia compartilhadas, acessíveis e escapadas.' . PHP_EOL;
// CMS-A11Y-045: table scrolling must be keyboard reachable, named and escaped.
foreach (['pages', 'users', 'plans', 'logs', 'contents', 'media'] as $slug) {
    $html = $render($slug);
    if (!str_contains($html, 'class="cms-table-wrap" role="region" tabindex="0" aria-label="')) {
        throw new RuntimeException("CMS-A11Y-045: tabela de {$slug} sem região acessível.");
    }
}
if (!str_contains($emptyTableHtml, 'aria-label="&lt;script&gt;alert(1)&lt;/script&gt;"')
    || !str_contains($emptyTableHtml, 'tabindex="0"')) {
    throw new RuntimeException('CMS-A11Y-045: nome acessível da tabela não escapado.');
}
$css = file_get_contents($root . '/assets/css/responsive-fixes.css');
if (!is_string($css) || !str_contains($css, '.cms-table-wrap:focus-visible')) {
    throw new RuntimeException('CMS-A11Y-045: foco visível da região de tabela ausente.');
}
echo 'OK - CMS-A11Y-045: tabelas navegáveis pelo teclado, foco visível e nome escapado.' . PHP_EOL;
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


// CMS-UI-048: settings preview covers identity, SEO and governance without writes.
$settingsPreview = $render('settings');
foreach (['cms-settings-identity-title', 'cms-settings-editorial-title',
    'cms-settings-governance-title', 'Auditoria e privacidade',
    'cms-settings-description', 'cms-settings-indexing', 'Configurações bloqueadas'] as $needle) {
    if (!str_contains($settingsPreview, $needle)) {
        throw new RuntimeException('CMS-UI-048: configuração administrativa incompleta: ' . $needle);
    }
}
if (preg_match('/<form\\b/i', $settingsPreview) === 1) {
    throw new RuntimeException('CMS-UI-048: formulário administrativo habilitado.');
}
echo 'OK - CMS-UI-048: identidade, SEO e governança em prévia protegida.' . PHP_EOL;

// CMS-A11Y-049: the disabled administrative state must remain visible on mobile.
$cmsMobilePreview = $render('overview');
$cmsMobileCss = file_get_contents($root . '/assets/css/responsive-fixes.css');
$cmsMobileJs = file_get_contents($root . '/assets/js/cms.js');
if (!str_contains($cmsMobilePreview, 'class="cms-preview-status">Prévia local · Operações administrativas indisponíveis</span>')
    || !is_string($cmsMobileCss) || !str_contains($cmsMobileCss, '.cms-topbar .cms-preview-status')
    || !is_string($cmsMobileJs) || !str_contains($cmsMobileJs, "document.addEventListener('focusin'")) {
    throw new RuntimeException('CMS-A11Y-049: status móvel ou fechamento por foco ausente.');
}
echo 'OK - CMS-A11Y-049: status da prévia visível e navegação móvel com saída por teclado.' . PHP_EOL;

// CMS-A11Y-058: without JavaScript the navigation remains visible and the toggle is hidden.
if (!str_contains($cmsMobileCss, '.cms-sidebar.cms-js .cms-menu-toggle{display:inline-flex}')
    || !str_contains($cmsMobileCss, '.cms-sidebar.cms-js .cms-nav{display:none}')
    || !str_contains($cmsMobilePreview, 'id="cms-menu-toggle"')) {
    throw new RuntimeException('CMS-A11Y-058: navegação progressiva sem JavaScript incorreta.');
}
echo 'OK - CMS-A11Y-058: menu visível sem JS, botão móvel somente com JS.' . PHP_EOL;

// CMS-UI-057: shared read-only workflows in pages, contents and media.
foreach (['pages' => 'Fluxo previsto para páginas', 'contents' => 'Fluxo editorial previsto',
    'media' => 'Fluxo seguro previsto para arquivos',
    'users' => 'Fluxo previsto de acesso administrativo',
    'plans' => 'Fluxo previsto de planos e assinaturas',
    'logs' => 'Fluxo previsto de auditoria administrativa',
    'settings' => 'Fluxo previsto de alterações de configuração'] as $slug => $heading) {
    $html = $render($slug);
    if (!str_contains($html, $heading)
        || substr_count($html, '<ol class="cms-workflow-steps">') !== 1
        || substr_count($html, 'class="cms-workflow-state">Pendente</span>') !== 4
        || !str_contains($html, 'Nenhuma etapa foi executada.')) {
        throw new RuntimeException("CMS-UI-057: fluxo visual incompleto em {$slug}");
    }
}
$cmsWorkflow = [
    'title' => '<script>unsafe</script>',
    'steps' => [['title' => '<img src=x onerror=alert(1)>', 'description' => 'Texto & dados']],
];
ob_start();
require $root . '/components/workflow-preview.php';
$workflowHtml = (string) ob_get_clean();
if (str_contains($workflowHtml, '<script>') || str_contains($workflowHtml, '<img')
    || !str_contains($workflowHtml, '&lt;script&gt;')
    || !str_contains($workflowHtml, '&lt;img')
    || !str_contains($workflowHtml, 'Texto &amp; dados')) {
    throw new RuntimeException('CMS-UI-057: fluxo não escapou os metadados.');
}
echo 'OK - CMS-UI-059: sete fluxos de prévia do CMS sem ações administrativas.' . PHP_EOL;
