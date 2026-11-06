<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\BillingReport;
use Moves\Services\Contacts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Organizations;
use Moves\Services\Receivables;
use Moves\Services\ServiceOrders;
use MovesCode\Model\Connection;

$pdo = Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(),'test')) {
    throw new RuntimeException('Banco de teste obrigatório.');
}
$failed=0;
$check=static function(bool $value,string $message)use(&$failed):void{
    echo($value?'OK':'FALHOU')." - {$message}\n";
    if(!$value)$failed++;
};
$newUser=static function(string $name)use($pdo):int{
    $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES(?,'Teste',?,'x','active')")
        ->execute([$name,strtolower($name).'-'.bin2hex(random_bytes(5)).'@example.invalid']);
    return(int)$pdo->lastInsertId();
};
$key=static fn():string=>bin2hex(random_bytes(32));
$owner=$newUser('Faturamento');
$other=$newUser('FaturamentoOutro');
$org=Organizations::create($owner,'Faturamento Alfa','');
$alien=Organizations::create($other,'Faturamento Beta','');
$contexts=[FinanceContext::forOrganization($org),FinanceContext::forOrganization($alien)];

try{
    $finance=new Finance($contexts[0]);
    $account=$finance->createAccount(['name'=>'Conta cobrança','type'=>'checking']);
    $category=$finance->createCategory(['name'=>'Serviços','category_kind'=>'income','color'=>'#16a34a']);
    $customer=(new Contacts($contexts[0]))->create(['person_type'=>'company','legal_name'=>'Cliente Faturamento','is_customer'=>'1','status'=>'active'],$owner);
    $orders=new ServiceOrders($org);
    $order=$orders->create([
        'customer_id'=>$customer,'description'=>'Implantação','due_date'=>'2034-03-20','discount'=>'20,00',
        'item_type'=>['service'],'item_description'=>['Consultoria'],'item_quantity'=>['1'],
        'item_unit_price'=>['500,00'],'item_discount'=>['0'],'idempotency_key'=>bin2hex(random_bytes(16)),
    ],$owner);
    $charge=$orders->generateReceivable($order['id'],[
        'due_date'=>'2034-03-20','account_id'=>$account,'category_id'=>$category,'payment_method'=>'pix',
    ],$owner);
    $receivables=new Receivables($contexts[0]);
    $manual=(int)$receivables->create([
        'description'=>'Mensalidade março','amount'=>'1.000,00','due_date'=>'2034-03-15','competence'=>'2034-03',
        'account_id'=>$account,'category_id'=>$category,'counterparty_name'=>'Cliente manual','payment_method'=>'pix','idempotency_key'=>$key(),
    ],$owner)['id'];
    $prior=(int)$receivables->create([
        'description'=>'Mensalidade fevereiro','amount'=>'50,00','due_date'=>'2034-02-20','competence'=>'2034-02',
        'account_id'=>$account,'category_id'=>$category,'counterparty_name'=>'Cliente anterior','payment_method'=>'pix','idempotency_key'=>$key(),
    ],$owner)['id'];
    $cancelled=(int)$receivables->create([
        'description'=>'Cobrança cancelada','amount'=>'70,00','due_date'=>'2034-03-25','competence'=>'2034-03',
        'account_id'=>$account,'category_id'=>$category,'payment_method'=>'pix','idempotency_key'=>$key(),
    ],$owner)['id'];
    $receivables->cancel($cancelled,'Cancelamento para teste',$owner);
    $settle=static function(int $entry,string $amount,string $date)use($receivables,$account,$owner,$key):void{
        $receivables->settle($entry,['amount'=>$amount,'received_at'=>$date,'account_id'=>$account,'payment_method'=>'pix','idempotency_key'=>$key()],$owner);
    };
    $settle((int)$charge['entry_id'],'180,00','2034-03-25');
    $settle($manual,'300,00','2034-03-10');
    $settle($manual,'200,00','2034-04-05');
    $settle($prior,'50,00','2034-03-02');

    $report=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31');
    $totals=$report['totals'];
    $check($totals['billed_cents']===148000,'Faturado soma somente receitas da competência, sem canceladas');
    $check($totals['received_for_billed_cents']===48000&&$totals['open_cents']===100000,'Posição histórica considera apenas baixas até o fim do período');
    $check($totals['cash_received_cents']===53000,'Recebido no período inclui caixa de competências anteriores');
    $check($totals['service_order_cents']===48000&&$totals['manual_cents']===100000,'Origem separa ordem de serviço e lançamento manual');
    $check($totals['overdue_cents']===100000,'Vencido usa o saldo aberto na data-base do relatório');
    $check($report['series']['2034-03']===['billed'=>148000,'received'=>53000],'Série mensal separa competência e caixa');
    $origins=array_column($report['rows'],'origin','id');
    $check($origins[(int)$charge['entry_id']]==='service_order'&&$origins[$manual]==='manual','Linhas preservam vínculo rastreável com a OS');
    $customerOptions=array_column($report['customers'],'label','key');
    $check(($customerOptions['contact:'.$customer]??'')==='Cliente Faturamento','Filtro expõe somente clientes do contexto com chave opaca');

    $serviceOnly=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31',['origin'=>'service_order']);
    $check($serviceOnly['totals']['billed_cents']===48000&&$serviceOnly['totals']['cash_received_cents']===18000&&count($serviceOnly['rows'])===1,'Filtro por OS mantém competência, caixa e linhas consistentes');
    $manualOnly=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31',['origin'=>'manual']);
    $check($manualOnly['totals']['billed_cents']===100000&&$manualOnly['totals']['cash_received_cents']===35000&&count($manualOnly['rows'])===1,'Filtro manual inclui caixa de competência anterior da mesma origem');
    $customerOnly=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31',['customer'=>'contact:'.$customer]);
    $check($customerOnly['totals']['billed_cents']===48000&&$customerOnly['totals']['cash_received_cents']===18000,'Filtro por cliente afeta indicadores e detalhamento');
    $unassigned=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31',['customer'=>'unassigned']);
    $check($unassigned['totals']['billed_cents']===100000&&$unassigned['totals']['cash_received_cents']===35000,'Filtro sem cliente preserva lançamentos não vinculados');
    $invalid=(new BillingReport($contexts[0]))->report('2034-03-01','2034-03-31',['origin'=>'qualquer','customer'=>'contact:0 OR 1=1']);
    $check($invalid['filters']===['origin'=>'','customer'=>'']&&$invalid['totals']['billed_cents']===148000,'Filtros inválidos são descartados sem alterar a consulta');

    $february=(new BillingReport($contexts[0]))->report('2034-02-01','2034-02-28');
    $check($february['totals']['billed_cents']===5000&&$february['totals']['cash_received_cents']===0,'Período anterior permanece comparável pelos mesmos critérios');
    $april=(new BillingReport($contexts[0]))->report('2034-04-01','2034-04-30');
    $check($april['totals']['billed_cents']===0&&$april['totals']['cash_received_cents']===20000,'Caixa posterior não altera faturamento da competência original');

    $otherFinance=new Finance($contexts[1]);
    $otherAccount=$otherFinance->createAccount(['name'=>'Conta outra','type'=>'checking']);
    (new Receivables($contexts[1]))->create([
        'description'=>'Receita outra empresa','amount'=>'500,00','due_date'=>'2034-03-10','competence'=>'2034-03',
        'account_id'=>$otherAccount,'payment_method'=>'pix','idempotency_key'=>$key(),
    ],$other);
    $check($report['totals']['billed_cents']===148000&&(new BillingReport($contexts[1]))->report('2034-03-01','2034-03-31')['totals']['billed_cents']===50000,'Empresas permanecem isoladas');
}finally{
    foreach([$org,$alien]as$organizationId){
        foreach(['organization_service_order_history','organization_service_order_attachments']as$table)$pdo->prepare("DELETE FROM {$table} WHERE organization_id=?")->execute([$organizationId]);
        $orderIds=$pdo->query('SELECT id FROM organization_service_orders WHERE organization_id='.(int)$organizationId)->fetchAll(PDO::FETCH_COLUMN);
        foreach($orderIds as$orderId)$pdo->prepare('DELETE FROM organization_service_order_items WHERE service_order_id=?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM organization_service_orders WHERE organization_id=?')->execute([$organizationId]);
        $pdo->prepare('DELETE FROM organization_service_order_sequences WHERE organization_id=?')->execute([$organizationId]);
    }
    foreach($contexts as$context){
        foreach(['finance_entry_audit_logs','finance_entry_settlements','finance_entries','finance_contacts','finance_categories','finance_accounts']as$table)$pdo->prepare("DELETE FROM {$table} WHERE financial_context_id=?")->execute([$context]);
    }
    foreach([$org,$alien]as$organizationId){
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id=?')->execute([$organizationId]);
        $pdo->prepare('DELETE FROM financial_contexts WHERE organization_id=?')->execute([$organizationId]);
        $pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$organizationId]);
    }
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$owner,$other]);
}
echo$failed===0?"ENT-009: FATURAMENTO APROVADO.\n":"ENT-009: {$failed} FALHA(S).\n";
exit($failed===0?0:1);
