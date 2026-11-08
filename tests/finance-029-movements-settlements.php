<?php
// FIN-029 (PF-02): Movimentações mostram cada baixa na data e com o valor dela; cancelados ficam fora. Só banco de teste.
declare(strict_types=1);
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';
use Moves\Services\{Finance, FinanceContext};
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) { fwrite(STDERR, "Banco de teste obrigatório.\n"); exit(1); }
$fail = 0;
$check = static function (bool $ok, string $label) use (&$fail): void { echo ($ok ? 'OK' : 'FALHOU') . " - $label\n"; if (!$ok) $fail++; };
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Mov', 'Teste', ?, 'x', 'active')")->execute(['fin029-' . bin2hex(random_bytes(4)) . '@example.invalid']);
$user = (int) $pdo->lastInsertId();
try {
    $context = FinanceContext::forUser($user);
    $finance = new Finance($context);
    $account = $finance->createAccount(['name' => 'Conta 029', 'type' => 'checking', 'opening_balance' => '0,00']);
    $entry = $pdo->prepare("INSERT INTO finance_entries (financial_context_id, account_id, kind, description, amount_cents, due_date, status, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $entry->execute([$context, $account, 'income', 'Serviço parcelado', 10000, '2031-03-01', 'received', '2031-03-20']);
    $income = (int) $pdo->lastInsertId();
    $settle = $pdo->prepare("INSERT INTO finance_entry_settlements (financial_context_id, entry_id, account_id, amount_cents, received_at, payment_method, idempotency_key) VALUES (?, ?, ?, ?, ?, 'pix', ?)");
    $settle->execute([$context, $income, $account, 4000, '2031-03-05', bin2hex(random_bytes(32))]);
    $settle->execute([$context, $income, $account, 6000, '2031-03-20', bin2hex(random_bytes(32))]);
    $entry->execute([$context, $account, 'expense', 'Conta de luz', 2500, '2031-03-10', 'received', '2031-03-10']);
    $entry->execute([$context, $account, 'expense', 'Despesa cancelada', 9900, '2031-03-11', 'cancelled', '2031-03-11']);
    $rows = $finance->movements('2031-03-01', '2031-03-31');
    $byDesc = static fn (string $d): array => array_values(array_filter($rows, static fn (array $r): bool => $r['description'] === $d));
    $parts = $byDesc('Serviço parcelado');
    $check(count($parts) === 2 && array_sum(array_map(static fn ($r) => (int) $r['amount_cents'], $parts)) === 10000, 'Receita com duas baixas aparece duas vezes, somando R$ 100,00 (não 200)');
    $check(in_array('2031-03-05', array_column($parts, 'paid_at'), true) && in_array('2031-03-20', array_column($parts, 'paid_at'), true), 'Cada baixa na sua data');
    $check(count(array_filter($parts, static fn ($r) => $r['partial'])) === 2, 'Baixas marcadas como parciais');
    $early = $finance->movements('2031-03-01', '2031-03-10');
    $check(array_sum(array_map(static fn ($r) => $r['kind'] === 'income' ? (int) $r['amount_cents'] : 0, $early)) === 4000, 'Até dia 10 só entrou a primeira baixa (R$ 40,00)');
    $check(count($byDesc('Conta de luz')) === 1 && $byDesc('Despesa cancelada') === [], 'Despesa paga sem baixa aparece; cancelada não');
    $check(count($finance->movements('2031-03-01', '2031-03-31', 'expense')) === 1, 'Filtro por tipo vale também para as baixas');
} finally {
    $pdo->prepare('DELETE FROM finance_entry_settlements WHERE financial_context_id = (SELECT id FROM financial_contexts WHERE personal_user_id = ?)')->execute([$user]);
    $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = (SELECT id FROM financial_contexts WHERE personal_user_id = ?)')->execute([$user]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);
}
echo $fail === 0 ? "FIN-029: MOVIMENTAÇÕES COM BAIXAS APROVADAS.\n" : "FIN-029: $fail falha(s).\n";
exit($fail === 0 ? 0 : 1);
