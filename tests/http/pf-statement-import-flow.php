<?php
// PF-15: importar extrato (CSV) na PF e lançar as linhas sem correspondência como receita ou despesa paga.
// Usa uma pessoa nova no banco de teste; nada da empresa é tocado.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
use Moves\Services\{Finance, FinanceContext};
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$email = 'extrato-' . bin2hex(random_bytes(4)) . '@example.invalid';
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status, email_verified_at) VALUES ('Extrato', 'PF', ?, ?, 'active', NOW())")->execute([$email, password_hash('Extrato#2026', PASSWORD_DEFAULT)]);
$user = (int) $pdo->lastInsertId();
$tmp = sys_get_temp_dir() . '/extrato-pf-' . bin2hex(random_bytes(3)) . '.csv';
try {
    $context = FinanceContext::forUser($user);
    $finance = new Finance($context);
    $account = $finance->createAccount(['name' => 'Conta Extrato', 'type' => 'checking', 'opening_balance' => '1.000,00']);
    $market = $finance->createCategory(['name' => 'Mercado PF', 'category_kind' => 'expense']);
    $balance = static function () use ($finance, $account): int { foreach ($finance->accountsWithBalance() as $a) if ((int) $a['id'] === $account) return (int) $a['balance_cents']; return PHP_INT_MIN; };
    $h = new H();
    $check($h->login($email, 'Extrato#2026'), 'Pessoa entra');
    $get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
    $menu = $get($h, '/p');
    $check(str_contains($menu, 'href="/p/conciliacao"'), 'Menu PF tem Conciliação bancária (importar extrato)');
    $page = $get($h, '/p/conciliacao');
    file_put_contents($tmp, "Data;Descrição;Valor\n03/02/2031;SUPERMERCADO BOM PRECO;-123,45\n05/02/2031;PIX RECEBIDO JOAO;200,00\n");
    $h->req('POST', '/minha-conta/financeiro/conciliacao-importar', ['csrf_token' => $h->csrf($page), 'back' => 'conciliacao', 'account_id' => $account], ['statement' => new CURLFile($tmp, 'text/plain', 'extrato.csv')]);
    $lines = $pdo->query("SELECT id, amount_cents FROM finance_reconciliation_lines WHERE financial_context_id=$context ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $check(count($lines) === 2, 'CSV importado na PF: 2 linhas');
    $before = $balance();
    $expenseLine = (int) $lines[0]['id'];
    $page = $get($h, '/p/conciliacao');
    $check(str_contains($page, 'action="/minha-conta/financeiro/conciliacao-lancar"') && str_contains($page, 'Lançar despesa') && str_contains($page, 'Lançar receita'), 'Linhas oferecem Lançar receita/despesa');
    $post = ['csrf_token' => $h->csrf($page), 'back' => 'conciliacao', 'line_id' => $expenseLine, 'category_id' => $market];
    $h->req('POST', '/minha-conta/financeiro/conciliacao-lancar', $post);
    $entry = $pdo->query("SELECT e.kind, e.amount_cents, e.paid_at, e.category_id, l.status, l.matched_entry_id = e.id linked FROM finance_reconciliation_lines l JOIN finance_entries e ON e.id = l.matched_entry_id WHERE l.id = $expenseLine")->fetch(PDO::FETCH_ASSOC);
    $check($entry && $entry['kind'] === 'expense' && (int) $entry['amount_cents'] === 12345 && $entry['paid_at'] === '2031-02-03' && (int) $entry['category_id'] === $market && $entry['status'] === 'matched', 'Despesa paga criada na data do extrato, com a categoria, e linha conciliada');
    $check($balance() === $before - 12345, 'Saldo da conta cai R$ 123,45');
    $h->req('POST', '/minha-conta/financeiro/conciliacao-lancar', $post);
    $check((int) $pdo->query("SELECT COUNT(*) FROM finance_entries WHERE financial_context_id=$context AND description='SUPERMERCADO BOM PRECO'")->fetchColumn() === 1, 'Reenviar não cria outro lançamento');
    $h->req('POST', '/minha-conta/financeiro/conciliacao-lancar', ['csrf_token' => $h->csrf($get($h, '/p/conciliacao')), 'back' => 'conciliacao', 'line_id' => (int) $lines[1]['id'], 'category_id' => $market]);
    $check((int) $pdo->query("SELECT COUNT(*) FROM finance_entries WHERE financial_context_id=$context AND description='PIX RECEBIDO JOAO'")->fetchColumn() === 0, 'Categoria de despesa não serve para receita');
    $h->req('POST', '/minha-conta/financeiro/conciliacao-lancar', ['csrf_token' => $h->csrf($get($h, '/p/conciliacao')), 'back' => 'conciliacao', 'line_id' => (int) $lines[1]['id']]);
    $check($balance() === $before - 12345 + 20000 && str_contains($get($h, '/p/movimentacoes?inicio=2031-02-01&fim=2031-02-28'), 'PIX RECEBIDO JOAO'), 'Receita sem categoria entra no saldo e em Movimentações');
    $other = new H(); $other->login('pj-dono@example.invalid', 'PjMissao#2026');
    $r = $other->req('POST', '/minha-conta/financeiro/conciliacao-lancar', ['csrf_token' => $other->csrf($get($other, '/p/conciliacao')), 'back' => 'conciliacao', 'line_id' => (int) $lines[1]['id']]);
    $check((int) $pdo->query("SELECT COUNT(*) FROM finance_entries WHERE financial_context_id=$context")->fetchColumn() === 2, 'Outra pessoa não lança linha que não é dela');
} finally {
    @unlink($tmp);
    foreach (['finance_reconciliation_lines', 'finance_reconciliation_imports', 'finance_entries'] as $t) $pdo->prepare("DELETE FROM $t WHERE financial_context_id = (SELECT id FROM financial_contexts WHERE personal_user_id = ?)")->execute([$user]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);
}
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
