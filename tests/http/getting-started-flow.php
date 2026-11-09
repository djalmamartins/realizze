<?php
// PF-06/PF-07: Primeiros passos mostra o progresso real e some quando tudo está feito; sem sobras de prévia no menu.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
use Moves\Services\{Finance, FinanceContext};
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$email = 'setup-' . bin2hex(random_bytes(4)) . '@example.invalid';
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status, email_verified_at) VALUES ('Novo', 'Usuário', ?, ?, 'active', NOW())")->execute([$email, password_hash('Setup#2026x', PASSWORD_DEFAULT)]);
$user = (int) $pdo->lastInsertId();
try {
    $context = FinanceContext::forUser($user);
    $finance = new Finance($context);
    $h = new H();
    $check($h->login($email, 'Setup#2026x'), 'Pessoa nova entra');
    $page = $h->req('GET', '/p')['body'];
    $check(str_contains($page, 'PRIMEIROS PASSOS') && preg_match('/\d de 5 concluídos/', $page) === 1, 'Painel mostra Primeiros passos com contagem');
    $check(!str_contains($page, 'Conexão bancária') && !str_contains($page, 'Em breve: progresso') && !str_contains($page, 'nesta prévia'), 'Sem sobras de prévia no menu e no painel');
    $before = (int) (preg_match('/(\d) de 5 concluídos/', $page, $m) ? $m[1] : -1);
    $account = $finance->createAccount(['name' => 'Conta Setup', 'type' => 'checking', 'opening_balance' => '0,00']);
    $after = $h->req('GET', '/p')['body'];
    $check(preg_match('/(\d) de 5 concluídos/', $after, $m2) === 1 && (int) $m2[1] === $before + 1 && str_contains($after, 'is-done'), 'Cadastrar a conta marca o passo como concluído');
    $finance->createAccount(['name' => 'Cartão Setup', 'type' => 'credit_card', 'opening_balance' => '0,00', 'credit_limit' => '1000,00', 'closing_day' => '5', 'due_day' => '12']);
    if ($finance->categories('expense') === []) $finance->createCategory(['name' => 'Mercado', 'category_kind' => 'expense']);
    $pdo->prepare("INSERT INTO finance_entries (financial_context_id, account_id, kind, description, amount_cents, due_date, status) VALUES (?, ?, 'expense', 'Primeira', 100, CURDATE(), 'pending')")->execute([$context, $account]);
    $pdo->prepare("INSERT INTO finance_goals (financial_context_id, name, target_cents) VALUES (?, 'Reserva', 100000)")->execute([$context]);
    $progress = $finance->setupProgress();
    $check(!in_array(false, $progress, true), 'Todos os passos concluídos: ' . json_encode($progress));
    $check(!str_contains($h->req('GET', '/p')['body'], 'PRIMEIROS PASSOS'), 'Com tudo pronto, o quadro some');
} finally {
    foreach (['finance_entries', 'finance_goals'] as $t) $pdo->prepare("DELETE FROM $t WHERE financial_context_id = (SELECT id FROM financial_contexts WHERE personal_user_id = ?)")->execute([$user]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);
}
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
