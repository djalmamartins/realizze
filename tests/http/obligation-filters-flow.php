<?php
// APP-FLT-101: Obrigações no padrão de filtros de Contas a Receber (abas com contagem, modal, etiquetas, Limpar).
// Escreve no banco de teste: cria três obrigações com título único e confere abas e filtros.
declare(strict_types=1);
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$base = "/e/$slug/obrigacoes";
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$open = static function (H $h, string $url): array { $r = $h->req('GET', $url); return $r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r; };
$tag = 'FLT' . bin2hex(random_bytes(3));
$page = $open($o, $base);
$orgId = preg_match('/data-organization-id="(\d+)"/', $page['body'], $m) ? $m[1] : '';
$create = function (string $title, string $type, string $due) use ($o, $open, $base, $orgId): void {
    $form = $open($o, $base)['body'];
    $o->req('POST', '/minha-conta/obrigacoes/salvar', ['csrf_token' => $o->csrf($form), 'context_organization_id' => $orgId, 'idempotency_key' => bin2hex(random_bytes(16)),
        'title' => $title, 'type' => $type, 'due_date' => $due, 'competence_date' => substr($due, 0, 7) . '-01', 'frequency' => 'once', 'amount' => '10,00']);
};
$before = $open($o, $base)['body'];
$count = static fn (string $html, string $label): int => preg_match('/>' . $label . ' \((\d+)\)</u', $html, $m) ? (int) $m[1] : -1;
$create("$tag vencida", 'tax', date('Y-m-d', strtotime('-3 days')));
$create("$tag futura", 'labor', date('Y-m-d', strtotime('+20 days')));
$after = $open($o, $base)['body'];
$check(str_contains($after, 'data-finance-open="obligation-filters"') && str_contains($after, 'id="obligation-filters"'), 'Botão Filtros abre o modal de filtros');
$check(str_contains($after, 'class="filter-period"'), 'Modal usa o seletor de período padrão');
$check($count($after, 'Todas') === $count($before, 'Todas') + 2, 'Aba Todas conta as novas obrigações');
$check($count($after, 'Vencidas') === $count($before, 'Vencidas') + 1, 'Aba Vencidas conta a obrigação atrasada');
$overdue = $open($o, "$base?situacao=overdue")['body'];
$check(str_contains($overdue, "$tag vencida") && !str_contains($overdue, "$tag futura"), 'Aba Vencidas filtra de verdade');
$check(preg_match('/aria-current="true">Vencidas/u', $overdue) === 1, 'Aba ativa marcada');
$typed = $open($o, "$base?tipo=labor")['body'];
$check(str_contains($typed, "$tag futura") && !str_contains($typed, "$tag vencida"), 'Filtro por tipo funciona');
$check(str_contains($typed, 'Tipo: Trabalhista') && str_contains($typed, 'class="filter-clear"'), 'Etiqueta do filtro ativo e Limpar aparecem');
$range = $open($o, "$base?inicio=" . date('Y-m-d', strtotime('+10 days')) . '&fim=' . date('Y-m-d', strtotime('+30 days')))['body'];
$check(str_contains($range, "$tag futura") && !str_contains($range, "$tag vencida") && str_contains($range, 'Vencimento: '), 'Período filtra pelo vencimento e mostra etiqueta');
$check(!preg_match('/(Warning|Notice|Fatal error)<\/b>/', $after . $overdue . $typed . $range), 'Sem erro PHP');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
