<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/source/Boot/app.php';
require_once dirname(__DIR__).'/source/Boot/database.php';

use Moves\Services\{Finance,FinanceContext,Receivables};
use MovesCode\Model\Connection;

if(!str_contains((string)getenv('DB_DATABASE'),'test')){exit("Recusado: use um banco com test no nome.\n");}
$ok=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('FALHOU: '.$message);echo "OK - {$message}\n";};
$pdo=Connection::get();$userId=0;
try{
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES('Teste','Baixa',?,'x','active')")->execute(['baixa-'.bin2hex(random_bytes(6)).'@example.invalid']);
    $userId=(int)$pdo->lastInsertId();$contextId=FinanceContext::forUser($userId);$finance=new Finance($contextId);$receivables=new Receivables($contextId);
    $a=$finance->createAccount(['name'=>'Conta A','type'=>'checking','opening_balance'=>'100,00']);
    $b=$finance->createAccount(['name'=>'Conta B','type'=>'checking']);
    $balances=static fn():array=>array_column($finance->accountsWithBalance(),'balance_cents','id');
    $entry=(int)$receivables->create(['description'=>'Serviço','amount'=>'1.000,00','due_date'=>date('Y-m-d'),'competence'=>date('Y-m'),'account_id'=>$a,'payment_method'=>'pix','idempotency_key'=>bin2hex(random_bytes(32))],$userId)['id'];
    $ok($balances()[$a]===10000,'Recebimento em aberto não muda o saldo');
    $settle=static fn(string $amount,int $account)=>$receivables->settle($entry,['amount'=>$amount,'received_at'=>date('Y-m-d'),'account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>bin2hex(random_bytes(32))],$userId);
    $settle('400,00',$a);
    $ok($balances()[$a]===50000,'Baixa parcial entra no saldo da conta');
    $settle('600,00',$b);
    $now=$balances();
    $ok($now[$a]===50000&&$now[$b]===60000,'Quitação não soma o valor inteiro e respeita a conta de cada baixa');
    $paid=$finance->createEntry('income',['description'=>'À vista','amount'=>'50,00','due_date'=>date('Y-m-d'),'account_id'=>$a,'paid'=>'1'],$userId);
    $ok($balances()[$a]===55000,'Receita já recebida conta uma vez só');
    $finance->delete($paid,$userId);
    $ok($balances()[$a]===50000,'Excluir a receita tira a baixa do saldo');
}finally{if($userId>0)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);}
