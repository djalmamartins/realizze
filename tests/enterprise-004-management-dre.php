<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CardInvoices;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\ManagementDre;
use Moves\Services\AppUrl;
use Moves\Services\Receivables;
use MovesCode\Model\Connection;

$database = (string) getenv('DB_DATABASE');
if (!in_array($database, ['realizze_migration014_20261103', 'realizze_pjtest'], true)) {
    exit("Recusado: banco isolado conhecido obrigatório.\n");
}
$ok = static function (bool $value, string $message): void {
    if (!$value) throw new RuntimeException('FALHOU: ' . $message);
    echo "OK - {$message}\n";
};
$key = static fn (): string => bin2hex(random_bytes(32));
$pdo = Connection::get();
$users = [];

try {
    foreach (['Dre', 'Isolado'] as $name) {
        $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES(?,'Teste',?,'x','active')")
            ->execute([$name, strtolower($name) . '-' . bin2hex(random_bytes(6)) . '@example.invalid']);
        $users[] = (int) $pdo->lastInsertId();
    }
    $user = $users[0];
    $ctx = FinanceContext::forUser($user);
    $finance = new Finance($ctx);
    $account = $finance->createAccount(['name' => 'Banco', 'type' => 'checking']);
    $card = $finance->createAccount(['name' => 'Cartão', 'type' => 'credit_card', 'closing_day' => '20', 'due_day' => '5']);
    $incomeCategory = $finance->createCategory(['name' => 'Vendas', 'category_kind' => 'income', 'color' => '#000000']);
    $costCategory = $finance->createCategory(['name' => 'Custos diretos', 'category_kind' => 'expense', 'color' => '#000000']);
    $expenseCategory = $finance->createCategory(['name' => 'Administrativo', 'category_kind' => 'expense', 'color' => '#000000']);
    $deductionCategory = $finance->createCategory(['name' => 'Taxa comercial', 'category_kind' => 'expense', 'color' => '#000000']);
    $receivables = new Receivables($ctx);
    $income = (int) $receivables->create([
        'description' => 'Venda competência', 'amount' => '1.000,00', 'due_date' => '2033-01-10',
        'competence' => '2033-01', 'account_id' => $account, 'category_id' => $incomeCategory,
        'payment_method' => 'pix', 'idempotency_key' => $key(),
    ], $user)['id'];
    $expense = $finance->createEntry('expense', [
        'description' => 'Custo', 'amount' => '400,00', 'due_date' => '2033-01-12',
        'account_id' => $account, 'category_id' => $costCategory,
    ], $user);
    $finance->createEntry('expense', [
        'description' => 'Admin', 'amount' => '100,00', 'due_date' => '2033-01-12',
        'account_id' => $account, 'category_id' => $expenseCategory,
    ], $user);
    $finance->createEntry('expense', [
        'description' => 'Dedução configurada', 'amount' => '50,00', 'due_date' => '2033-01-12',
        'account_id' => $account, 'category_id' => $deductionCategory,
    ], $user);
    $receivables->settle($income, [
        'amount' => '300,00', 'received_at' => '2033-02-02', 'account_id' => $account,
        'payment_method' => 'pix', 'idempotency_key' => $key(),
    ], $user);
    $finance->settleExpense($expense, [
        'amount' => '200,00', 'paid_at' => '2033-02-02', 'account_id' => $account,
        'payment_method' => 'pix', 'idempotency_key' => bin2hex(random_bytes(16)),
    ], $user);

    $cards = new CardInvoices($ctx);
    $cards->purchase([
        'card_account_id' => $card, 'description' => 'Insumo no cartão', 'amount' => '120,00',
        'purchased_on' => '2033-01-10', 'installments' => '1', 'category_id' => $costCategory,
        'idempotency_key' => $key(),
    ], $user);
    $payment = $cards->pay([
        'card_account_id' => $card, 'source_account_id' => $account, 'month' => '2033-01',
        'paid_on' => '2033-02-05', 'idempotency_key' => $key(),
    ], $user);

    $dre = new ManagementDre($ctx);
    $ok($dre->classificationReady(), 'Migration de classificação gerencial está disponível no banco isolado');
    $ok($dre->saveCategoryClassification($deductionCategory, 'deductions'), 'Categoria recebe grupo gerencial explícito');
    $competence = $dre->report('2033-01-01', '2033-01-31');
    $cash = $dre->report('2033-02-01', '2033-02-28', 'cash');
    $ok($competence['groups']['gross']['total'] === 100000
        && $competence['groups']['costs']['total'] === 40000
        && $competence['groups']['operating']['total'] === 10000
        && $competence['groups']['deductions']['total'] === 5000,
        'Competência reconhece obrigações completas no mês');
    $classified=array_values(array_filter($dre->categoryClassifications(),static fn(array$row):bool=>$row['id']===$deductionCategory));
    $ok(count($classified)===1&&$classified[0]['configured_group']==='deductions','Consulta expõe classificação configurada da categoria');
    try{$dre->saveCategoryClassification($deductionCategory,'gross');$ok(false,'Grupo incompatível deveria ser recusado');}catch(InvalidArgumentException){$ok(true,'Grupo incompatível com despesa é recusado');}
    $custom=AppUrl::periodFromSegments('dre','2033-01-10','2033-02-08');
    $ok($custom===['inicio'=>'2033-01-10','fim'=>'2033-02-08'],'DRE aceita período personalizado em URL canônica');
    $ok($cash['groups']['gross']['total'] === 30000
        && $cash['groups']['costs']['total'] === 32000,
        'Caixa usa baixas parciais e pagamento categorizado da fatura');
    $cardRows = array_filter($cash['groups']['costs']['rows'], static fn (array $row): bool => $row['source'] === 'card_payment');
    $ok(count($cardRows) === 1 && (int) array_values($cardRows)[0]['amount_cents'] === 12000,
        'Pagamento da fatura preserva categoria, valor e origem rastreável');

    $today = date('Y-m-d');
    $finance->createEntry('expense', [
        'description' => 'Despesa à vista', 'amount' => '45,00', 'due_date' => $today,
        'account_id' => $account, 'category_id' => $expenseCategory, 'paid' => '1',
    ], $user);
    $current = $dre->report(date('Y-m-01'), date('Y-m-t'), 'cash');
    $ok($current['groups']['operating']['total'] === 4500,
        'Despesa paga diretamente, sem tabela de baixas, aparece no caixa');

    $cards->reversePayment($payment, $user);
    $reversed = $dre->report('2033-02-01', '2033-02-28', 'cash');
    $ok($reversed['groups']['costs']['total'] === 20000,
        'Estorno de fatura remove somente a parcela paga do caixa');

    $other = new ManagementDre(FinanceContext::forUser($users[1]));
    $ok(!$other->saveCategoryClassification($deductionCategory,'costs'),'Outro contexto não altera classificação da categoria');
    $isolated = $other->report('2033-01-01', '2033-02-28');
    $ok($isolated['result'] === 0, 'Outro contexto financeiro não enxerga a DRE');
    $ok($dre->report('2033-02-01', '2033-02-28', 'unknown')['criterion'] === 'competence',
        'Critério desconhecido mantém o regime padrão de competência');
    try {
        $dre->report('2033-02-31', '2033-03-01');
        $ok(false, 'Data civil inválida deveria ser recusada');
    } catch (InvalidArgumentException) {
        $ok(true, 'Data civil inválida é recusada');
    }
} finally {
    foreach ($users as $userId) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
    }
}
