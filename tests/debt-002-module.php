<?php
declare(strict_types=1);

/** Sair das Dívidas no banco: isolamento PF, cadastro, pagamentos, negociações e vínculo com lançamentos. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\AiAdvisor;
use Moves\Services\DebtPlanner;
use Moves\Services\Debts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$users = [];
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'D', ?, 'x', 'active')")
            ->execute(['debt-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $a = new Debts(FinanceContext::forUser($users['a']));
    $b = new Debts(FinanceContext::forUser($users['b']));

    $card = $a->create(['creditor_name' => 'Cartão X', 'debt_type' => 'credit_card', 'balance' => '5.000,00', 'monthly_rate' => '12', 'monthly_payment' => '300,00'], $users['a']);
    $loan = $a->create(['creditor_name' => 'Financeira Y', 'debt_type' => 'vehicle', 'balance' => '20.000,00', 'cet_annual' => '26,8242', 'monthly_payment' => '900,00', 'collateral' => 'vehicle'], $users['a']);
    $check(count($a->open()) === 2, 'Dívidas cadastradas');
    $check(abs($a->find($loan)['monthly_rate'] - 2.0) < 0.001, 'CET anual vira taxa mensal equivalente');
    try {
        $a->create(['creditor_name' => '', 'balance' => '10,00'], $users['a']);
        $check(false, 'Dívida sem credor aceita');
    } catch (InvalidArgumentException) {
        echo "OK - Dívida sem credor recusada\n";
    }
    try {
        $a->create(['creditor_name' => 'Z', 'balance' => '10,00', 'monthly_rate' => '150'], $users['a']);
        $check(false, 'Taxa absurda aceita');
    } catch (InvalidArgumentException) {
        echo "OK - Taxa acima de 100% a.m. recusada\n";
    }

    // Isolamento PF.
    $check($b->all() === [] && $b->find($card) === null, 'Outro usuário não vê as dívidas');
    $check(!$b->update($card, ['creditor_name' => 'Hack', 'balance' => '1,00']) && !$b->archive($card), 'Outro usuário não altera nem remove');
    try {
        $b->registerPayment($card, ['amount' => '10,00', 'paid_on' => date('Y-m-d')], $users['b']);
        $check(false, 'Pagamento em dívida alheia aceito');
    } catch (InvalidArgumentException) {
        echo "OK - Pagamento em dívida alheia recusado\n";
    }

    // Pagamento parcial vinculado a lançamento existente, sem duplicar.
    $finance = new Finance(FinanceContext::forUser($users['a']));
    $account = $finance->createAccount(['name' => 'Conta', 'type' => 'checking']);
    $entry = $finance->createEntry('expense', ['description' => 'Fatura cartão', 'amount' => '300,00', 'due_date' => date('Y-m-d'), 'account_id' => $account], $users['a']);
    $a->registerPayment($card, ['amount' => '300,00', 'paid_on' => date('Y-m-d'), 'entry_id' => (string) $entry], $users['a']);
    $check($a->find($card)['balance_cents'] === 470000 && $a->find($card)['paid_cents'] === 30000, 'Pagamento parcial abate o saldo');
    try {
        $a->registerPayment($loan, ['amount' => '300,00', 'paid_on' => date('Y-m-d'), 'entry_id' => (string) $entry], $users['a']);
        $check(false, 'Mesmo lançamento usado duas vezes');
    } catch (InvalidArgumentException) {
        echo "OK - Um lançamento não paga duas dívidas\n";
    }
    $check(!in_array($entry, array_map('intval', array_column($a->linkableEntries(), 'id')), true), 'Lançamento vinculado sai da lista de vínculo');
    $entryB = (new Finance(FinanceContext::forUser($users['b'])));
    $accB = $entryB->createAccount(['name' => 'B', 'type' => 'checking']);
    $foreignEntry = $entryB->createEntry('expense', ['description' => 'Alheio', 'amount' => '1,00', 'due_date' => date('Y-m-d'), 'account_id' => $accB], $users['b']);
    try {
        $a->registerPayment($card, ['amount' => '1,00', 'paid_on' => date('Y-m-d'), 'entry_id' => (string) $foreignEntry], $users['a']);
        $check(false, 'Lançamento de outro usuário vinculado');
    } catch (InvalidArgumentException) {
        echo "OK - Lançamento de outro usuário não pode ser vinculado\n";
    }
    $a->registerPayment($card, ['amount' => '10.000,00', 'paid_on' => date('Y-m-d')], $users['a']);
    $check($a->find($card)['balance_cents'] === 0 && $a->find($card)['status'] === 'paid', 'Pagamento acima do saldo quita sem saldo negativo');
    $check(count($a->payments($card)) === 2 && (int) $a->payments($card)[0]['balance_after_cents'] === 0, 'Histórico guarda saldo antes e depois');

    // Negociações.
    $neg = $a->createNegotiation($loan, ['down_payment' => '2.000,00', 'installments' => '24', 'installment' => '800,00', 'origin' => 'creditor'], $users['a']);
    $check($a->find($loan)['status'] === 'negotiating', 'Proposta coloca a dívida em negociação');
    $check($a->updateNegotiationStatus($neg, 'accepted') && $a->find($loan)['status'] === 'agreement', 'Acordo aceito muda a situação');
    $check(!$b->updateNegotiationStatus($neg, 'rejected'), 'Outro usuário não altera a negociação');
    $check(count($a->negotiations($loan)) === 1, 'Histórico de acordos');

    // Diagnóstico e capacidade com dados reais.
    $a->saveProfile(['net_income' => '4.000,00', 'essential_expenses' => '3.200,00', 'variable_expenses' => '0', 'emergency_reserve' => ''], $users['a']);
    $capacity = DebtPlanner::capacity($a->profile(), $a->open());
    $check($capacity['capacity_cents'] === 34000 && $capacity['situation'] === 'over_committed', 'Capacidade R$ 340 e parcelas acima dela sinalizadas');
    try {
        $a->saveProfile(['net_income' => ''], $users['a']);
        $check(false, 'Diagnóstico sem renda aceito');
    } catch (InvalidArgumentException) {
        echo "OK - Diagnóstico exige renda\n";
    }

    // Dados enviados à IA: sem nomes nem identificadores; sem provedor, indisponível.
    $summary = json_encode(AiAdvisor::minimizedSummary($capacity, $a->open(), DebtPlanner::compare(array_map(static fn ($d) => ['id' => $d['id'], 'balance_cents' => $d['balance_cents'], 'monthly_rate' => $d['monthly_rate'], 'monthly_payment_cents' => $d['monthly_payment_cents']], $a->open()), 100000)));
    $check(!str_contains($summary, 'Financeira Y') && !str_contains($summary, '"id"') && !str_contains($summary, 'notes'), 'Resumo para IA sem nomes, ids ou observações');
    $check(!AiAdvisor::available(), 'Sem provedor de IA configurado');
} finally {
    foreach ($users as $id) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
echo "Todos os testes do módulo passaram.\n";
