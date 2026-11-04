<?php
declare(strict_types=1);

/**
 * Carteiras virtuais das metas: reserva, resgate, transferência, pagamento, estorno, duplicidade, concorrência e isolamento.
 * Exige a 012. Recusa rodar no banco principal; limpa o que cria.
 */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\GoalWallets;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database === 'realizze_dev' || !str_contains($database, 'test')) {
    fwrite(STDERR, "Recusado: rode em um banco temporário de teste, não em {$database}.\n");
    exit(2);
}

// Processo filho do teste de concorrência: tenta reservar e informa o resultado.
if (($argv[1] ?? '') === '--worker') {
    try {
        (new GoalWallets((int) $argv[2]))->reserve((int) $argv[3], ['account_id' => $argv[4], 'amount' => $argv[5], 'date' => date('Y-m-d')], null);
        echo 'ok';
    } catch (InvalidArgumentException) {
        echo 'recusado';
    }
    exit(0);
}

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$refused = static function (callable $work, string $message) use ($check): void {
    try {
        $work();
        $check(false, $message);
    } catch (InvalidArgumentException) {
        $check(true, $message);
    }
};
$users = [];
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'W', ?, 'x', 'active')")
            ->execute(['wallet-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $user = $users['a'];
    $context = FinanceContext::forUser($user);
    $finance = new Finance($context);
    $wallets = new GoalWallets($context);
    $account = $finance->createAccount(['name' => 'Corrente', 'type' => 'checking', 'opening_balance' => '5.000,00']);
    $second = $finance->createAccount(['name' => 'Poupança', 'type' => 'savings', 'opening_balance' => '1.000,00']);
    $card = $finance->createAccount(['name' => 'Cartão', 'type' => 'credit_card']);
    $today = date('Y-m-d');

    $state = static function (int $id) use ($finance): array {
        foreach ($finance->accountsWithBalance() as $row) {
            if ((int) $row['id'] === $id) {
                return [$row['balance_cents'], $row['reserved_cents'], $row['available_cents']];
            }
        }
        throw new RuntimeException('Conta não encontrada');
    };
    $goalRow = static function (int $id) use ($finance): array {
        foreach ($finance->goals() as $goal) {
            if ((int) $goal['id'] === $id) {
                return $goal;
            }
        }
        throw new RuntimeException('Meta não encontrada');
    };
    $results = static function () use ($pdo, $context): array {
        $statement = $pdo->prepare("SELECT kind, COUNT(*) AS total, COALESCE(SUM(amount_cents), 0) AS cents FROM finance_entries WHERE financial_context_id = ? AND kind IN ('income','expense') GROUP BY kind");
        $statement->execute([$context]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    $key = static fn (): string => bin2hex(random_bytes(16));
    $goalData = ['name' => 'Viagem', 'amount' => '3.000,00', 'account_id' => $account, 'color' => '#2478A8', 'idempotency_key' => $key()];

    // 1. Criar meta e carteira vinculada, sem duplicar.
    $trip = $finance->createGoal($goalData, null, $user);
    $check($finance->createGoal($goalData, null, $user) === $trip && count($finance->goals()) === 1, '1. Criar meta e repetir o envio mantém uma só meta');
    $check((int) $goalRow($trip)['account_id'] === $account && $goalRow($trip)['color'] === '#2478a8', '1. Meta guarda conta de referência e cor');
    $check($state($account) === [500000, 0, 500000], '1. Criar meta sem reserva não muda a conta');

    // 2 e 3. Reservar R$ 500 e mais R$ 300.
    $wallets->reserve($trip, ['account_id' => $account, 'amount' => '500,00', 'date' => $today], $user);
    $check($state($account) === [500000, 50000, 450000], '2. Reservar R$ 500: total igual, disponível cai');
    $wallets->reserve($trip, ['account_id' => $account, 'amount' => '300,00', 'date' => $today], $user);
    $check($state($account) === [500000, 80000, 420000] && $goalRow($trip)['saved_cents'] === 80000, '3. Reservar mais R$ 300');
    $check($results() === [], '3. Reserva não vira receita nem despesa');

    // 4. Resgatar R$ 200.
    $wallets->release($trip, ['account_id' => $account, 'amount' => '200,00', 'date' => $today], $user);
    $check($state($account) === [500000, 60000, 440000], '4. Resgatar R$ 200 devolve ao disponível');

    // 5. Transferir R$ 100 entre metas.
    $home = $finance->createGoal(['name' => 'Casa', 'amount' => '100.000,00', 'idempotency_key' => $key()], null, $user);
    $wallets->transfer($trip, ['target_goal_id' => $home, 'account_id' => $account, 'amount' => '100,00', 'date' => $today], $user);
    $check($goalRow($trip)['saved_cents'] === 50000 && $goalRow($home)['saved_cents'] === 10000 && $state($account) === [500000, 60000, 440000], '5. Transferir R$ 100 entre metas não muda a conta');

    // 6. Pagar despesa com dinheiro reservado: uma despesa, e o disponível fica igual.
    $paid = $wallets->payExpense($trip, ['account_id' => $account, 'amount' => '200,00', 'date' => $today, 'description' => 'Hospedagem'], $user);
    $check($state($account) === [480000, 40000, 440000] && $goalRow($trip)['saved_cents'] === 30000, '6. Pagar R$ 200 com a meta: saldo e reserva caem, disponível igual');
    $check(count($finance->movements($today, $today, 'expense')) === 1 && (int) $results()[0]['cents'] === 20000, '6. A despesa aparece uma vez e o resultado conta uma vez');
    $refused(static fn () => $finance->updateExpense($paid['entry_id'], ['description' => 'X', 'amount' => '1,00', 'due_date' => $today, 'account_id' => $account], $user), '6. Despesa paga com meta não é editada por fora');

    // Despesa comum: sai só do saldo, e a reserva fica.
    $normal = $finance->createEntry('expense', ['description' => 'Luz', 'amount' => '300,00', 'due_date' => $today, 'account_id' => $account, 'paid' => '1'], $user);
    $check($state($account) === [450000, 40000, 410000], '8. Pagamento comum de R$ 300 só reduz saldo e disponível, uma vez');
    $finance->delete($normal, $user);

    // 7. Estornos.
    $finance->delete($paid['entry_id'], $user);
    $check($state($account) === [500000, 60000, 440000] && $goalRow($trip)['saved_cents'] === 50000, '7. Excluir a despesa estorna o pagamento e devolve a reserva');
    $check($results() === [], '7. Depois do estorno, nenhuma despesa restante');
    $history = $wallets->history()[$trip];
    $reservation = array_values(array_filter($history, static fn (array $h): bool => $h['type'] === 'reserve'))[0];
    $wallets->reverse((int) $reservation['id'], $user);
    $check($state($account) === [500000, 30000, 470000], '7. Estornar a reserva de R$ 300');
    $refused(static fn () => $wallets->reverse((int) $reservation['id'], $user), '7. A mesma operação não é estornada duas vezes');
    $big = $wallets->history()[$trip];
    $firstReserve = array_values(array_filter($big, static fn (array $h): bool => $h['type'] === 'reserve' && !$h['reversed']))[0];
    $refused(static fn () => $wallets->reverse((int) $firstReserve['id'], $user), '7. Estorno recusado se o valor já saiu da reserva');
    $transfer = array_values(array_filter($big, static fn (array $h): bool => $h['type'] === 'transfer_out'))[0];
    $wallets->reverse((int) $transfer['id'], $user);
    $check($goalRow($trip)['saved_cents'] === 30000 && $goalRow($home)['saved_cents'] === 0 && $state($account)[1] === 30000, '7. Estornar a transferência desfaz as duas pernas');

    // 8 e 9. Limites.
    $refused(static fn () => $wallets->reserve($trip, ['account_id' => $account, 'amount' => '4.700,01', 'date' => $today], $user), '8. Não reserva mais que o disponível');
    $wallets->reserve($home, ['account_id' => $second, 'amount' => '1.000,00', 'date' => $today], $user);
    $check($state($second) === [100000, 100000, 0], '8. Reserva o disponível exato');
    $refused(static fn () => $wallets->reserve($home, ['account_id' => $second, 'amount' => '0,01', 'date' => $today], $user), '8. Sem disponível, não reserva nem um centavo');
    $refused(static fn () => $wallets->release($trip, ['account_id' => $account, 'amount' => '300,01', 'date' => $today], $user), '9. Não resgata mais que o reservado');
    $refused(static fn () => $wallets->release($trip, ['account_id' => $second, 'amount' => '1,00', 'date' => $today], $user), '9. Não resgata de conta sem reserva da meta');
    $refused(static fn () => $wallets->reserve($trip, ['account_id' => $card, 'amount' => '1,00', 'date' => $today], $user), '8. Cartão de crédito não guarda reserva');
    $refused(static fn () => $wallets->payExpense($trip, ['account_id' => $account, 'amount' => '300,01', 'date' => $today, 'description' => 'X'], $user), '9. Não paga mais que o reservado');
    $wallets->release($home, ['account_id' => $second, 'amount' => '1.000,00', 'date' => $today], $user);

    // 10. Operações duplicadas.
    $repeat = ['account_id' => $account, 'amount' => '10,00', 'date' => $today, 'idempotency_key' => $key()];
    $check(!$wallets->reserve($trip, $repeat, $user)['duplicate'] && $wallets->reserve($trip, $repeat, $user)['duplicate'] && $state($account)[1] === 31000, '10. Reserva repetida conta uma vez');
    $repeat['idempotency_key'] = $key();
    $check(!$wallets->release($trip, $repeat, $user)['duplicate'] && $wallets->release($trip, $repeat, $user)['duplicate'] && $state($account)[1] === 30000, '10. Resgate repetido conta uma vez');
    $pay = $repeat + ['description' => 'Taxa'];
    $pay['idempotency_key'] = $key();
    $first = $wallets->payExpense($trip, $pay, $user);
    $check($wallets->payExpense($trip, $pay, $user)['duplicate'] && count($finance->movements($today, $today, 'expense')) === 1, '10. Pagamento repetido gera uma despesa');
    $finance->delete($first['entry_id'], $user);

    // 11. Patrimônio consolidado preservado, com transferência bancária entre contas.
    $summary = $finance->balanceSummary();
    $check($summary['total_cents'] === 600000 && $summary['reserved_cents'] === 30000 && $summary['available_cents'] === 570000 && $finance->availableBalance() === 570000, '11. Patrimônio de R$ 6.000 não soma as carteiras');
    $finance->transfer(['account_id' => $account, 'target_account_id' => $second, 'amount' => '100,00', 'date' => $today], $user);
    $check($finance->balanceSummary()['total_cents'] === 600000 && $state($account)[1] === 30000, '11. Transferência bancária preserva patrimônio e reservas');
    $finance->transfer(['account_id' => $second, 'target_account_id' => $account, 'amount' => '100,00', 'date' => $today], $user);

    // Insuficiência explícita: pagamento maior que o disponível não mexe na carteira.
    $wallets->reserve($home, ['account_id' => $second, 'amount' => '900,00', 'date' => $today], $user);
    $over = $finance->createEntry('expense', ['description' => 'Conserto', 'amount' => '500,00', 'due_date' => $today, 'account_id' => $second, 'paid' => '1'], $user);
    $check($state($second) === [50000, 90000, -40000] && $finance->balanceSummary()['shortfalls'] === [['name' => 'Poupança', 'cents' => 40000]], '8. Pagamento além do disponível é apontado, sem alterar a carteira');
    $finance->delete($over, $user);
    $wallets->release($home, ['account_id' => $second, 'amount' => '900,00', 'date' => $today], $user);

    // 12. Isolamento PF/PJ e entre contextos.
    $otherContext = FinanceContext::forUser($users['b']);
    $otherFinance = new Finance($otherContext);
    $otherWallets = new GoalWallets($otherContext);
    $otherAccount = $otherFinance->createAccount(['name' => 'Outra', 'type' => 'checking', 'opening_balance' => '100,00']);
    $otherGoal = $otherFinance->createGoal(['name' => 'Outra meta', 'amount' => '50,00', 'idempotency_key' => $key()], null, $users['b']);
    $refused(static fn () => $otherWallets->reserve($trip, ['account_id' => $otherAccount, 'amount' => '1,00', 'date' => $today], $users['b']), '12. Outro contexto não reserva na meta alheia');
    $refused(static fn () => $wallets->reserve($trip, ['account_id' => $otherAccount, 'amount' => '1,00', 'date' => $today], $user), '12. Meta não usa conta de outro contexto');
    $refused(static fn () => $wallets->transfer($trip, ['target_goal_id' => $otherGoal, 'account_id' => $account, 'amount' => '1,00', 'date' => $today], $user), '12. Não transfere para meta de outro contexto');
    $otherHistory = $otherWallets->history();
    $check($otherHistory === [] && $otherWallets->reservedByAccount() === [] && $otherFinance->balanceSummary()['reserved_cents'] === 0, '12. Outro contexto não vê reservas');
    $refused(static fn () => $finance->createGoal(['name' => 'X', 'amount' => '1,00', 'account_id' => $otherAccount, 'idempotency_key' => $key()], null, $user), '12. Criar meta com conta alheia é recusado');
    $operation = $wallets->history()[$trip][0]['id'];
    $check($otherWallets->reverse((int) $operation, $users['b']) === false, '12. Outro contexto não estorna operação alheia');

    // 13. Dois aportes simultâneos: só cabe um.
    $race = $finance->createAccount(['name' => 'Corrida', 'type' => 'checking', 'opening_balance' => '100,00']);
    $spawn = static function () use ($context, $home, $race): array {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $context, (string) $home, (string) $race, '80,00'], [1 => ['pipe', 'w']], $pipes);
        return [$process, $pipes[1]];
    };
    $workers = [$spawn(), $spawn(), $spawn()];
    $outcomes = [];
    foreach ($workers as [$process, $pipe]) {
        $outcomes[] = trim((string) stream_get_contents($pipe));
        fclose($pipe);
        proc_close($process);
    }
    sort($outcomes);
    $check($outcomes === ['ok', 'recusado', 'recusado'] && $state($race) === [10000, 8000, 2000], '13. Três aportes simultâneos de R$ 80 em R$ 100: só um passa');
    $wallets->release($home, ['account_id' => $race, 'amount' => '80,00', 'date' => $today], $user);

    // 14. Saldos recarregados de uma conexão nova batem com o histórico.
    $fresh = new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . $database . ';charset=utf8mb4', (string) getenv('DB_USERNAME'), (string) getenv('DB_PASSWORD'));
    $statement = $fresh->prepare('SELECT COALESCE(SUM(delta_cents), 0) FROM finance_goal_wallet_operations WHERE financial_context_id = ?');
    $statement->execute([$context]);
    $check((int) $statement->fetchColumn() === $finance->balanceSummary()['reserved_cents'], '14. Saldo recarregado confere com o histórico');

    // 15. Dashboard, Metas e Contas usam a mesma conta.
    $period = Finance::period([]);
    $overview = $finance->overview($period['start'], $period['end'], $period['compareStart'], $period['compareEnd']);
    $summary = $finance->balanceSummary();
    $goalsReserved = array_sum(array_column($finance->goals(), 'reserved_cents'));
    $check($overview['balance'] === $summary['total_cents'] && $overview['reserved'] === $summary['reserved_cents'] && $overview['available'] === $summary['available_cents'] && $goalsReserved === $summary['reserved_cents'], '15. Dashboard, Metas e Contas mostram os mesmos valores');

    // Aporte fora das contas não afeta o disponível.
    $external = $finance->createGoal(['name' => 'Fora', 'amount' => '500,00', 'initial' => '100,00', 'idempotency_key' => $key()], null, $user);
    $check($goalRow($external)['saved_cents'] === 10000 && $goalRow($external)['reserved_cents'] === 0 && $finance->balanceSummary()['reserved_cents'] === $summary['reserved_cents'], 'Valor inicial sem conta fica fora das contas');

    // Criação atômica: reserva inicial maior que o disponível não deixa meta para trás.
    $before = count($finance->goals());
    $refused(static fn () => $finance->createGoal(['name' => 'Grande', 'amount' => '99.000,00', 'initial' => '90.000,00', 'account_id' => $account, 'idempotency_key' => $key()], null, $user), 'Reserva inicial acima do disponível é recusada');
    $check(count($finance->goals()) === $before, 'Meta não fica criada pela metade');
    $withInitial = $finance->createGoal(['name' => 'Inicial', 'amount' => '200,00', 'initial' => '200,00', 'account_id' => $account, 'idempotency_key' => $key()], null, $user);
    $check($goalRow($withInitial)['reserved_cents'] === 20000 && $goalRow($withInitial)['status'] === 'completed', 'Reserva inicial conclui a meta quando atinge o objetivo');

    // Situação: pausar e concluir.
    $wallets->setPaused($trip, true);
    $refused(static fn () => $wallets->reserve($trip, ['account_id' => $account, 'amount' => '1,00', 'date' => $today], $user), 'Meta pausada não recebe reserva');
    $wallets->release($trip, ['account_id' => $account, 'amount' => '1,00', 'date' => $today], $user);
    $check($goalRow($trip)['status'] === 'paused', 'Resgate não tira a meta da pausa');
    $wallets->setPaused($trip, false);
    $check($goalRow($trip)['status'] === 'active', 'Retomar volta a ativa');
    $wallets->release($withInitial, ['account_id' => $account, 'amount' => '1,00', 'date' => $today], $user);
    $check($goalRow($withInitial)['status'] === 'active', 'Resgate reabre meta concluída');

    // Excluir a meta devolve as reservas. Despesa estornada depois não recria reserva.
    $used = $finance->createGoal(['name' => 'Usada', 'amount' => '500,00', 'initial' => '100,00', 'account_id' => $second, 'idempotency_key' => $key()], null, $user);
    $spent = $wallets->payExpense($used, ['account_id' => $second, 'amount' => '40,00', 'date' => $today, 'description' => 'Compra'], $user);
    $check($finance->deleteGoal($used, $user) && $state($second) === [96000, 0, 96000], 'Excluir meta devolve a reserva às contas');
    $finance->delete($spent['entry_id'], $user);
    $check($state($second) === [100000, 0, 100000], 'Estornar despesa de meta excluída não recria reserva');

    // Aportes programados.
    $check(GoalWallets::nextRun(31, '2027-02-10') === '2027-02-28' && GoalWallets::nextRun(10, '2027-02-10') === '2027-03-10' && GoalWallets::nextRun(10, '2027-02-10', true) === '2027-02-10', 'Próximo aporte ajusta meses curtos');
    $entriesBefore = $results();
    $wallets->saveSchedule($home, ['account_id' => $account, 'amount' => '30,00', 'day' => (int) date('j')], $user, $today);
    $reservedBefore = $state($account)[1];
    $totalBefore = $state($account)[0];
    GoalWallets::processDue($today);
    GoalWallets::processDue($today);
    $check($state($account)[1] === $reservedBefore + 3000 && $state($account)[0] === $totalBefore && $results() === $entriesBefore, 'Aporte programado reserva uma vez, sem despesa e sem mudar o patrimônio');
    $schedule = $wallets->schedules()[$home];
    $check($schedule['next_run_on'] > $today && $schedule['last_result'] === 'done', 'Aporte programado avança para o próximo mês');
    $wallets->setScheduleStatus($home, 'paused');
    $pdo->prepare('UPDATE finance_goal_schedules SET next_run_on = ? WHERE goal_id = ?')->execute([$today, $home]);
    $check(GoalWallets::processDue($today)['done'] === 0, 'Programação pausada não roda');
    $wallets->saveSchedule($home, ['account_id' => $race, 'amount' => '100,01', 'day' => (int) date('j')], $user, $today);
    // Outra data: a chave de cada aporte é por data, e a de hoje já rodou.
    $pdo->prepare('UPDATE finance_goal_schedules SET next_run_on = ? WHERE goal_id = ?')->execute([date('Y-m-d', strtotime('-1 day')), $home]);
    $check(GoalWallets::processDue($today)['skipped'] === 1 && $wallets->schedules()[$home]['last_result'] === 'skipped', 'Sem disponível, o aporte não acontece e fica registrado');
    $wallets->setScheduleStatus($home, 'canceled');
    $check(!isset($wallets->schedules()[$home]), 'Programação cancelada sai da lista');
    echo "Todos os testes de carteiras passaram.\n";
} finally {
    foreach ($users as $id) {
        $context = FinanceContext::forUser($id);
        $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$context]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
