<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/source/Boot/app.php';
require_once dirname(__DIR__).'/source/Boot/database.php';

use Moves\Services\{Finance,FinanceContext,Receivables,RecurringFinance};
use MovesCode\Model\Connection;

$ok=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('FALHOU: '.$message);echo "OK - {$message}\n";};
$pdo=Connection::get();$userId=0;
if($pdo->query("SHOW TABLES LIKE 'finance_recurring_occurrences'")->fetchColumn()===false){echo "SKIP - migration 011 não aplicada; cenário automático não pode ser validado.\n";exit(0);}
try{
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES('Teste','Recorrência',?,'x','active')")->execute(['recurring-'.bin2hex(random_bytes(5)).'@example.invalid']);
    $userId=(int)$pdo->lastInsertId();$context=FinanceContext::forUser($userId);$finance=new Finance($context);
    $account=$finance->createAccount(['name'=>'Conta recorrente','type'=>'checking','opening_balance'=>'1.000,00']);
    $service=new RecurringFinance($context);$today=date('Y-m-d');
    $expense=$service->create(['item_type'=>'subscription','kind'=>'expense','name'=>'Assinatura hoje','amount'=>'100,00','frequency'=>'monthly','next_due_date'=>$today,'account_id'=>$account,'auto_create'=>'1'],$userId);
    $rows=$finance->entries('expense',$today,$today,'all');
    $ok(count(array_filter($rows,static fn(array $row):bool=>$row['description']==='Assinatura hoje'))===1,'Assinatura com vencimento hoje aparece em Contas a Pagar');
    $service->generateDue(new DateTimeImmutable('today'),100);$service->generateDue(new DateTimeImmutable('today'),100);
    $count=(int)$pdo->query("SELECT COUNT(*) FROM finance_entries WHERE financial_context_id={$context} AND description='Assinatura hoje'")->fetchColumn();
    $ok($count===1,'Processar recorrências duas vezes não duplica lançamento');
    $entry=(int)array_values(array_filter($rows,static fn(array $row):bool=>$row['description']==='Assinatura hoje'))[0]['id'];
    $ok($finance->availableBalance()===100000,'Previsão não altera saldo antes do pagamento');
    $ok($finance->settle($entry)&&$finance->availableBalance()===90000,'Pagamento da assinatura atualiza o saldo da conta');
    $service->create(['item_type'=>'recurring','kind'=>'income','name'=>'Receita recorrente','amount'=>'250,00','frequency'=>'monthly','next_due_date'=>$today,'account_id'=>$account,'auto_create'=>'1'],$userId);
    $receivables=(new Receivables($context))->listing(['start'=>$today,'end'=>$today,'page'=>1,'per_page'=>20]);
    $ok(count(array_filter($receivables['rows'],static fn(array $row):bool=>$row['description']==='Receita recorrente'))===1,'Receita recorrente aparece em Contas a Receber');
    $item=$service->listing('active');$next=array_values(array_filter($item,static fn(array $row):bool=>(int)$row['id']===$expense))[0]['next_due_date']??'';
    $ok($next>$today,'Próximo vencimento mensal avança para o futuro');
}finally{if($userId>0){$pdo->prepare('DELETE r FROM finance_recurring_items r JOIN financial_contexts c ON c.id=r.financial_context_id WHERE c.personal_user_id=?')->execute([$userId]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);}}
