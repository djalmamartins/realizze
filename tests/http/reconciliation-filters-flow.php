<?php
// APP-FLT-104: Conciliação no padrão de filtros de Contas a Receber. Só leitura (GET).
// Compara a tela com o banco de teste: contagens das abas, busca, período e etiquetas.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$base = '/e/missao-beta-me/conciliacao';
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$count = static fn (string $html, string $label): int => preg_match('/>' . $label . ' \((\d+)\)</u', $html, $m) ? (int) $m[1] : -1;
$context = (int) $pdo->query("SELECT c.id FROM organizations o JOIN financial_contexts c ON c.organization_id=o.id WHERE o.slug='missao-beta-me' LIMIT 1")->fetchColumn();
$line = $pdo->query("SELECT description, occurred_on FROM finance_reconciliation_lines WHERE financial_context_id=$context ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$line) { echo "Sem linhas importadas: rode enterprise-reconciliation-flow.php antes.\n"; exit(1); }
$total = (int) $pdo->query("SELECT COUNT(*) FROM finance_reconciliation_lines WHERE financial_context_id=$context")->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM finance_reconciliation_lines WHERE financial_context_id=$context AND status='pending'")->fetchColumn();
$page = $get($o, $base);
$check(str_contains($page, 'id="reconciliation-filters"') && str_contains($page, 'class="filter-period"'), 'Modal de filtros com período padrão');
$check($count($page, 'Todas') === $total && $count($page, 'Pendentes') === $pending, 'Abas mostram as contagens do banco');
$word = explode(' ', trim($line['description']))[0];
$expected = (int) $pdo->query("SELECT COUNT(*) FROM finance_reconciliation_lines WHERE financial_context_id=$context AND description LIKE " . $pdo->quote('%' . $word . '%'))->fetchColumn();
$search = $get($o, "$base?busca=" . urlencode($word));
$check($count($search, 'Todas') === $expected && str_contains($search, 'Busca: ' . htmlspecialchars($word, ENT_QUOTES)), 'Busca na descrição filtra e mostra etiqueta');
$day = $line['occurred_on'];
$expectedDay = (int) $pdo->query("SELECT COUNT(*) FROM finance_reconciliation_lines WHERE financial_context_id=$context AND occurred_on=" . $pdo->quote($day))->fetchColumn();
$range = $get($o, "$base?inicio=$day&fim=$day");
$check($count($range, 'Todas') === $expectedDay && str_contains($range, 'Data: '), 'Período filtra pela data do extrato');
$tab = $get($o, "$base?situacao=pending");
$check(preg_match('/aria-current="true">Pendentes/u', $tab) === 1 && $count($tab, 'Todas') === $total, 'Aba ativa marcada sem zerar as outras contagens');
$check(!preg_match('/(Warning|Notice|Fatal error)<\/b>/', $page . $search . $range . $tab), 'Sem erro PHP');
// Paginação: 55 linhas extras com marca única, 50 por página.
$import = (int) $pdo->query("SELECT import_id FROM finance_reconciliation_lines WHERE financial_context_id=$context LIMIT 1")->fetchColumn();
$accountId = (int) $pdo->query("SELECT account_id FROM finance_reconciliation_lines WHERE financial_context_id=$context LIMIT 1")->fetchColumn();
$mark = 'PAG' . bin2hex(random_bytes(3));
$insert = $pdo->prepare('INSERT INTO finance_reconciliation_lines (financial_context_id, import_id, account_id, occurred_on, description, amount_cents) VALUES (?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 55; $i++) $insert->execute([$context, $import, $accountId, '2031-01-15', "$mark linha $i", 100 + $i]);
try {
    $first = $get($o, "$base?busca=$mark");
    $second = $get($o, "$base?busca=$mark&pagina_extrato=2");
    $check(substr_count($first, "$mark linha") === 50 && substr_count($second, "$mark linha") === 5, 'Extrato pagina de 50 em 50, com os filtros mantidos');
    $check(str_contains($first, 'aria-label="Páginas do extrato"') && str_contains($first, 'pagina_extrato=2'), 'Links de página aparecem');
} finally {
    $pdo->prepare('DELETE FROM finance_reconciliation_lines WHERE description LIKE ?')->execute(["$mark linha%"]);
}
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
