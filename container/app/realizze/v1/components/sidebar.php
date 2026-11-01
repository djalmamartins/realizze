<?php
/** Escapar textos e atributos dinâmicos; os ícones SVG são estáticos. */
$escapeSidebar = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
// Associate keys with their links before grouping; positional arrays remain supported.
// PF/PJ headings must not depend on the incoming item order.
// APP-SEC-088: HTML escaping does not neutralize executable URL schemes.
// Only same-origin root-relative paths are valid for this navigation.
$sidebarHref = static function (mixed $href) use ($escapeSidebar): string {
    if (!is_string($href) || !str_starts_with($href, '/')
        || str_starts_with($href, '//')
        || preg_match('/[\x00-\x20\x7F\\\\]/', $href)) {
        return '/minha-conta';
    }
    return $escapeSidebar($href);
};
$sidebarSectionKeys = array_keys($sections ?? []);
$sidebarGroups = ['personal' => [], 'enterprise' => []];
$contextEnabled = $contextEnabled ?? false;
$activeOrganization = $activeOrganization ?? null;
foreach ($items as $index => $item) {
    $itemSection = is_string($index) ? $index : ($sidebarSectionKeys[$index] ?? null);
    // No seletor de contexto, a área empresarial só aparece dentro de uma empresa.
    if ($contextEnabled && $itemSection === 'empresarial' && $activeOrganization === null) {
        continue;
    }
    $sidebarGroups[$itemSection === 'empresarial' ? 'enterprise' : 'personal'][] = [
        'section' => $itemSection,
        'item' => $item,
    ];
}
?>
<aside class="sidebar" id="app-sidebar" aria-label="Navegação principal">
<button type="button" class="sidebar-close" id="sidebar-close" aria-label="Fechar menu"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
<a href="<?= $sidebarHref(\Moves\Services\AppUrl::base($activeOrganization)) ?>" class="brand" aria-label="Realizze"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="152" height="38"></a>
<?php require __DIR__ . '/context-switch.php'; ?>
<nav class="nav" aria-label="Menu principal">
<?php if (!empty($navGroups)): ?>
<?php
$pendingSections = $pendingSections ?? [];
$navLink = static function (string $key) use ($items, $section, $sidebarHref, $icon, $escapeSidebar, $pendingSections): void {
    $item = $items[$key] ?? null;
    if ($item === null) return; ?>
<a href="<?= $sidebarHref($item[2]) ?>"<?= $section === $key ? ' class="active" aria-current="page"' : '' ?>><span class="symbol" aria-hidden="true"><?= $icon($item[0]) ?></span><?= $escapeSidebar($item[1]) ?><?php if (in_array($key, $pendingSections, true)): ?><span class="nav-soon" title="Em breve"><span class="sr-only">Em breve</span></span><?php endif; ?></a>
<?php };
$navLink('overview');
foreach ($navGroups as [$groupTitle, $groupIcon, $groupKeys]):
    $groupOpen = in_array($section, $groupKeys, true); ?>
<details class="nav-group"<?= $groupOpen ? ' open' : '' ?>>
<summary<?= $groupOpen ? ' class="is-active"' : '' ?>><span class="symbol" aria-hidden="true"><?= $icon($groupIcon) ?></span><?= $escapeSidebar($groupTitle) ?></summary>
<div class="nav-sub"><?php foreach ($groupKeys as $key) { $navLink($key); } ?></div>
</details>
<?php endforeach; ?>
<?php else: ?>
<?php foreach (['personal' => $activeOrganization ? 'Financeiro da empresa' : 'Finanças pessoais · PF', 'enterprise' => 'Gestão empresarial · PJ'] as $group => $heading): ?>
<?php if ($sidebarGroups[$group] === []) continue; ?>
<h2 class="nav-group-heading"><?= $heading ?></h2>
<?php
// Na empresa, Clientes, Fornecedores e Categorias ficam agrupados em Cadastros, logo após Movimentações.
$registerSections = $activeOrganization ? ['clientes', 'fornecedores', 'categorias'] : [];
$registerEntries = array_values(array_filter($sidebarGroups[$group], static fn (array $x): bool => in_array($x['section'], $registerSections, true)));
$registerOpen = in_array($section, $registerSections, true);
$renderLink = static function (array $entry) use ($section, $sidebarHref, $icon, $escapeSidebar): void {
    $item = $entry['item'];
    $itemSection = $entry['section'];
    ?>
<?php $item = $entry['item']; $itemSection = $entry['section']; ?>
<a href="<?= $sidebarHref($item[2] ?? null) ?>" <?= $section === $itemSection ? 'class="active" aria-current="page"' : '' ?>><span class="symbol" aria-hidden="true"><?= $icon($item[0]) ?></span><?= $escapeSidebar($item[1]) ?></a>
<?php
};
foreach ($sidebarGroups[$group] as $entry):
    if (in_array($entry['section'], $registerSections, true)) {
        continue;
    }
    $renderLink($entry);
    if ($entry['section'] === 'movimentacoes' && $registerEntries !== []): ?>
<details class="nav-group"<?= $registerOpen ? ' open' : '' ?>>
<summary<?= $registerOpen ? ' class="is-active"' : '' ?>><span class="symbol" aria-hidden="true"><?= $icon('building') ?></span>Cadastros</summary>
<div class="nav-sub"><?php foreach ($registerEntries as $sub) { $renderLink($sub); } ?></div>
</details>
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
</nav>
<div class="sidebar-bottom">
<div class="connect"><strong>Conexão bancária</strong><p>A importação de transações estará disponível após a integração segura.</p><button type="button" disabled title="Integração bancária indisponível">Em preparação</button></div>
<a class="profile profile-link" href="<?= $sidebarHref(\Moves\Services\AppUrl::section('perfil', $activeOrganization)) ?>"><span class="avatar" aria-hidden="true"><?= $escapeSidebar(require __DIR__ . '/profile-initial.php') ?></span><div><strong><?= $escapeSidebar($name ?? "") ?></strong><small>Minha conta</small></div></a>
<div class="sidebar-actions"><button type="button" id="theme-toggle" aria-label="Alternar tema"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z"/></svg></button><button type="button" data-demo="true" aria-label="Ajuda"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4.1 1.9c-.9.7-1.6 1.1-1.6 2.6"/><path d="M12 17h.01"/></svg></button></div>
<form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= $escapeSidebar($csrf ?? "") ?>"><button type="submit" style="border:0;background:transparent;width:100%;padding:10px"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:middle;margin-right:7px"><path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M14 16l4-4-4-4M18 12H9"/></svg>Sair</button></form>
</div>
</aside>
<?php if ($contextEnabled): ?>
<dialog class="finance-dialog" id="company-dialog" aria-labelledby="company-dialog-title">
  <form method="post" action="/minha-conta/financeiro/empresa" data-finance-form>
    <header><h2 id="company-dialog-title">Cadastrar empresa</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $escapeSidebar($csrf ?? '') ?>"><input type="hidden" name="back" value="overview">
    <label>Nome da empresa<input name="name" maxlength="180" required></label>
    <label>CNPJ<input name="cnpj" inputmode="numeric" maxlength="18" placeholder="Opcional: 00.000.000/0000-00"></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Cadastrar</button></footer>
  </form>
</dialog>
<?php endif; ?>
