<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/source/Boot/app.php';
require_once dirname(__DIR__).'/source/Boot/database.php';

use Moves\Services\{Finance,FinanceContext};
use MovesCode\Model\Connection;

$ok=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('FALHOU: '.$message);echo "OK - {$message}\n";};
$pdo=Connection::get();$userId=0;
try{
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status) VALUES('Teste','Transferência',?,'x','active')")->execute(['transfer-'.bin2hex(random_bytes(5)).'@example.invalid']);
    $userId=(int)$pdo->lastInsertId();$finance=new Finance(FinanceContext::forUser($userId));
    $from=$finance->createAccount(['name'=>'Conta A','type'=>'checking','opening_balance'=>'1.000,00']);
    $to=$finance->createAccount(['name'=>'Conta B','type'=>'checking','opening_balance'=>'200,00']);
    $key=bin2hex(random_bytes(32));$data=['account_id'=>$from,'target_account_id'=>$to,'amount'=>'500,00','date'=>date('Y-m-d'),'idempotency_key'=>$key];
    $entry=$finance->transfer($data,$userId);$same=$finance->transfer($data,$userId);
    $balances=array_column($finance->accountsWithBalance(),'balance_cents','id');
    $ok($entry===$same,'Repetir a submissão retorna a mesma transferência');
    $ok($balances[$from]===50000&&$balances[$to]===70000,'R$ 500,00 saem da origem e entram no destino');
    $ok($finance->availableBalance()===120000,'Transferência preserva o patrimônio total');
    $movement=array_values(array_filter($finance->movements(date('Y-m-01'),date('Y-m-t'),'transfer'),static fn(array $row):bool=>(int)$row['id']===$entry))[0]??null;
    $ok($movement!==null&&$movement['account']==='Conta A'&&$movement['target_account']==='Conta B','Histórico identifica origem e destino');
    $ok($finance->delete($entry),'Transferência pode ser excluída de forma consistente');
    $balances=array_column($finance->accountsWithBalance(),'balance_cents','id');
    $ok($balances[$from]===100000&&$balances[$to]===20000,'Exclusão estorna os dois saldos');
}finally{if($userId>0)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);}
