<?php
// APP-NAV-100: todo link do menu abre (200) para quem o vê; o menu esconde o que a rota recusa.
// Roda contra o servidor temporário ligado ao banco de teste (REALIZZE_TEST_HTTP). Só leitura: faz GET.
declare(strict_types=1);
require __DIR__ . '/client.php';

$failures = [];
$menuLinks = static function (string $html): array {
    if (!preg_match('/<nav class="nav"[^>]*>(.*?)<\/nav>/s', $html, $nav)) return [];
    preg_match_all('/<a href="([^"]+)"/', $nav[1], $m);
    return array_values(array_unique(array_map('html_entity_decode', $m[1])));
};
$check = static function (string $label, H $h, string $start, array $mustHave, array $mustNotHave) use ($menuLinks, &$failures): void {
    $page = $h->req('GET', $start);
    if ($page['code'] !== 200) { $failures[] = "$label: $start respondeu {$page['code']}"; return; }
    $links = $menuLinks($page['body']);
    if ($links === []) { $failures[] = "$label: menu vazio em $start"; return; }
    foreach ($mustHave as $path) if (!in_array($path, $links, true)) $failures[] = "$label: falta $path no menu";
    foreach ($mustNotHave as $path) if (in_array($path, $links, true)) $failures[] = "$label: $path não deveria estar no menu";
    foreach ($links as $link) {
        $r = $h->req('GET', $link);
        if ($r['code'] !== 200) { $failures[] = "$label: $link respondeu {$r['code']}"; continue; }
        if (preg_match('/(Warning|Notice|Deprecated|Fatal error)<\/b>|Uncaught|Stack trace/', $r['body'])) $failures[] = "$label: erro PHP em $link";
        if (str_contains($link, '/suporte')) continue;
        if (!preg_match('/aria-current="page"><span class="symbol"[^>]*>.*?<\/span>([^<]+)/s', $r['body'], $active)) { $failures[] = "$label: $link sem item ativo no menu"; continue; }
        // Título da página igual ao item do menu (Configurações abre Meu perfil / Configurações da empresa, nomes completos).
        preg_match('/<h1 id="app-page-title">([^<]+)/', $r['body'], $h1);
        $menuName = html_entity_decode(trim($active[1])); $title = html_entity_decode(trim($h1[1] ?? $menuName));
        if ($menuName !== 'Configurações' && $link !== '/p' && !preg_match('#^/e/[^/]+$#', $link) && $title !== $menuName) $failures[] = "$label: $link título \"$title\" difere do menu \"$menuName\"";
        // Mesmo menu em todas as telas: a navegação não muda conforme a página.
        if ($menuLinks($r['body']) !== $links) $failures[] = "$label: menu diferente em $link";
    }
    printf("%s: %d links verificados\n", $label, count($links));
};

$slug = getenv('REALIZZE_TEST_ORG') ?: 'missao-alfa-ltda';
$owner = new H();
if (!$owner->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login do dono falhou\n"); exit(1); }
$check('PF dono', $owner, '/p', ['/p/receber', '/p/pagar', '/p/movimentacoes', '/p/cartoes', '/p/faturas', '/p/conciliacao', '/p/categorias', '/p/relatorios', '/p/perfil', '/p/notificacoes'], ["/e/$slug/dre", '/p/configuracoes']);
$check('PJ dono', $owner, "/e/$slug", ["/e/$slug/dre", "/e/$slug/faturas", "/e/$slug/conciliacao", "/e/$slug/ordens-de-servico", "/e/$slug/obrigacoes", "/e/$slug/auditoria", "/e/$slug/permissoes"], ['/p/sair-das-dividas']);

$operational = new H();
if (!$operational->login('pj-operacional@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login do operacional falhou\n"); exit(1); }
// Operacional não tem reports.view, accounts.view, planning.view nem team.manage.
$check('PJ operacional', $operational, "/e/$slug", ["/e/$slug/receber", "/e/$slug/clientes"], ["/e/$slug/dre", "/e/$slug/relatorios", "/e/$slug/cartoes", "/e/$slug/metas", "/e/$slug/planejamento", "/e/$slug/auditoria"]);
$r = $owner->req('GET', '/p/configuracoes');
if ($r['code'] !== 302 || $r['location'] !== '/p/perfil') $failures[] = 'PF: /p/configuracoes deveria levar a /p/perfil';
// A rota continua recusando o que o menu esconde.
foreach (['/dre', '/relatorios', '/auditoria'] as $hidden) {
    $r = $operational->req('GET', "/e/$slug$hidden");
    if ($r['code'] !== 403) $failures[] = "PJ operacional: $hidden respondeu {$r['code']}, esperado 403";
}

if ($failures !== []) { echo "FALHAS:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "OK - APP-NAV-100: menus PF/PJ completos, coerentes com as permissões e sem links quebrados.\n";
