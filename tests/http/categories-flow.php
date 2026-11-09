<?php
// FIN-028: Categorias por HTTP (PF e PJ): criar, aparecer na lista, excluir, isolamento e papel de consulta.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$orgId = (string) $pdo->query("SELECT id FROM organizations WHERE slug='missao-alfa-ltda'")->fetchColumn();
foreach (['PF' => ['/p/categorias', []], 'PJ' => ['/e/missao-alfa-ltda/categorias', ['context_organization_id' => $orgId]]] as $ctx => [$path, $extra]) {
    $name = "Cat $ctx " . bin2hex(random_bytes(3));
    $o->req('POST', '/minha-conta/financeiro/categoria', $extra + ['csrf_token' => $o->csrf($get($o, $path)), 'back' => 'categorias', 'name' => $name, 'category_kind' => 'expense', 'color' => '#336699']);
    $page = $get($o, $path);
    $id = (int) $pdo->query('SELECT id FROM finance_categories WHERE name = ' . $pdo->quote($name))->fetchColumn();
    $check($id > 0 && str_contains($page, $name), "$ctx: categoria criada aparece na lista");
    $other = $ctx === 'PF' ? '/e/missao-alfa-ltda/categorias' : '/p/categorias';
    $check(!str_contains($get($o, $other), $name), "$ctx: não aparece no outro contexto");
    $o->req('POST', '/minha-conta/financeiro/categoria-excluir', $extra + ['csrf_token' => $o->csrf($get($o, $path)), 'back' => 'categorias', 'id' => $id]);
    $check((int) $pdo->query("SELECT COUNT(*) FROM finance_categories WHERE id = $id")->fetchColumn() === 0, "$ctx: categoria excluída");
}
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$vp = $get($v, '/e/missao-alfa-ltda/categorias');
$r = $v->req('POST', '/minha-conta/financeiro/categoria', ['csrf_token' => $v->csrf($vp), 'context_organization_id' => $orgId, 'back' => 'categorias', 'name' => 'Invasora', 'category_kind' => 'expense']);
$check($r['code'] === 403 && (int) $pdo->query("SELECT COUNT(*) FROM finance_categories WHERE name = 'Invasora'")->fetchColumn() === 0, 'Visualizador não cria categoria na empresa');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
