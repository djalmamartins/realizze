<?php
// SEC-106: com a sessão numa empresa, enviar ids de registros de OUTRA empresa do mesmo dono não altera nada.
// O dono tem as duas empresas, então só o isolamento por contexto protege. Compara uma impressão digital das linhas antes e depois.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$org = static fn (string $slug): array => $pdo->query("SELECT o.id, c.id context FROM organizations o JOIN financial_contexts c ON c.organization_id=o.id WHERE o.slug=" . $pdo->quote($slug))->fetch(PDO::FETCH_ASSOC);
$alfa = $org('missao-alfa-ltda'); $beta = $org('missao-beta-me');
$one = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$targets = [
    'lançamento' => ['finance_entries', $one("SELECT id FROM finance_entries WHERE financial_context_id={$beta['context']} AND kind IN ('income','expense') ORDER BY id DESC LIMIT 1")],
    'conta' => ['finance_accounts', $one("SELECT id FROM finance_accounts WHERE financial_context_id={$beta['context']} ORDER BY id LIMIT 1")],
    'categoria' => ['finance_categories', $one("SELECT id FROM finance_categories WHERE financial_context_id={$beta['context']} ORDER BY id LIMIT 1")],
    'contato' => ['finance_contacts', $one("SELECT id FROM finance_contacts WHERE financial_context_id={$beta['context']} ORDER BY id LIMIT 1")],
    'linha de conciliação' => ['finance_reconciliation_lines', $one("SELECT id FROM finance_reconciliation_lines WHERE financial_context_id={$beta['context']} ORDER BY id LIMIT 1")],
];
$fingerprint = static function (string $table, int $id) use ($pdo): string { $row = $pdo->query("SELECT * FROM $table WHERE id=$id")->fetch(PDO::FETCH_ASSOC); unset($row['updated_at']); return md5(json_encode($row)); };
$before = [];
foreach ($targets as $label => [$table, $id]) { if ($id > 0) $before[$label] = $fingerprint($table, $id); }
$check(count($before) >= 3, 'Há registros da empresa Beta para testar (' . implode(', ', array_keys($before)) . ')');
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$page = $o->req('GET', '/e/missao-alfa-ltda/pagar')['body'];
$base = ['csrf_token' => $o->csrf($page), 'context_organization_id' => (string) $alfa['id'], 'idempotency_key' => bin2hex(random_bytes(16))];
[$entry, $account, $category, $contact, $line] = array_map(static fn (array $t): int => $t[1], array_values($targets));
$posts = [
    ['/minha-conta/financeiro/lancamento-editar', ['id' => $entry, 'kind' => 'expense', 'description' => 'IDOR', 'amount' => '1,00', 'due_date' => date('Y-m-d'), 'back' => 'pagar']],
    ['/minha-conta/financeiro/baixa', ['id' => $entry, 'kind' => 'expense', 'amount' => '1,00', 'paid_at' => date('Y-m-d'), 'back' => 'pagar']],
    ['/minha-conta/financeiro/excluir', ['id' => $entry, 'back' => 'pagar']],
    ['/minha-conta/financeiro/conta-editar', ['id' => $account, 'name' => 'IDOR', 'back' => 'contas']],
    ['/minha-conta/financeiro/conta-arquivar', ['id' => $account, 'back' => 'contas']],
    ['/minha-conta/financeiro/categoria-excluir', ['id' => $category, 'back' => 'categorias']],
    ['/minha-conta/contatos/atualizar', ['id' => $contact, 'person_type' => 'company', 'legal_name' => 'IDOR', 'is_customer' => '1', 'back' => 'clientes']],
    ['/minha-conta/contatos/status', ['id' => $contact, 'status' => 'inactive', 'back' => 'clientes']],
    ['/minha-conta/financeiro/conciliacao-revisar', ['line_id' => $line, 'to' => 'ignored', 'back' => 'conciliacao']],
    ['/minha-conta/financeiro/conciliacao-lancar', ['line_id' => $line, 'back' => 'conciliacao']],
];
$codes = [];
foreach ($posts as [$path, $data]) { $codes[] = basename($path) . '=' . $o->req('POST', $path, $base + $data)['code']; }
$changed = [];
foreach ($targets as $label => [$table, $id]) { if (isset($before[$label]) && $fingerprint($table, $id) !== $before[$label]) $changed[] = $label; }
$check($changed === [], 'Nenhum registro da Beta mudou com a sessão na Alfa' . ($changed ? ': ' . implode(', ', $changed) . ' (' . implode(' ', $codes) . ')' : ''));
// Controle: o mesmo envio, na empresa certa, altera de fato (o teste não passa por envio malformado).
$account = $targets['conta'][1];
$oldName = (string) $pdo->query("SELECT name FROM finance_accounts WHERE id=$account")->fetchColumn();
$betaPage = $o->req('GET', '/e/missao-beta-me/cartoes')['body'];
$o->req('POST', '/minha-conta/financeiro/conta-editar', ['csrf_token' => $o->csrf($betaPage), 'context_organization_id' => (string) $beta['id'], 'id' => $account, 'name' => $oldName . ' ctl', 'back' => 'contas']);
$renamed = (string) $pdo->query("SELECT name FROM finance_accounts WHERE id=$account")->fetchColumn();
$check($renamed === $oldName . ' ctl', 'Controle: na própria empresa a edição da conta funciona');
$pdo->prepare('UPDATE finance_accounts SET name=? WHERE id=?')->execute([$oldName, $account]);
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
