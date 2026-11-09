<?php
// APP-FLT-103: Clientes e Fornecedores no padrão de filtros de Contas a Receber.
// Escreve no banco de teste: cria dois clientes com nome único (PJ e PF) e confere abas, busca, tipo e etiquetas.
declare(strict_types=1);
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$base = '/e/missao-alfa-ltda/clientes';
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$count = static fn (string $html, string $label): int => preg_match('/>' . $label . ' \((\d+)\)</u', $html, $m) ? (int) $m[1] : -1;
$tag = 'CLF' . bin2hex(random_bytes(3));
$before = $get($o, $base);
foreach (['company' => "$tag Empresa", 'individual' => "$tag Pessoa"] as $type => $name) {
    $o->req('POST', '/minha-conta/contatos/salvar', ['csrf_token' => $o->csrf($get($o, $base)), 'back' => 'clientes', 'person_type' => $type, 'legal_name' => $name, 'is_customer' => '1']);
}
$after = $get($o, $base);
$check(str_contains($after, 'id="contact-filters"') && str_contains($after, 'data-finance-open="contact-filters"'), 'Botão Filtros abre o modal');
$check($count($after, 'Todos') === $count($before, 'Todos') + 2 && $count($after, 'Ativos') === $count($before, 'Ativos') + 2, 'Abas contam os novos clientes');
$search = $get($o, "$base?busca=$tag");
$check(str_contains($search, "$tag Empresa") && str_contains($search, "$tag Pessoa") && str_contains($search, "Busca: $tag") && $count($search, 'Todos') === 2, 'Busca filtra, conta e mostra etiqueta');
$typed = $get($o, "$base?busca=$tag&tipo=individual");
$check(str_contains($typed, "$tag Pessoa") && !str_contains($typed, "$tag Empresa") && str_contains($typed, 'Tipo: Pessoa física'), 'Filtro por tipo funciona');
$inactive = $get($o, "$base?busca=$tag&situacao=inactive");
$check(!str_contains($inactive, "$tag Pessoa") && preg_match('/aria-current="true">Inativos/u', $inactive) === 1 && $count($inactive, 'Ativos') === 2, 'Aba Inativos filtra sem zerar as outras contagens');
$check(!str_contains($after, 'name="papel"'), 'Papel fixo na tela de Clientes (sem filtro de papel)');
$check(!preg_match('/(Warning|Notice|Fatal error)<\/b>/', $after . $search . $typed . $inactive), 'Sem erro PHP');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
