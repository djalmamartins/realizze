<?php
declare(strict_types=1);

/** Sair das Dívidas: integração com contas, duplicidade, estorno, parcial, quitação, acordo e Planejamento. Exige banco de teste e a migration 013. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\DebtPlanner;
use Moves\Services\Debts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$refused = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
        $check(false, $message);
    } catch (InvalidArgumentException) {
        echo "OK - {$message}\n";
    }
};
$users = [];
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'C', ?, 'x', 'active')")
            ->execute(['debt3-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $uid = $users['a'];
    $context = FinanceContext::forUser($uid);
    $debts = new Debts($context);
    $other = new Debts(FinanceContext::forUser($users['b']));
    $finance = new Finance($context);
    $check($debts->controlReady(), 'Migration 013 aplicada');
    $account = $finance->createAccount(['name' => 'Conta', 'type' => 'checking', 'opening_balance' => '5.000,00']);
    $balance = static fn (): int => array_column($finance->accountsWithBalance(), 'balance_cents', 'id')[$account];
    $today = date('Y-m-d');
    $due = date('Y-m-15', strtotime('first day of next month'));

    // Cadastro só com o essencial.
    $min = $debts->create(['creditor_name' => 'Amigo', 'balance' => '100,00'], $uid);
    $check($debts->find($min)['monthly_rate'] === null && $debts->find($min)['monthly_payment_cents'] === null, 'Cadastra sem juros nem parcela');
    $debts->update($min, ['creditor_name' => 'Amigo João', 'description' => 'Empréstimo', 'balance' => '90,00', 'original_amount' => '120,00']);
    $check($debts->find($min)['creditor_name'] === 'Amigo João' && $debts->find($min)['balance_cents'] === 9000 && $debts->find($min)['description'] === 'Empréstimo', 'Edita a dívida');
    $refused(static fn () => $debts->update($min, ['creditor_name' => 'Amigo', 'balance' => '90,00', 'status' => 'paid']), 'Não marca como quitada com saldo');

    $id = $debts->create(['creditor_name' => 'Banco', 'balance' => '1.000,00', 'monthly_payment' => '200,00', 'installments_remaining' => '5', 'next_due_date' => $due, 'account_id' => $account, 'monthly_rate' => '2'], $uid);

    // Parcial: abate o saldo, cria a saída, não avança a parcela.
    $partial = $debts->registerPayment($id, ['amount' => '50,00', 'paid_on' => $today], $uid);
    $debt = $debts->find($id);
    $check($debt['balance_cents'] === 95000 && $debt['paid_cents'] === 5000 && $balance() === 495000, 'Parcial abate a dívida e a conta');
    $check($debt['installments_remaining'] === 5 && $debt['next_due_date'] === $due, 'Parcial não avança parcela nem vencimento');

    // Duplo envio.
    $key = bin2hex(random_bytes(16));
    $first = $debts->registerPayment($id, ['amount' => '200,00', 'paid_on' => $today, 'idempotency_key' => $key], $uid);
    $second = $debts->registerPayment($id, ['amount' => '200,00', 'paid_on' => $today, 'idempotency_key' => $key], $uid);
    $check($first === $second && $debts->find($id)['balance_cents'] === 75000 && $balance() === 475000, 'Mesmo envio não paga duas vezes');
    $check($debts->find($id)['installments_remaining'] === 4 && $debts->find($id)['next_due_date'] > $due, 'Parcela cheia avança vencimento e reduz parcelas');
    $entries = (int) $pdo->query("SELECT COUNT(*) FROM finance_entries WHERE financial_context_id = {$context} AND description LIKE 'Pagamento de dívida%'")->fetchColumn();
    $check($entries === 2, 'Uma saída por pagamento');

    // Proteção da saída ligada ao pagamento.
    $entryId = (int) $pdo->query('SELECT entry_id FROM finance_debt_payments WHERE id = ' . $first)->fetchColumn();
    $refused(static fn () => $finance->delete($entryId, $uid), 'Saída de pagamento não pode ser excluída em Pagar');

    // Estorno.
    $refused(static fn () => $other->reversePayment($first, $users['b']) ?: throw new InvalidArgumentException('não encontrado'), 'Outro usuário não estorna');
    $check($debts->reversePayment($first, $uid), 'Estorna o pagamento');
    $debt = $debts->find($id);
    $check($debt['balance_cents'] === 95000 && $debt['paid_cents'] === 5000 && $balance() === 495000, 'Estorno refaz dívida e conta');
    $check($debt['installments_remaining'] === 5 && $debt['next_due_date'] === $due, 'Estorno devolve parcela e vencimento');
    $history = array_values(array_filter($debts->payments($id), static fn (array $p): bool => (int) $p['id'] === $first));
    $check($history !== [] && $history[0]['reversed_at'] !== null, 'Histórico mantém o pagamento estornado');
    $refused(static fn () => $debts->reversePayment($first, $uid), 'Não estorna duas vezes');

    // Estorno de despesa vinculada em aberto volta para pendente; a já paga continua paga.
    $open = $finance->createEntry('expense', ['description' => 'Parcela', 'amount' => '100,00', 'due_date' => $today, 'account_id' => $account], $uid);
    $linked = $debts->registerPayment($id, ['amount' => '100,00', 'paid_on' => $today, 'entry_id' => (string) $open], $uid);
    $check($balance() === 485000, 'Vínculo baixa a despesa sem criar outra');
    $debts->reversePayment($linked, $uid);
    $paidAt = $pdo->query('SELECT paid_at FROM finance_entries WHERE id = ' . $open)->fetchColumn();
    $check($paidAt === null && $balance() === 495000, 'Estorno devolve a despesa para em aberto');
    $paidEntry = $finance->createEntry('expense', ['description' => 'Já paga', 'amount' => '100,00', 'due_date' => $today, 'account_id' => $account, 'paid' => '1'], $uid);
    $linkedPaid = $debts->registerPayment($id, ['amount' => '100,00', 'paid_on' => $today, 'entry_id' => (string) $paidEntry], $uid);
    $debts->reversePayment($linkedPaid, $uid);
    $check($pdo->query('SELECT paid_at FROM finance_entries WHERE id = ' . $paidEntry)->fetchColumn() !== null && $balance() === 485000, 'Despesa que já estava paga continua paga');

    // Pago por fora, data futura, conta alheia.
    $debts->registerPayment($id, ['amount' => '50,00', 'paid_on' => $today, 'account_id' => 'none'], $uid);
    $check($debts->find($id)['balance_cents'] === 90000 && $balance() === 485000, 'Pago por fora não mexe na conta');
    $refused(static fn () => $debts->registerPayment($id, ['amount' => '1,00', 'paid_on' => date('Y-m-d', strtotime('+1 day'))], $uid), 'Data futura recusada');
    $foreign = (new Finance(FinanceContext::forUser($users['b'])))->createAccount(['name' => 'B', 'type' => 'checking']);
    $refused(static fn () => $debts->registerPayment($id, ['amount' => '1,00', 'paid_on' => $today, 'account_id' => (string) $foreign], $uid), 'Conta de outro usuário recusada');

    // Planejamento: parcela prevista some quando paga no mês; o pagamento vira despesa uma vez.
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $check($debts->plannedPayments($monthStart, $monthEnd) === 0, 'Parcela já paga no mês não é prevista de novo');
    $nextStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));
    $nextEnd = date('Y-m-t', strtotime($nextStart));
    $check($debts->plannedPayments($nextStart, $nextEnd) === 20000, 'Parcela do mês seguinte é prevista');
    $check($debts->plannedPayments('2020-01-01', '2020-12-31') === 0, 'Passado não recebe previsão');
    $plan = $finance->planProjection($monthStart, $monthEnd);
    $check($plan['expense_realized'] === 15000 && $plan['expense_expected'] === 10000, 'Pagamentos entram no realizado uma única vez');

    // Panorama.
    $late = $debts->create(['creditor_name' => 'Atrasada', 'balance' => '10,00', 'monthly_payment' => '10,00', 'next_due_date' => '2020-01-10'], $uid);
    $view = $debts->panorama();
    $check(in_array($late, array_map('intval', array_column($view['late'], 'id')), true), 'Parcela em atraso aparece');
    $check($view['total_cents'] === 90000 + 9000 + 1000 && $view['monthly_cents'] === 21000 && $view['progress'] > 0, 'Totais, parcela mensal e progresso');
    $check($other->panorama()['total_cents'] === 0, 'Panorama isolado por usuário');

    // Renegociação preserva o histórico e pode ser desfeita.
    $before = $debts->find($id);
    $paymentsBefore = count($debts->payments($id));
    $negotiation = $debts->createNegotiation($id, ['down_payment' => '100,00', 'installments' => '4', 'installment' => '150,00', 'first_due_date' => $due, 'status' => 'proposal'], $uid);
    $check($debts->find($id)['balance_cents'] === $before['balance_cents'], 'Proposta não muda os termos');
    $check($debts->updateNegotiationStatus($negotiation, 'accepted'), 'Aceita o acordo');
    $debt = $debts->find($id);
    $check($debt['balance_cents'] === 70000 && $debt['monthly_payment_cents'] === 15000 && $debt['installments_remaining'] === 4 && $debt['status'] === 'agreement' && $debt['monthly_rate'] === 0.0, 'Acordo aplica novos termos');
    $check(count($debts->payments($id)) === $paymentsBefore, 'Acordo não apaga pagamentos');
    $second = $debts->createNegotiation($id, ['installments' => '2', 'installment' => '10,00'], $uid);
    $refused(static fn () => $debts->updateNegotiationStatus($second, 'accepted'), 'Só um acordo em vigor');
    $debts->registerPayment($id, ['amount' => '150,00', 'paid_on' => $today], $uid);
    $debts->updateNegotiationStatus($negotiation, 'broken');
    $debt = $debts->find($id);
    $check($debt['balance_cents'] === $before['balance_cents'] - 15000 && $debt['monthly_payment_cents'] === 20000 && abs($debt['monthly_rate'] - 2.0) < 0.001 && $debt['status'] === 'overdue', 'Acordo rompido volta aos termos, descontado o pago');

    // Quitação.
    $full = $debts->find($id)['balance_cents'];
    $payoff = $debts->registerPayment($id, ['amount' => number_format($full / 100, 2, ',', ''), 'paid_on' => $today], $uid);
    $check($debts->find($id)['balance_cents'] === 0 && $debts->find($id)['status'] === 'paid', 'Quita a dívida');
    $refused(static fn () => $debts->registerPayment($id, ['amount' => '1,00', 'paid_on' => $today], $uid), 'Dívida quitada não recebe pagamento');
    $check($debts->update($id, ['creditor_name' => 'Banco', 'balance' => '0,00', 'status' => 'paid', 'notes' => 'Encerrada']), 'Dívida quitada pode ter os dados corrigidos');
    $debts->reversePayment($payoff, $uid);
    $check($debts->find($id)['status'] !== 'paid' && $debts->find($id)['balance_cents'] === $full, 'Estornar a quitação reabre a dívida');

    // Simulações com premissas explícitas.
    $plans = DebtPlanner::compare([
        ['id' => 1, 'balance_cents' => 100000, 'monthly_rate' => 10.0, 'monthly_payment_cents' => 20000],
        ['id' => 2, 'balance_cents' => 50000, 'monthly_rate' => 1.0, 'monthly_payment_cents' => 10000],
    ], 50000, new DateTimeImmutable('2031-01-01'));
    $check($plans['avalanche']['total_interest_cents'] <= $plans['snowball']['total_interest_cents'] && $plans['recommended'] === 'avalanche', 'Avalanche custa menos juros que bola de neve');
    $check($plans['avalanche']['months'] <= $plans['minimum']['months'] && $plans['avalanche']['assumptions'] !== [] , 'Pagar mais encurta o prazo, com premissas expostas');
    $more = DebtPlanner::compare([['id' => 1, 'balance_cents' => 100000, 'monthly_rate' => 5.0, 'monthly_payment_cents' => 10000]], 20000);
    $base = DebtPlanner::compare([['id' => 1, 'balance_cents' => 100000, 'monthly_rate' => 5.0, 'monthly_payment_cents' => 10000]], 15000);
    $check($more['avalanche']['months'] < $base['avalanche']['months'] && $more['avalanche']['total_interest_cents'] < $base['avalanche']['total_interest_cents'], 'Pagamento adicional reduz prazo e juros');
} finally {
    foreach ($users as $id) {
        $context = FinanceContext::forUser($id);
        $pdo->prepare('DELETE FROM finance_debts WHERE financial_context_id = ?')->execute([$context]);
        $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = ?')->execute([$context]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
echo "Todos os testes de controle de dívidas passaram.\n";
