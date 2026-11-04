<?php
declare(strict_types=1);

/** Recorrências previstas em Pagar e Receber. Tudo roda em transação desfeita no final. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\RecurringFinance;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};

$pdo = Connection::get();
$account = $pdo->query('SELECT id, financial_context_id, (SELECT created_by FROM finance_entries LIMIT 1) user_id FROM finance_accounts ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($account === false) {
    exit("Sem conta para testar.\n");
}
$context = (int) $account['financial_context_id'];
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE finance_recurring_items SET status=? WHERE financial_context_id=?')->execute(['paused', $context]);
    $insert = $pdo->prepare('INSERT INTO finance_recurring_items (financial_context_id,item_type,kind,name,amount_cents,frequency,next_due_date,account_id,created_by,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $add = static fn (string $kind, string $name, string $frequency, string $due, string $status = 'active') => $insert->execute([$context, 'recurring', $kind, $name, 1000, $frequency, $due, $account['id'], $account['user_id'], $status]);
    $add('expense', 'Mensal 31', 'monthly', '2026-01-31');
    $add('expense', 'Semanal', 'weekly', '2026-02-20');
    $add('expense', 'Pausada', 'monthly', '2026-02-10', 'paused');
    $add('expense', 'Depois', 'monthly', '2026-03-01');
    $add('income', 'Receita', 'monthly', '2026-02-05');

    $service = new RecurringFinance($context);
    $names = static fn (array $rows): array => array_map(static fn (array $row): string => $row['due_date'] . ' ' . $row['name'], $rows);
    $february = $names($service->planned('expense', '2026-02-01', '2026-02-28'));
    $check($february === ['2026-02-20 Semanal', '2026-02-27 Semanal', '2026-02-28 Mensal 31'], 'repete no período, respeita o fim do mês e ignora pausada e futura');
    $check($names($service->planned('income', '2026-02-01', '2026-02-28')) === ['2026-02-05 Receita'], 'separa receitas de despesas');
    $check($service->planned('expense', '2025-01-01', '2025-01-31') === [], 'antes da próxima cobrança não prevê nada');
} finally {
    $pdo->rollBack();
}
