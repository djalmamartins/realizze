<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/source/Boot/app.php'; require_once dirname(__DIR__).'/source/Boot/database.php';
use Moves\App\ContactActions; use Moves\Services\Contacts; use Moves\Services\Finance; use Moves\Services\FinanceContext; use Moves\Services\Receivables; use MovesCode\Model\Connection;
$ok=static function(bool $v,string $m):void{if(!$v)throw new RuntimeException('FALHOU: '.$m);echo "OK - {$m}\n";};
$bad=static function(callable $f,string $m)use($ok):void{try{$f();$ok(false,$m);}catch(InvalidArgumentException){echo "OK - {$m}\n";}};
$pdo=Connection::get();$users=[];$ids=[];
try{
 foreach(['a','b'] as $s){$email='contacts-'.bin2hex(random_bytes(5)).$s.'@example.invalid';$pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES('Teste','Contato',?,'x','active')")->execute([$email]);$users[$s]=(int)$pdo->lastInsertId();}
 $ca=FinanceContext::forUser($users['a']);$cb=FinanceContext::forUser($users['b']);$a=new Contacts($ca);$b=new Contacts($cb);
 $base=['person_type'=>'individual','legal_name'=>'Maria da Silva','document'=>'529.982.247-25','email'=>'maria@example.invalid','mobile'=>'11999999999','is_customer'=>'1','status'=>'active'];
 $pf=$a->create($base,$users['a']);$ids[]=$pf;$ok($a->find($pf)['document_value']==='52998224725','Cadastro de cliente PF e normalização do CPF');
 $pj=$a->create(['person_type'=>'company','legal_name'=>'Empresa Teste Ltda','trade_name'=>'Empresa Teste','document'=>'11.222.333/0001-81','is_supplier'=>'1','email'=>'empresa@example.invalid'],$users['a']);$ids[]=$pj;$ok($a->find($pj)['is_supplier']==1,'Cadastro de fornecedor PJ');
 $both=$a->create(['person_type'=>'company','legal_name'=>'Parceiro Duplo Ltda','document'=>'45.723.174/0001-10','is_customer'=>'1','is_supplier'=>'1'],$users['a']);$ids[]=$both;$ok($a->find($both)['is_customer']==1&&$a->find($both)['is_supplier']==1,'Contato possui os dois papéis');
 $bad(fn()=>$a->create(array_merge($base,['document'=>'111.111.111-11','legal_name'=>'CPF ruim']),$users['a']),'CPF inválido recusado');
 $bad(fn()=>$a->create(['person_type'=>'company','legal_name'=>'CNPJ ruim','document'=>'11.111.111/1111-11','is_supplier'=>'1'],$users['a']),'CNPJ inválido recusado');
 $bad(fn()=>$a->create(array_merge($base,['legal_name'=>'Duplicado']),$users['a']),'Documento duplicado no contexto recusado');
 $sameOther=$b->create(array_merge($base,['legal_name'=>'Mesmo CPF outro contexto']),$users['b']);$ok($sameOther>0,'Mesmo documento permitido em outro contexto');
 $a->update($pf,array_merge($base,['legal_name'=>'Maria Atualizada','is_supplier'=>'1']),$users['a']);$ok($a->find($pf)['legal_name']==='Maria Atualizada'&&$a->find($pf)['is_supplier']==1,'Edição preserva e atualiza papéis');
 $bad(fn()=>$b->find($pf),'Outro usuário não visualiza contato');$bad(fn()=>$b->update($pf,$base,$users['b']),'Outro usuário não edita contato');
 foreach(range(1,4) as $n)$a->create(['person_type'=>'individual','legal_name'=>'Contato Paginação '.$n,'is_supplier'=>'1'],$users['a']);
 $ok($a->listing(['search'=>'Maria'])['total']===1,'Pesquisa por nome');$ok($a->listing(['role'=>'both'])['total']===2,'Filtro de ambos os papéis');$ok($a->listing(['per_page'=>5])['pages']===2,'Paginação no servidor');
 $finance=new Finance($ca);$account=$finance->createAccount(['name'=>'Conta Contatos','type'=>'checking']);$pdo->prepare("INSERT INTO finance_categories(financial_context_id,name,kind)VALUES(?,'Serviços contato','income')")->execute([$ca]);$category=(int)$pdo->lastInsertId();$r=new Receivables($ca);
 $payload=['description'=>'Receita vinculada','amount'=>'100,00','due_date'=>date('Y-m-d'),'competence'=>date('Y-m'),'payment_method'=>'pix','account_id'=>$account,'category_id'=>$category,'idempotency_key'=>bin2hex(random_bytes(16)),'contact_id'=>$pf];
 $entry=$r->create($payload,$users['a']);$listed=$r->listing(['start'=>date('Y-m-01'),'end'=>date('Y-m-t'),'contact_id'=>$pf]);$ok($listed['total']===1&&$listed['rows'][0]['counterparty_name']==='Maria Atualizada','Recebimento vincula e exibe cliente real');
 $legacy=$r->create(array_merge($payload,['description'=>'Legado','contact_id'=>'','counterparty_name'=>'Cliente legado','idempotency_key'=>bin2hex(random_bytes(16))]),$users['a']);$ok($legacy['id']>0&&$r->listing(['start'=>date('Y-m-01'),'end'=>date('Y-m-t'),'counterparty'=>'Cliente legado'])['total']===1,'Texto livre antigo permanece compatível');
 $bad(fn()=>$r->create(array_merge($payload,['contact_id'=>$sameOther,'idempotency_key'=>bin2hex(random_bytes(16))]),$users['a']),'Cliente de outro contexto não pode ser vinculado');
 $a->setActive($pf,false,$users['a']);$ok($a->financialSummary($pf)['entries']===1,'Inativação preserva vínculo financeiro');
 $bad(fn()=>$r->create(array_merge($payload,['idempotency_key'=>bin2hex(random_bytes(16))]),$users['a']),'Cliente inativo não entra em novo recebimento');
 $ok(count($pdo->query('SELECT action FROM finance_contact_audit_logs WHERE contact_id='.(int)$pf)->fetchAll())>=3,'Criação, edição e inativação auditadas');
 $route=file_get_contents(dirname(__DIR__).'/container/app/realizze/v1/routes.php');$ok(str_contains($route,"AccessPolicy::requireAccess('app')")&&str_contains($route,'ContactActions'),'Rotas protegidas por autenticação e action CSRF');
 $_SESSION=['auth_user_id'=>$users['a'],'auth_version'=>1];$_POST=['csrf_token'=>'invalido'];http_response_code(200);(new ContactActions())->handle('create');$ok(http_response_code()===403,'CSRF inválido bloqueia a action');$_POST=[];$_SESSION=[];
 echo "FINANCE-003: CONTATOS APROVADO.\n";
}finally{foreach($users as $id){$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}}
