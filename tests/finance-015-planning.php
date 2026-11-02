<?php
declare(strict_types=1);

/** Planejamento: limites, gasto realizado e previsto, alertas, ano, projeção e isolamento. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

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
$users = [];
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'P', ?, 'x', 'active')")
            ->execute(['plan-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $a = new Finance(FinanceContext::forUser($users['a']));
    $b = new Finance(FinanceContext::forUser($users['b']));
    $month = '2031-03';
    $row = static fn (Finance $f, int $id, string $m = '2031-03'): array => array_values(array_filter($f->budgets($m), static fn (array $r): bool => (int) $r['category_id'] === $id))[0];
    $account = $a->createAccount(['name' => 'Conta', 'type' => 'checking', 'opening_balance' => '1.000,00']);
    $other = $a->createAccount(['name' => 'Outra', 'type' => 'checking']);
    $food = $a->createCategory(['name' => 'Alimentação', 'category_kind' => 'expense']);
    $income = $a->createCategory(['name' => 'Salário', 'category_kind' => 'income']);

    $check($row($a, $food)['budget'] === 0 && $row($a, $food)['spent'] === 0, 'Período sem movimentações fica zerado');
    try {
        $a->saveBudget(['category_id' => $income, 'mes' => $month, 'amount' => '100,00']);
        $check(false, 'Orçamento em categoria de receita aceito');
    } catch (InvalidArgumentException) {
        echo "OK - Categoria de receita não recebe orçamento\n";
    }
    $a->saveBudget(['category_id' => $food, 'mes' => $month, 'amount' => '1.000,00']);
    $check($row($a, $food)['budget'] === 100000, 'Cria orçamento');
    $a->saveBudget(['category_id' => $food, 'mes' => $month, 'amount' => '500,00']);
    $check($row($a, $food)['budget'] === 50000, 'Edita orçamento sem duplicar');

    $expense = static fn (string $amount, bool $paid = false) => $a->createEntry('expense', ['description' => 'Mercado', 'amount' => $amount, 'due_date' => '2031-03-10', 'account_id' => $account, 'category_id' => $food] + ($paid ? ['paid' => '1'] : []), $users['a']);
    $expense('100,00', true);
    $expense('50,00');
    $current = $row($a, $food);
    $check($current['realized'] === 10000 && $current['pending'] === 5000 && $current['spent'] === 15000, 'Despesa atualiza realizado e previsto');
    $check(Finance::budgetState($current['budget'], $current['spent']) === 'ok', 'Dentro do orçamento');
    $check(Finance::budgetState(100, 80) === 'near' && Finance::budgetState(100, 79) === 'ok' && Finance::budgetState(100, 100) === 'near' && Finance::budgetState(100, 101) === 'over' && Finance::budgetState(0, 50) === null, 'Limites do alerta: 80%, 100% e acima');

    // Transferência e lançamento cancelado não contam.
    $a->transfer(['account_id' => $account, 'target_account_id' => $other, 'amount' => '300,00', 'date' => '2031-03-10', 'due_date' => '2031-03-10'], $users['a']);
    $cancelled = $expense('999,00');
    $pdo->prepare("UPDATE finance_entries SET status = 'cancelled' WHERE id = ?")->execute([$cancelled]);
    $check($row($a, $food)['spent'] === 15000, 'Transferência e cancelado ficam de fora');
    $expense('400,00');
    $check(Finance::budgetState($row($a, $food)['budget'], $row($a, $food)['spent']) === 'over', 'Orçamento excedido é sinalizado');

    // Projeção.
    $a->createEntry('income', ['description' => 'Salário', 'amount' => '2.000,00', 'due_date' => '2031-03-05', 'account_id' => $account, 'paid' => '1'], $users['a']);
    $a->createEntry('income', ['description' => 'Extra', 'amount' => '200,00', 'due_date' => '2031-03-25', 'account_id' => $account], $users['a']);
    $plan = $a->planProjection('2031-03-01', '2031-03-31');
    $check($plan['income_realized'] === 200000 && $plan['income_expected'] === 20000, 'Receita realizada separada da prevista');
    $check($plan['expense_realized'] === 10000 && $plan['expense_expected'] === 45000, 'Despesa realizada separada da prevista');
    $check($plan['result_realized'] === 190000 && $plan['result_projected'] === 165000, 'Resultado realizado e projetado');

    // Mês seguinte, ano e cópia.
    $check($a->copyPreviousBudgets('2031-04') === 1 && $row($a, $food, '2031-04')['budget'] === 50000, 'Copia os limites do mês anterior');
    $check($a->copyPreviousBudgets('2031-04') === 0, 'Copiar de novo não duplica');
    $a->saveBudget(['category_id' => $food, 'mes' => '2031-04', 'amount' => '700,00']);
    $a->copyPreviousBudgets('2031-04');
    $check($row($a, $food, '2031-04')['budget'] === 70000, 'Cópia não sobrescreve limite já definido');

    // Isolamento.
    try {
        $b->saveBudget(['category_id' => $food, 'mes' => $month, 'amount' => '1,00']);
        $check(false, 'Outro usuário gravou orçamento alheio');
    } catch (InvalidArgumentException) {
        echo "OK - Outro usuário não define orçamento alheio\n";
    }
    $check(!$b->deleteBudget($food, $month) && $row($a, $food)['budget'] === 50000, 'Outro usuário não exclui orçamento alheio');
    $check($b->planProjection('2031-03-01', '2031-03-31')['expense_expected'] === 0, 'Projeção não mistura usuários');

    $check($a->deleteBudget($food, $month) && $row($a, $food)['budget'] === 0 && $row($a, $food)['spent'] === 55000, 'Exclui o orçamento e mantém os lançamentos');
    $check(!$a->deleteBudget($food, $month), 'Excluir de novo não encontra nada');
} finally {
    foreach ($users as $id) {
        $context = FinanceContext::forUser($id);
        $pdo->prepare('DELETE FROM finance_entries WHERE financial_context_id = ?')->execute([$context]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
echo "Todos os testes do Planejamento passaram.\n";
