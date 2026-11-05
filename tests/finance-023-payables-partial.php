<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use MovesCode\Model\Connection;

if ((string) getenv('DB_DATABASE') !== 'realizze_migration014_20261103') exit("Recusado: use somente realizze_migration014_20261103.\n");
$ok = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException('FALHOU: ' . $message); echo "OK - {$message}\n"; };
$pdo = Connection::get(); $userId = 0;
try {
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES('Teste','Despesa',?,'x','active')")->execute(['pagar-' . bin2hex(random_bytes(6)) . '@example.invalid']);
    $userId = (int) $pdo->lastInsertId(); $context = FinanceContext::forUser($userId); $finance = new Finance($context);
    $account = $finance->createAccount(['name' => 'Caixa parcial', 'type' => 'checking', 'opening_balance' => '2.000,00']);
    $entry = $finance->createEntry('expense', ['description'=>'Fornecedor','amount'=>'1.000,00','due_date'=>'2032-02-29','account_id'=>$account], $userId);
    $pay = static fn (string $amount, string $key) => $finance->settleExpense($entry, ['amount'=>$amount,'paid_at'=>'2032-02-29','account_id'=>$account,'payment_method'=>'pix','notes'=>'Parcial','idempotency_key'=>$key], $userId);
    $firstKey = bin2hex(random_bytes(16)); $first = $pay('300,00', $firstKey);
    $ok($first['balance_cents'] === 70000, 'Primeira baixa deixa R$ 700,00 em aberto');
    $again = $pay('300,00', $firstKey); $ok($again['duplicate'] && $again['balance_cents'] === 70000, 'Chave idempotente não duplica pagamento');
    $second = $pay('200,00', bin2hex(random_bytes(16))); $ok($second['balance_cents'] === 50000, 'Segunda baixa deixa R$ 500,00 em aberto');
    $history = $finance->expenseSettlements($entry); $ok(count($history) === 2 && (int) $history[0]['amount_cents'] === 30000, 'Histórico preserva cada baixa');
    $balance = array_column($finance->accountsWithBalance(), 'balance_cents', 'id'); $ok($balance[$account] === 150000, 'Conta debita somente R$ 500,00 efetivamente pagos');
    try { $pay('500,01', bin2hex(random_bytes(16))); throw new RuntimeException('Aceitou pagamento acima do saldo'); } catch (InvalidArgumentException) { $ok(true, 'Pagamento acima do saldo é recusado'); }
    $last = $pay('500,00', bin2hex(random_bytes(16))); $ok($last['balance_cents'] === 0, 'Quitação final zera o saldo');
    $balance = array_column($finance->accountsWithBalance(), 'balance_cents', 'id'); $ok($balance[$account] === 100000, 'Quitação não duplica a saída no saldo');
} finally { if ($userId > 0) $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]); }
