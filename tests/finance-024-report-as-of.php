<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use MovesCode\Model\Connection;

$pdo=Connection::get();
if(!str_contains((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'test'))throw new RuntimeException('Banco de teste obrigatório.');
$failed=0;
$check=static function(bool$value,string$message)use(&$failed):void{echo($value?'OK':'FALHOU')." - {$message}\n";if(!$value)$failed++;};
$pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES('DataBase','Teste',?,'x','active')")
    ->execute(['database-'.bin2hex(random_bytes(5)).'@example.invalid']);
$user=(int)$pdo->lastInsertId();
$context=FinanceContext::forUser($user);

try{
    $finance=new Finance($context);
    $account=$finance->createAccount(['name'=>'Conta histórica','type'=>'checking']);
    $receivables=new Receivables($context);
    $key=static fn():string=>bin2hex(random_bytes(32));
    $income=(int)$receivables->create([
        'description'=>'Receita janeiro','amount'=>'1.000,00','due_date'=>'2025-01-10','competence'=>'2025-01',
        'account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>$key(),
    ],$user)['id'];
    $receivables->settle($income,['amount'=>'300,00','received_at'=>'2025-01-20','account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>$key()],$user);
    $receivables->settle($income,['amount'=>'200,00','received_at'=>'2025-02-05','account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>$key()],$user);
    $expense=$finance->createEntry('expense',['description'=>'Despesa janeiro','amount'=>'500,00','due_date'=>'2025-01-12','account_id'=>$account],$user);
    $finance->settleExpense($expense,['amount'=>'150,00','paid_at'=>'2025-01-25','account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>bin2hex(random_bytes(16))],$user);
    $finance->settleExpense($expense,['amount'=>'100,00','paid_at'=>'2025-02-06','account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>bin2hex(random_bytes(16))],$user);
    $finance->createEntry('expense',['description'=>'Despesa à vista','amount'=>'50,00','due_date'=>'2025-01-05','account_id'=>$account,'paid'=>'1'],$user);

    $report=$finance->report('2025-01-01','2025-01-31');
    $check($report['as_of']==='2025-01-31','Período histórico usa o último dia como data-base');
    $check($report['totals']['income']===100000&&$report['totals']['income_paid']===30000&&$report['totals']['income_overdue']===70000,'Receber preserva baixa parcial e saldo histórico');
    $check($report['totals']['expense']===55000&&$report['totals']['expense_paid']===20000&&$report['totals']['expense_overdue']===35000,'Pagar combina pagamento direto e baixa parcial até a data-base');
    $entries=[];foreach($report['days']as$day)foreach($day['entries']as$row)$entries[$row['description']]=$row;
    $check($entries['Receita janeiro']['paid_cents']===30000&&$entries['Receita janeiro']['open_cents']===70000,'Linha de receita expõe realizado e aberto');
    $check($entries['Despesa janeiro']['paid_cents']===15000&&$entries['Despesa janeiro']['open_cents']===35000,'Linha de despesa ignora baixa posterior');

    $export=[];foreach($finance->exportRows('2025-01-01','2025-01-31')as$row)$export[$row['description']]=$row;
    $check($export['Receita janeiro']['paid_cents']===30000&&$export['Receita janeiro']['open_cents']===70000&&$export['Receita janeiro']['display_status']==='partial_overdue','CSV usa a mesma posição histórica do relatório');
}finally{
    foreach(['finance_entry_audit_logs','finance_entry_settlements','finance_entries','finance_categories','finance_accounts']as$table)$pdo->prepare("DELETE FROM {$table} WHERE financial_context_id=?")->execute([$context]);
    $pdo->prepare('DELETE FROM financial_contexts WHERE id=?')->execute([$context]);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user]);
}
echo$failed===0?"FIN-024: POSIÇÃO HISTÓRICA APROVADA.\n":"FIN-024: {$failed} FALHA(S).\n";
exit($failed===0?0:1);
