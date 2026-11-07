<?php
// APP-REP-105: exportações CSV da Central de Relatórios (PF e PJ). Escreve uma receita de teste no banco isolado.
// Confere: todo link "Exportar" das telas responde CSV com BOM e cabeçalho; valores e fórmulas protegidas; papel sem reports.export recebe 403.
declare(strict_types=1);
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$get = static function (H $h, string $url): array { $r = $h->req('GET', $url); return $r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r; };
$exportLinks = static function (string $html): array {
    preg_match_all('#href="(/minha-conta/financeiro/exportar[^"]*)"#', $html, $m);
    return array_values(array_unique(array_map('html_entity_decode', $m[1])));
};
$csvOk = static fn (array $r): bool => $r['code'] === 200 && str_starts_with((string) $r['type'], 'text/csv') && str_starts_with($r['body'], "\xEF\xBB\xBF") && str_contains(strtok(substr($r['body'], 3), "\n"), ';');
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$slug = 'missao-alfa-ltda';
foreach (['PF' => ['/p/relatorios', '/p/planejamento'], 'PJ' => ["/e/$slug/relatorios", "/e/$slug/dre", "/e/$slug/fluxo-de-caixa", "/e/$slug/planejamento"]] as $ctx => $pages) {
    $links = [];
    foreach ($pages as $page) {
        $html = $get($o, $page)['body'];
        foreach ($exportLinks($html) as $link) $links[$link] = $page;
    }
    $check($links !== [], "$ctx: telas oferecem exportação (" . count($links) . ' links)');
    $bad = [];
    foreach ($links as $link => $page) {
        $get($o, $page); // o contexto da sessão segue a tela de origem
        $r = $o->req('GET', $link);
        if (!$csvOk($r)) $bad[] = "$link ({$r['code']} {$r['type']})";
    }
    $check($bad === [], "$ctx: todo link de exportação devolve CSV com BOM e cabeçalho" . ($bad ? ': ' . implode(', ', $bad) : ''));
}
// Valor e proteção contra fórmula no CSV de Pagar e Receber da empresa.
$receber = $get($o, "/e/$slug/receber")['body'];
preg_match('/name="account_id"[^>]*>.*?<option value="(\d+)"/s', $receber, $acc);
$tag = 'EXP' . bin2hex(random_bytes(3));
$o->req('POST', '/minha-conta/financeiro/lancamento', ['csrf_token' => $o->csrf($receber), 'kind' => 'income', 'source' => 'entry', 'mes' => date('Y-m'), 'back' => 'receber',
    'description' => "=HYPERLINK(\"x\") $tag", 'amount' => '321,09', 'due_date' => date('Y-m-d'), 'account_id' => $acc[1] ?? '', 'idempotency_key' => bin2hex(random_bytes(16))]);
$get($o, "/e/$slug/relatorios");
$csv = $o->req('GET', '/minha-conta/financeiro/exportar?' . http_build_query(['inicio' => date('Y-m-01'), 'fim' => date('Y-m-t')]));
$line = '';
foreach (explode("\n", $csv['body']) as $l) if (str_contains($l, $tag)) $line = $l;
$check($line !== '' && str_contains($line, '321,09'), 'CSV de Pagar e Receber traz a receita lançada com o valor certo');
$check(str_contains($line, "\"'=HYPERLINK"), 'Texto que começa com = sai protegido contra fórmula');
// Isolamento: a receita da Alfa não aparece na exportação da Beta.
$get($o, '/e/missao-beta-me/relatorios');
$beta = $o->req('GET', '/minha-conta/financeiro/exportar?' . http_build_query(['inicio' => date('Y-m-01'), 'fim' => date('Y-m-t')]));
$check($csvOk($beta) && !str_contains($beta['body'], $tag), 'Exportação de outra empresa não traz o lançamento');
// Papel sem reports.export.
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$get($v, "/e/$slug/relatorios");
$denied = [];
foreach (['', 'faturamento', 'posicao-contas', 'fluxo-caixa', 'dre', 'obrigacoes', 'ordens-servico', 'orcamento'] as $report) {
    $r = $v->req('GET', '/minha-conta/financeiro/exportar' . ($report === '' ? '' : '?relatorio=' . $report));
    if ($r['code'] !== 403) $denied[] = ($report ?: 'pagar-receber') . "={$r['code']}";
}
$check($denied === [], 'Visualizador não exporta nenhum relatório da empresa' . ($denied ? ': ' . implode(', ', $denied) : ''));
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
