<?php
// APP-FLT-102: Ordens de serviço no padrão de filtros de Contas a Receber.
// Escreve no banco de teste: cria duas ordens com descrição única (prazos distintos) e confere abas, busca, período e etiquetas.
declare(strict_types=1);
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$base = '/e/missao-alfa-ltda/ordens-de-servico';
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$tag = 'OSF' . bin2hex(random_bytes(3));
$page = $get($o, $base);
preg_match('/name="customer_id"[^>]*>.*?<option value="(\d+)"/s', $page, $cm);
$customerId = (int) ($cm[1] ?? 0);
$count = static fn (string $html, string $label): int => preg_match('/>' . $label . ' \((\d+)\)</u', $html, $m) ? (int) $m[1] : -1;
$before = $page;
foreach (['2035-01-10' => 'janeiro', '2035-06-10' => 'junho'] as $due => $word) {
    $o->req('POST', '/minha-conta/ordens-de-servico/salvar', ['csrf_token' => $o->csrf($get($o, $base)), 'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id' => $customerId, 'description' => "$tag $word", 'start_date' => '2035-01-01', 'due_date' => $due,
        'item_type' => ['service'], 'item_description' => ['Serviço de teste'], 'item_quantity' => ['1'], 'item_unit_price' => ['10,00'], 'item_discount' => ['0'], 'discount' => '0']);
}
$after = $get($o, $base);
$check($customerId > 0 && str_contains($after, "$tag janeiro") && str_contains($after, "$tag junho"), 'Ordens de teste criadas pela interface');
$check(str_contains($after, 'id="service-order-filters"') && str_contains($after, 'class="filter-period"'), 'Modal de filtros com período padrão');
$check($count($after, 'Todas') === $count($before, 'Todas') + 2, 'Aba Todas conta as novas ordens');
$search = $get($o, "$base?busca=" . urlencode("$tag jun"));
$check(str_contains($search, "$tag junho") && !str_contains($search, "$tag janeiro") && str_contains($search, "Busca: $tag jun"), 'Busca por descrição filtra e mostra etiqueta');
$range = $get($o, "$base?inicio=2035-06-01&fim=2035-06-30");
$check(str_contains($range, "$tag junho") && !str_contains($range, "$tag janeiro") && str_contains($range, 'Prazo: 01/06/2035 a 30/06/2035'), 'Período filtra pelo prazo');
$status = $get($o, "$base?situacao=completed&busca=$tag");
$check(!str_contains($status, "$tag junho") && preg_match('/aria-current="true">Concluídas/u', $status) === 1, 'Aba de situação filtra e fica marcada');
$check($count($status, 'Rascunhos') >= 2, 'Filtrar uma situação não zera as contagens das outras');
$check(!preg_match('/(Warning|Notice|Fatal error)<\/b>/', $after . $search . $range . $status), 'Sem erro PHP');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
