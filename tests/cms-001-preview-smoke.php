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
foreach (['overview' => 'Preparação obrigatória para operar o CMS',
    'pages' => 'Fluxo previsto para páginas', 'contents' => 'Fluxo editorial previsto',
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
echo 'OK - CMS-UI-059: oito fluxos de prévia do CMS sem ações administrativas.' . PHP_EOL;

// CMS-A11Y-065: all shared inventory tables explain horizontal scrolling.
foreach (['pages', 'users', 'plans', 'logs', 'contents', 'media'] as $slug) {
    if (!str_contains($render($slug), 'class="cms-scroll-hint"')) {
        throw new RuntimeException('CMS-A11Y-065: orientação de rolagem ausente em ' . $slug);
    }
}
if (!str_contains($cmsMobileCss, '@media(max-width:720px){.cms-scroll-hint{display:block}}')
    || !str_contains($cmsMobileCss, '.cms-table-wrap:focus-visible>.cms-scroll-hint')) {
    throw new RuntimeException('CMS-A11Y-065: orientação de rolagem responsiva/foco ausente.');
}
echo 'OK - CMS-A11Y-065: tabelas administrativas com orientação de rolagem.' . PHP_EOL;

// CMS-UI-078: reusable semantic status lists in four administrative previews.
foreach (['users' => [1, 3], 'plans' => [1, 3], 'logs' => [1, 4], 'settings' => [2, 5]] as $slug => [$lists, $items]) {
    $html = $render($slug);
    if (substr_count($html, '<dl class="cms-status-list">') !== $lists
        || substr_count($html, '<div class="cms-setting-row">') !== $items
        || substr_count($html, '<dt>') !== $items
        || substr_count($html, '<dd>') !== $items) {
        throw new RuntimeException("CMS-UI-078: lista semântica incompleta em {$slug}");
    }
}
$cmsStatusRows = [['label' => '<script>ataque</script>', 'status' => '<img src=x onerror=alert(1)>']];
ob_start();
require $root . '/components/status-list.php';
$statusHtml = (string) ob_get_clean();
if (str_contains($statusHtml, '<script>') || str_contains($statusHtml, '<img')
    || !str_contains($statusHtml, '&lt;script&gt;ataque&lt;/script&gt;')
    || !str_contains($statusHtml, '&lt;img src=x onerror=alert(1)&gt;')) {
    throw new RuntimeException('CMS-UI-078: status não escapou HTML.');
}
$rejected = false;
$cmsStatusRows = [['label' => '', 'status' => 'Pendente']];
ob_start();
try {
    require $root . '/components/status-list.php';
} catch (InvalidArgumentException $expected) {
    $rejected = true;
} finally {
    ob_end_clean();
}
if (!$rejected || !str_contains($cmsMobileCss, '.cms-status-list dd')) {
    throw new RuntimeException('CMS-UI-078: validação ou CSS responsivo ausente.');
}
echo 'OK - CMS-UI-078: estados semânticos, reutilizáveis, escapados e responsivos.' . PHP_EOL;

// CMS-UI-080: four inventory screens share disabled, labelled, responsive filters.
foreach ([
    'pages' => ['cms-pages-search', 'cms-pages-filter-status', 'cms-pages-filter-model'],
    'contents' => ['cms-contents-search', 'cms-contents-type', 'cms-contents-status'],
    'media' => ['cms-media-search', 'cms-media-filter', 'cms-media-access'],
    'logs' => ['cms-audit-search', 'cms-audit-type', 'cms-audit-date', 'cms-audit-actor'],
] as $slug => $ids) {
    $html = $render($slug);
    if (substr_count($html, 'class="cms-preview-filters"') !== 1 ||
        !str_contains($html, 'Somente prévia · filtros bloqueados')) {
        throw new RuntimeException("CMS-UI-080: filtros compartilhados ausentes em {$slug}");
    }
    foreach ($ids as $id) {
        if (substr_count($html, 'id="' . $id . '"') !== 1 ||
            !str_contains($html, 'for="' . $id . '"') ||
            preg_match('/<(?:input|select)\\b[^>]*\\bid="' . preg_quote($id, '/') . '"[^>]*\\bdisabled\\b/i', $html) !== 1) {
            throw new RuntimeException("CMS-UI-080: filtro inacessível ou habilitado: {$id}");
        }
    }
}
$cmsPreviewFilters = [
    'title' => '<script>unsafe</script>',
    'description' => 'Texto & descrição',
    'fields' => [
        ['id' => 'cms-filter-safe', 'label' => '<img src=x onerror=alert(1)>', 'type' => 'search', 'placeholder' => '" autofocus onfocus="alert(1)'],
        ['id' => 'cms-filter-select', 'label' => 'Situação', 'type' => 'select', 'options' => ['<b>todas</b>']],
    ],
];
ob_start();
require $root . '/components/preview-filters.php';
$filterHtml = (string) ob_get_clean();
if (str_contains($filterHtml, '<script>') || str_contains($filterHtml, '<img') ||
    str_contains($filterHtml, '<b>todas</b>') ||
    !str_contains($filterHtml, '&lt;script&gt;unsafe&lt;/script&gt;') ||
    !str_contains($filterHtml, 'Texto &amp; descrição') ||
    !str_contains($filterHtml, '&lt;img src=x onerror=alert(1)&gt;') ||
    !str_contains($filterHtml, '&quot; autofocus onfocus=&quot;alert(1)') ||
    !str_contains($filterHtml, '&lt;b&gt;todas&lt;/b&gt;')) {
    throw new RuntimeException('CMS-UI-080: metadados de filtros sem escape.');
}
foreach ([
    [['id' => 'cms-repeat', 'label' => 'A', 'type' => 'search'], ['id' => 'cms-repeat', 'label' => 'B', 'type' => 'text']],
    [['id' => 'unsafe" onclick="alert(1)', 'label' => 'A', 'type' => 'search']],
    [['id' => 'cms-invalid', 'label' => 'A', 'type' => 'file']],
] as $invalidFields) {
    $cmsPreviewFilters = ['title' => 'Teste', 'description' => 'Teste', 'fields' => $invalidFields];
    $rejected = false;
    ob_start();
    try {
        require $root . '/components/preview-filters.php';
    } catch (InvalidArgumentException $expected) {
        $rejected = true;
    } finally {
        ob_end_clean();
    }
    if (!$rejected) {
        throw new RuntimeException('CMS-UI-080: configuração insegura de filtro aceita.');
    }
}
if (!str_contains($cmsMobileCss, '.cms-preview-filter-grid') ||
    !str_contains($cmsMobileCss, '@media(max-width:540px){.cms-preview-filters')) {
    throw new RuntimeException('CMS-UI-080: filtros sem layout responsivo.');
}
echo 'OK - CMS-UI-080: quatro inventários com filtros acessíveis, desabilitados e escapados.' . PHP_EOL;
