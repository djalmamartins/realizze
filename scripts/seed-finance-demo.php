<?php
declare(strict_types=1);

/**
 * Dados fictícios para testar o app localmente. Recusa qualquer ambiente que não seja local.
 *   php scripts/seed-finance-demo.php usuario@exemplo.com
 */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
use MovesCode\Model\Connection;

if (getenv('APP_ENV') !== 'local') {
    exit("Recusado: disponível apenas com APP_ENV=local.\n");
}
$email = $argv[1] ?? '';
$pdo = Connection::get();
$find = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$find->execute([$email]);
$userId = (int) $find->fetchColumn();
if ($userId === 0) {
    exit("Usuário não encontrado.\n");
}

$seed = static function (int $contextId, array $accountList, array $incomes, array $expenses) use ($pdo, $userId): int {
    $exists = $pdo->prepare('SELECT COUNT(*) FROM finance_accounts WHERE financial_context_id = ?');
    $exists->execute([$contextId]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $accounts = [];
    $insertAccount = $pdo->prepare('INSERT INTO finance_accounts (financial_context_id, name, type, institution, opening_balance_cents) VALUES (?, ?, ?, ?, ?)');
    foreach ($accountList as [$name, $type, $institution, $opening]) {
        $insertAccount->execute([$contextId, $name, $type, $institution, $opening]);
        $accounts[$name] = (int) $pdo->lastInsertId();
    }
    $categories = [];
    $insertCategory = $pdo->prepare('INSERT INTO finance_categories (financial_context_id, name, kind, color) VALUES (?, ?, ?, ?)');
    $palette = ['#078b4c', '#e5484d', '#f59e0b', '#3b82f6', '#8b5cf6', '#94a3b8', '#0ea5a4'];
    foreach (['income' => $incomes, 'expense' => $expenses] as $kind => $rows) {
        foreach (array_values(array_unique(array_column($rows, 3))) as $index => $name) {
            $insertCategory->execute([$contextId, $name, $kind, $palette[$index % 7]]);
            $categories[$kind . $name] = (int) $pdo->lastInsertId();
        }
    }
    $insertEntry = $pdo->prepare(
        'INSERT INTO finance_entries (financial_context_id, kind, description, amount_cents, due_date, competency_date,
            paid_at, account_id, category_id, payment_method, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertSettlement = $pdo->prepare(
        'INSERT INTO finance_entry_settlements (financial_context_id, entry_id, account_id, amount_cents,
            received_at, payment_method, idempotency_key, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $today = new DateTimeImmutable('today');
    $total = 0;
    foreach ([-2, -1, 0, 1] as $offset) {
        $month = $today->modify('first day of this month')->modify("{$offset} month");
        foreach (['income' => $incomes, 'expense' => $expenses] as $kind => $rows) {
            foreach ($rows as [$description, $amount, $day, $category, $account]) {
                $due = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);
                $paid = $due <= $today->modify('-3 days') ? $due->format('Y-m-d') : null;
                $status = $paid !== null ? 'received' : 'pending';
                $insertEntry->execute([
                    $contextId, $kind, $description, $amount, $due->format('Y-m-d'), $due->format('Y-m-01'),
                    $paid, $accounts[$account], $categories[$kind . $category], 'other', $status, $userId,
                ]);
                if ($kind === 'income' && $paid !== null) {
                    $insertSettlement->execute([
                        $contextId, (int) $pdo->lastInsertId(), $accounts[$account], $amount,
                        $paid, 'other', bin2hex(random_bytes(32)), $userId,
                    ]);
                }
                $total++;
            }
        }
    }
    return $total;
};

$created = $seed(
    FinanceContext::forUser($userId),
    [['Nubank', 'checking', 'Nubank', 280000], ['Inter', 'checking', 'Banco Inter', 420000], ['Carteira', 'wallet', null, 115000], ['Visa Final 4242', 'credit_card', 'Visa', 0]],
    [['Salário Empresa Exemplo', 850000, 5, 'Salário', 'Inter'], ['Aluguel Apartamento 101', 200000, 10, 'Aluguel recebido', 'Nubank'], ['Serviço para Cliente A', 85000, 22, 'Serviços', 'Nubank']],
    [['Aluguel e condomínio', 310000, 8, 'Moradia', 'Inter'], ['Supermercado', 92000, 12, 'Alimentação', 'Nubank'], ['Conta de energia', 32000, 15, 'Contas', 'Inter'], ['Plano de celular', 15000, 18, 'Contas', 'Nubank'], ['Combustível', 48000, 20, 'Transporte', 'Visa Final 4242'], ['Restaurante', 27500, 24, 'Lazer', 'Visa Final 4242'], ['Farmácia', 16800, 27, 'Saúde', 'Carteira']]
);
echo "Pessoa física: {$created} lançamentos criados.\n";

foreach ([
    ['Cueca Verde', 1800000, 2400000, 'Itaú'],
    ['Cueca Vermelha', 650000, 980000, 'Bradesco'],
] as [$company, $sales, $opening, $bank]) {
    $find = $pdo->prepare('SELECT id FROM organizations WHERE owner_user_id = ? AND name = ?');
    $find->execute([$userId, $company]);
    $organizationId = (int) $find->fetchColumn() ?: Organizations::create($userId, $company, '');
    $created = $seed(
        FinanceContext::forOrganization($organizationId),
        [["{$bank} PJ", 'checking', $bank, $opening], ['Caixa da loja', 'wallet', null, 150000]],
        [['Vendas no atacado', $sales, 7, 'Vendas', "{$bank} PJ"], ['Vendas na loja', (int) ($sales / 3), 20, 'Vendas', 'Caixa da loja']],
        [['Fornecedor de tecidos', (int) ($sales * .35), 10, 'Fornecedores', "{$bank} PJ"], ['Folha de pagamento', (int) ($sales * .25), 5, 'Pessoal', "{$bank} PJ"], ['Aluguel da loja', 280000, 8, 'Estrutura', "{$bank} PJ"], ['Impostos', (int) ($sales * .08), 20, 'Impostos', "{$bank} PJ"]]
    );
    echo "{$company}: {$created} lançamentos criados.\n";
}
