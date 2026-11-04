<?php
declare(strict_types=1);

/** Fatura de cartão: ciclo de fechamento, parcelas, pagamento total e parcial, estorno e saldos. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CardInvoices;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK' : 'FALHOU') . " - {$message}\n";
    if (!$ok) $failed++;
};
$refused = static function (callable $call): bool {
    try { $call(); return false; } catch (InvalidArgumentException) { return true; }
};
$key = static fn (): string => bin2hex(random_bytes(32));
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Cartao', 'Teste', ?, 'x', 'active')")
    ->execute(['cartao-' . bin2hex(random_bytes(5)) . '@example.invalid']);
$userId = (int) $pdo->lastInsertId();
$orgs = [Organizations::create($userId, 'Cartao Alfa', ''), Organizations::create($userId, 'Cartao Beta', '')];
try {
    $ctx = FinanceContext::forOrganization($orgs[0]);
    $finance = new Finance($ctx);
    $cards = new CardInvoices($ctx);
    $bank = $finance->createAccount(['name' => 'Banco', 'type' => 'checking', 'opening_balance' => '5.000,00']);
    // Fecha dia 28 e vence dia 5: compra até o dia 28 entra na fatura que vence no dia 5 do mês seguinte.
    $card = $finance->createAccount(['name' => 'Cartão', 'type' => 'credit_card', 'closing_day' => '28', 'due_day' => '5']);
    $buy = static fn (string $what, string $amount, string $date, int $parts = 1) => $cards->purchase(['card_account_id' => $card, 'description' => $what, 'amount' => $amount, 'purchased_on' => $date, 'installments' => (string) $parts, 'idempotency_key' => $key()], $userId);
    $buy('Antes do fechamento', '100,00', '2031-03-10');
    $buy('No dia do fechamento', '50,00', '2031-03-28');
    $buy('Depois do fechamento', '70,00', '2031-03-29');
    $buy('Parcelado', '300,00', '2031-03-15', 3);
    $march = $cards->invoice($card, '2031-03');
    $check($march['due_on'] === '2031-04-05' && $march['start'] === '2031-03-01' && $march['end'] === '2031-03-28', 'Fatura de março fecha dia 28 e vence 05/04');
    $check(count($march['rows']) === 3, 'Compras até o fechamento entram na fatura');
    $check($march['total_cents'] === 10000 + 5000 + 10000, 'Total da fatura de março');
    $april = $cards->invoice($card, '2031-04');
    $check($april['total_cents'] === 7000 + 10000 && $april['due_on'] === '2031-05-05', 'Compra depois do fechamento e a 2ª parcela vão para abril');
    $check($cards->invoice($card, '2031-05')['total_cents'] === 10000, 'Terceira parcela em maio');

    // Fecha dia 1 e vence dia 10: o vencimento vem depois do fechamento, no mesmo mês.
    $early = $finance->createAccount(['name' => 'Cartão 2', 'type' => 'credit_card', 'closing_day' => '1', 'due_day' => '10']);
    $cards->purchase(['card_account_id' => $early, 'description' => 'Compra', 'amount' => '40,00', 'purchased_on' => '2031-03-02', 'installments' => '1', 'idempotency_key' => $key()], $userId);
    $inv = $cards->invoice($early, '2031-04');
    $check($inv['total_cents'] === 4000 && $inv['due_on'] === '2031-04-10', 'Fechamento antes do vencimento no mesmo mês');

    $before = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($before[$bank] === 500000 && $before[$card] === 0, 'Compras em aberto não mexem no saldo');
    $check($refused(static fn () => $cards->pay(['card_account_id' => $card, 'source_account_id' => $bank, 'month' => '2031-03', 'amount' => '300,00', 'paid_on' => '2031-04-05', 'idempotency_key' => $key()], $userId)), 'Pagamento maior que a fatura é recusado');
    $payKey = $key();
    $payment = $cards->pay(['card_account_id' => $card, 'source_account_id' => $bank, 'month' => '2031-03', 'paid_on' => '2031-04-05', 'idempotency_key' => $payKey], $userId);
    $check($cards->pay(['card_account_id' => $card, 'source_account_id' => $bank, 'month' => '2031-03', 'paid_on' => '2031-04-05', 'idempotency_key' => $payKey], $userId) === $payment, 'Duplo envio não paga duas vezes');
    $after = array_column($finance->accountsWithBalance(), 'balance_cents', 'id');
    $check($after[$bank] === 500000 - 25000, 'Banco sai uma vez com o valor da fatura');
    $check($after[$card] === 0, 'Cartão volta a zero depois de pago');
    $expenses = $finance->summary('expense', '2031-01-01', '2031-12-31');
    $check($expenses['paid'] === 25000, 'Despesa paga conta uma vez só, pelas compras');
    $check($cards->invoice($card, '2031-03')['total_cents'] === 0, 'Fatura paga fica sem saldo aberto');
    $check($finance->balanceSummary()['total_cents'] === 475000, 'Saldo consolidado ignora o cartão e desconta o pagamento');

    $partial = $cards->pay(['card_account_id' => $card, 'source_account_id' => $bank, 'month' => '2031-04', 'amount' => '70,00', 'paid_on' => '2031-05-05', 'idempotency_key' => $key()], $userId);
    $check($cards->invoice($card, '2031-04')['total_cents'] === 10000, 'Pagamento parcial abate do saldo da fatura');
    $check(array_column($finance->accountsWithBalance(), 'balance_cents', 'id')[$bank] === 475000 - 7000, 'Pagamento parcial sai do banco');
    $check($cards->reversePayment($partial, $userId) && $cards->invoice($card, '2031-04')['total_cents'] === 17000, 'Estorno devolve o saldo da fatura');
    $check(array_column($finance->accountsWithBalance(), 'balance_cents', 'id')[$bank] === 475000, 'Estorno devolve o dinheiro ao banco');
    $check(!$cards->reversePayment($partial, $userId), 'Estorno não vale duas vezes');
    $check($cards->payments($card)[0]['reversed_at'] !== null && count($cards->payments($card)) === 2, 'Histórico guarda pagamentos e estornos');

    $other = new CardInvoices(FinanceContext::forOrganization($orgs[1]));
    $check($refused(static fn () => $other->invoice($card, '2031-03')), 'Outra empresa não vê a fatura');
    $check(!$other->reversePayment($payment, $userId), 'Outra empresa não estorna o pagamento');
} finally {
    foreach ($orgs as $org) {
        $c = FinanceContext::forOrganization($org);
        foreach (['finance_card_payment_allocations', 'finance_card_invoice_payments', 'finance_card_installments', 'finance_card_purchases', 'finance_entry_settlements', 'finance_entries', 'finance_accounts', 'finance_categories'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE financial_context_id = ?")->execute([$c]);
        }
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$c]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo $failed === 0 ? "FIN-020: FATURAS APROVADAS.\n" : "FIN-020: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
