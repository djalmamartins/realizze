<?php
// FIN-026: estorno de baixa (pagamento e recebimento), pela interface, no banco de teste.
// Confere saldo da conta, situação do lançamento, histórico, idempotência (reenviar não estorna outra) e permissão.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
use Moves\Services\Finance;
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$org = $pdo->query("SELECT o.id, c.id context FROM organizations o JOIN financial_contexts c ON c.organization_id=o.id WHERE o.slug='$slug'")->fetch(PDO::FETCH_ASSOC);
$finance = new Finance((int) $org['context']);
$balance = static function (int $account) use ($finance): int { foreach ($finance->accountsWithBalance() as $a) if ((int) $a['id'] === $account) return (int) $a['balance_cents']; return PHP_INT_MIN; };
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$pagar = $get($o, "/e/$slug/pagar");
preg_match('/<select name="account_id" required>.*?<option value="(\d+)"/s', $pagar, $acc);
$account = (int) ($acc[1] ?? 0);
$ctx = ['context_organization_id' => (string) $org['id']];
$tag = 'EST' . bin2hex(random_bytes(3));
$entry = static fn (string $description): array => $pdo->query('SELECT id, status, paid_at FROM finance_entries WHERE description=' . $pdo->quote($description))->fetch(PDO::FETCH_ASSOC) ?: [];

// Despesa: dois pagamentos (40 + 60), estorno do último.
$o->req('POST', '/minha-conta/financeiro/lancamento', $ctx + ['csrf_token' => $o->csrf($pagar), 'kind' => 'expense', 'source' => 'entry', 'mes' => date('Y-m'), 'back' => 'pagar', 'description' => "$tag despesa", 'amount' => '100,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'idempotency_key' => bin2hex(random_bytes(16))]);
$expense = (int) ($entry("$tag despesa")['id'] ?? 0);
$start = $balance($account);
foreach (['40,00', '60,00'] as $amount) {
    $o->req('POST', '/minha-conta/financeiro/baixa', $ctx + ['csrf_token' => $o->csrf($get($o, "/e/$slug/pagar")), 'kind' => 'expense', 'back' => 'pagar', 'id' => $expense, 'amount' => $amount, 'paid_at' => date('Y-m-d'), 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => bin2hex(random_bytes(16))]);
}
$check($expense > 0 && ($entry("$tag despesa")['status'] ?? '') === 'received' && $balance($account) === $start - 10000, 'Despesa paga em duas vezes debita R$ 100,00 da conta');
$page = $get($o, "/e/$slug/pagar");
preg_match('#action="/minha-conta/financeiro/baixa-estornar"[^>]*>(?:(?!</form>).)*?name="id" value="' . $expense . '"(?:(?!</form>).)*?name="settlement_id" value="(\d+)"#s', $page, $form);
$settlement = (int) ($form[1] ?? 0);
$last = (int) $pdo->query("SELECT MAX(id) FROM finance_entry_settlements WHERE entry_id=$expense")->fetchColumn();
$check($settlement === $last && $last > 0, 'Pagar oferece Estornar para o último pagamento');
$reverse = fn (): array => $o->req('POST', '/minha-conta/financeiro/baixa-estornar', $ctx + ['csrf_token' => $o->csrf($get($o, "/e/$slug/pagar")), 'kind' => 'expense', 'back' => 'pagar', 'id' => $expense, 'settlement_id' => $settlement]);
$r = $reverse();
$after = $entry("$tag despesa");
$check($r['code'] === 303 && $after['status'] === 'pending' && $after['paid_at'] === null && $balance($account) === $start - 4000, 'Estorno devolve R$ 60,00 à conta e a despesa volta a parcial');
$reverse();
$check($balance($account) === $start - 4000 && (int) $pdo->query("SELECT COUNT(*) FROM finance_entry_settlements WHERE entry_id=$expense")->fetchColumn() === 1, 'Reenviar o mesmo estorno não estorna outro pagamento');
$audit = $pdo->query("SELECT metadata_json FROM finance_entry_audit_logs WHERE entry_id=$expense AND action='updated' AND JSON_EXTRACT(metadata_json,'$.event')='settlement_reversed'")->fetchAll(PDO::FETCH_COLUMN);
$check(count($audit) === 1 && json_decode($audit[0], true)['amount_cents'] === 6000, 'Histórico guarda o estorno com o valor');

// Receita: recebimento parcial e estorno pelo histórico de Receber.
$receber = $get($o, "/e/$slug/receber");
$o->req('POST', '/minha-conta/financeiro/lancamento', $ctx + ['csrf_token' => $o->csrf($receber), 'kind' => 'income', 'source' => 'entry', 'mes' => date('Y-m'), 'back' => 'receber', 'description' => "$tag receita", 'amount' => '50,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'idempotency_key' => bin2hex(random_bytes(16))]);
$income = (int) ($entry("$tag receita")['id'] ?? 0);
$before = $balance($account);
$o->req('POST', '/minha-conta/financeiro/recebimento-baixa', $ctx + ['csrf_token' => $o->csrf($get($o, "/e/$slug/receber")), 'back' => 'receber', 'id' => $income, 'amount' => '20,00', 'received_at' => date('Y-m-d'), 'account_id' => $account, 'payment_method' => 'pix', 'idempotency_key' => bin2hex(random_bytes(16))]);
$check(($entry("$tag receita")['status'] ?? '') === 'partially_received' && $balance($account) === $before + 2000, 'Recebimento parcial entra na conta');
$receiptId = (int) $pdo->query("SELECT MAX(id) FROM finance_entry_settlements WHERE entry_id=$income")->fetchColumn();
$check(str_contains($get($o, "/e/$slug/receber"), 'name="settlement_id" value="' . $receiptId . '"'), 'Histórico do recebimento oferece Estornar');
$o->req('POST', '/minha-conta/financeiro/baixa-estornar', $ctx + ['csrf_token' => $o->csrf($get($o, "/e/$slug/receber")), 'kind' => 'income', 'back' => 'receber', 'id' => $income, 'settlement_id' => $receiptId]);
$check(($entry("$tag receita")['status'] ?? '') === 'pending' && $balance($account) === $before, 'Estorno do recebimento tira o valor da conta e volta a pendente');
$check(str_contains($get($o, "/e/$slug/receber"), 'Baixa estornada'), 'Receber mostra "Baixa estornada" no histórico');

// Permissão: visualizador não estorna.
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$vp = $get($v, "/e/$slug/pagar");
$denied = $v->req('POST', '/minha-conta/financeiro/baixa-estornar', $ctx + ['csrf_token' => $v->csrf($vp), 'kind' => 'expense', 'back' => 'pagar', 'id' => $expense, 'settlement_id' => (int) $pdo->query("SELECT MAX(id) FROM finance_entry_settlements WHERE entry_id=$expense")->fetchColumn()]);
$check($denied['code'] === 403 && $balance($account) === $before, 'Visualizador recebe 403 e nada muda');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
