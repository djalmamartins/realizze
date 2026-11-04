<?php
declare(strict_types=1);

/** Valores, validação e isolamento entre contextos, no banco de desenvolvimento, com limpeza ao final. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\Authorization;
use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$ids = [];
$companies = [];
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'F', ?, 'x', 'active')")
            ->execute(['finance-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $ids[$suffix] = (int) $pdo->lastInsertId();
    }
    $check(Finance::parseCents('1.234,56') === 123456 && Finance::parseCents('10') === 1000, 'Valores em reais viram centavos');
    $check(Finance::money(-123456) === '- R$ 1.234,56', 'Formato em real');
    foreach (['abc', '0', '1,234', ''] as $bad) {
        try {
            Finance::parseCents($bad);
            $check(false, "Valor inválido aceito: {$bad}");
        } catch (InvalidArgumentException) {
        }
    }
    echo "OK - Valores inválidos recusados\n";

    $a = new Finance(FinanceContext::forUser($ids['a']));
    $contextB = FinanceContext::forUser($ids['b']);
    $b = new Finance($contextB);
    $pdo->prepare("INSERT INTO finance_accounts (financial_context_id, name) VALUES (?, 'Conta B')")->execute([$contextB]);
    $accountB = (int) $pdo->lastInsertId();
    $entry = $b->createEntry('expense', ['description' => 'Teste', 'amount' => '10,00', 'due_date' => date('Y-m-d'), 'account_id' => $accountB], $ids['b']);

    $month = [date('Y-m-01'), date('Y-m-t')];
    $check($a->entries('expense', ...$month) === [], 'Um contexto não vê lançamentos de outro');
    $check(!$a->settle($entry) && !$a->delete($entry), 'Um contexto não altera nem exclui lançamentos de outro');
    try {
        $a->createEntry('expense', ['description' => 'X', 'amount' => '1,00', 'due_date' => date('Y-m-d'), 'account_id' => $accountB], $ids['a']);
        $check(false, 'Conta de outro contexto aceita');
    } catch (InvalidArgumentException) {
        echo "OK - Conta de outro contexto recusada\n";
    }
    $check($b->settle($entry) && $b->summary('expense', ...$month)['paid'] === 1000, 'Baixa muda o resumo');

    $second = $b->createAccount(['name' => 'Conta B2', 'type' => 'savings', 'opening_balance' => '100,00']);
    $b->transfer(['account_id' => $accountB, 'target_account_id' => $second, 'amount' => '30,00', 'date' => date('Y-m-d')], $ids['b']);
    $balances = array_column($b->accountsWithBalance(), 'balance_cents', 'id');
    $check($balances[$accountB] === -4000 && $balances[$second] === 13000, 'Transferência move saldo entre contas');
    $check($b->availableBalance() === 9000, 'Transferência não altera o saldo total');
    $card = $b->createAccount(['name' => 'Cartão', 'type' => 'credit_card']);
    $cardEntry = $b->createEntry('expense', ['description' => 'Compra', 'amount' => '50,00', 'due_date' => date('Y-m-d'), 'account_id' => $card, 'paid' => '1'], $ids['b']);
    $check($cardEntry > 0 && $b->availableBalance() === 9000, 'Cartão fica fora do saldo disponível');
    try {
        $b->transfer(['account_id' => $accountB, 'target_account_id' => $accountB, 'amount' => '1,00', 'date' => date('Y-m-d')], $ids['b']);
        $check(false, 'Transferência para a mesma conta aceita');
    } catch (InvalidArgumentException) {
        echo "OK - Transferência para a mesma conta recusada\n";
    }
    $check($a->accountsWithBalance() === [], 'Um contexto não vê contas de outro');

    $check(Organizations::validCnpj('11222333000181') && !Organizations::validCnpj('11222333000180'), 'CNPJ validado');
    $green = Organizations::create($ids['a'], 'Empresa Teste', '');
    $check((new Authorization())->can($ids['a'], $green, 'finance.manage') && !(new Authorization())->can($ids['b'], $green, 'organization.view'), 'Dono gerencia a empresa e outro usuário não acessa');
    $company = new Finance(FinanceContext::forOrganization($green));
    $company->createAccount(['name' => 'Conta PJ', 'type' => 'checking', 'opening_balance' => '500,00']);
    $check($company->availableBalance() === 50000 && $a->availableBalance() === 0, 'Dados da empresa ficam separados da pessoa física');
    $companies[] = $green;

    $category = $b->createCategory(['name' => 'Teste', 'category_kind' => 'expense']);
    $b->saveBudget(['category_id' => $category, 'mes' => date('Y-m'), 'amount' => '200,00']);
    $b->createEntry('expense', ['description' => 'Com categoria', 'amount' => '50,00', 'due_date' => date('Y-m-d'), 'account_id' => $accountB, 'category_id' => $category], $ids['b']);
    $budget = array_values(array_filter($b->budgets(date('Y-m')), static fn (array $r): bool => (int) $r['category_id'] === $category))[0];
    $check($budget['budget'] === 20000 && $budget['spent'] === 5000, 'Orçamento compara limite e gasto');
    $check(!$a->deleteCategory($category), 'Um contexto não exclui categoria de outro');
    $goal = $b->createGoal(['name' => 'Reserva', 'amount' => '1.000,00']);
    $check($b->addToGoal($goal, '100,00') && !$a->addToGoal($goal, '1,00') && !$a->deleteGoal($goal), 'Metas ficam no próprio contexto');
    $series = $b->monthlySeries(new DateTimeImmutable('today'));
    $check(count($series) === 6 && $series[date('Y-m')]['expense'] > 0, 'Série mensal de relatórios');
} finally {
    foreach ($companies as $id) {
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$id]);
    }
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
$left = $pdo->query("SELECT COUNT(*) FROM finance_accounts WHERE name = 'Conta B'")->fetchColumn();
$check((int) $left === 0, 'Excluir o usuário remove os dados financeiros dele');
