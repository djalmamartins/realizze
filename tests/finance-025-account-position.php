<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/source/Boot/app.php';
require_once dirname(__DIR__).'/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use MovesCode\Model\Connection;

$pdo=Connection::get();
if(!str_contains((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'test'))throw new RuntimeException('Banco de teste obrigatório.');
$failed=0;$check=static function(bool$value,string$message)use(&$failed):void{echo($value?'OK':'FALHOU')." - {$message}\n";if(!$value)$failed++;};
$users=[];foreach(['Posicao','PosicaoOutro']as$name){$pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES(?,'Teste',?,'x','active')")->execute([$name,strtolower($name).'-'.bin2hex(random_bytes(5)).'@example.invalid']);$users[]=(int)$pdo->lastInsertId();}
$contexts=[FinanceContext::forUser($users[0]),FinanceContext::forUser($users[1])];
try{
    $finance=new Finance($contexts[0]);
    $bank=$finance->createAccount(['name'=>'Banco A','type'=>'checking','opening_balance'=>'1.000,00','institution'=>'Banco Teste']);
    $wallet=$finance->createAccount(['name'=>'Carteira B','type'=>'wallet','opening_balance'=>'200,00']);
    $card=$finance->createAccount(['name'=>'Cartão','type'=>'credit_card','closing_day'=>'20','due_day'=>'5']);
    $receivables=new Receivables($contexts[0]);$key=static fn():string=>bin2hex(random_bytes(32));
    $income=(int)$receivables->create(['description'=>'Receita','amount'=>'300,00','due_date'=>'2034-02-10','competence'=>'2034-02','account_id'=>$bank,'payment_method'=>'pix','idempotency_key'=>$key()],$users[0])['id'];
    $receivables->settle($income,['amount'=>'300,00','received_at'=>'2034-02-10','account_id'=>$bank,'payment_method'=>'pix','idempotency_key'=>$key()],$users[0]);
    $expense=$finance->createEntry('expense',['description'=>'Despesa','amount'=>'150,00','due_date'=>'2034-02-12','account_id'=>$bank],$users[0]);
    $finance->settleExpense($expense,['amount'=>'150,00','paid_at'=>'2034-02-12','account_id'=>$bank,'payment_method'=>'pix','idempotency_key'=>bin2hex(random_bytes(16))],$users[0]);
    $finance->transfer(['account_id'=>$bank,'target_account_id'=>$wallet,'amount'=>'200,00','date'=>'2034-02-15','idempotency_key'=>$key()],$users[0]);
    $finance->transfer(['account_id'=>$bank,'target_account_id'=>$card,'amount'=>'100,00','date'=>'2034-02-20','idempotency_key'=>$key()],$users[0]);

    $opening=$finance->cashAccountsPositionOn('2034-01-31');
    $closing=$finance->cashAccountsPositionOn('2034-02-28');
    $openingById=array_column($opening,'balance_cents','id');$closingById=array_column($closing,'balance_cents','id');
    $check($openingById[$bank]===100000&&$openingById[$wallet]===20000,'Saldo inicial vem das contas existentes na data-base');
    $check($closingById[$bank]===85000&&$closingById[$wallet]===40000,'Posição por conta aplica baixas e transferências na conta correta');
    $check(array_sum($openingById)===120000&&array_sum($closingById)===125000,'Transferência interna é neutra e pagamento ao cartão reduz o caixa');
    $check(!isset($closingById[$card])&&$finance->cashBalanceOn('2034-02-28')===125000,'Cartão e limite não são apresentados como saldo de caixa');
    $check((new Finance($contexts[1]))->cashAccountsPositionOn('2034-02-28')===[],'Outro contexto não acessa a posição');
    try{$finance->cashAccountsPositionOn('2034-02-31');$check(false,'Data inválida deveria falhar');}catch(InvalidArgumentException){$check(true,'Data civil inválida é recusada');}
}finally{
    foreach($contexts as$context){foreach(['finance_entry_audit_logs','finance_entry_settlements','finance_entries','finance_categories','finance_accounts']as$table)$pdo->prepare("DELETE FROM {$table} WHERE financial_context_id=?")->execute([$context]);$pdo->prepare('DELETE FROM financial_contexts WHERE id=?')->execute([$context]);}
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute($users);
}
echo$failed===0?"FIN-025: POSIÇÃO DE CONTAS APROVADA.\n":"FIN-025: {$failed} FALHA(S).\n";exit($failed===0?0:1);
