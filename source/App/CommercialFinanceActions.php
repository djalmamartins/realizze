<?php
declare(strict_types=1);
namespace Moves\App;
use InvalidArgumentException;
use Moves\Services\{BankReconciliation,CardInvoices,CostCenters,Csrf,FinanceContext,OrganizationContext,OrganizationTeam,SessionAuth};
use Throwable;

/** Operações de cartões, conciliação e custos; todas requerem CSRF e finance.manage. */
final class CommercialFinanceActions
{
    public function handle(string $action): void
    {
        $user=SessionAuth::current();
        if($user===null||!Csrf::validate($_POST['csrf_token']??null)){\Moves\Services\AppError::render(403);return;}
        $org=OrganizationContext::current();
        if($org!==null&&!OrganizationTeam::can((int)$user['id'],(int)$org['id'],'finance.manage')){\Moves\Services\AppError::render(403);return;}
        // Na PF, faturas e conciliação ficam fora do acesso delegado (as telas também não abrem para quem foi convidado).
        if($org===null&&\Moves\Services\PersonalAccessContext::ownerId()!==null){\Moves\Services\AppError::render(403);return;}
        try {$context=(int)FinanceContext::currentId();$message=match($action){
            'card_purchase'=>(new CardInvoices($context))->purchase($_POST,(int)$user['id'])?'Compra registrada na fatura.':'',
            'card_pay'=>(new CardInvoices($context))->pay($_POST,(int)$user['id'])?'Pagamento registrado. O valor saiu da conta de origem para o cartão.':'',
            'card_reverse'=>(new CardInvoices($context))->reversePayment((int)($_POST['payment_id']??0),(int)$user['id'])?'Pagamento estornado. O valor voltou para a conta de origem.':throw new InvalidArgumentException('Pagamento não encontrado ou já estornado.'),
            'cost_center'=>(new CostCenters($context))->save($_POST,(int)$user['id'])?'Centro de custo salvo.':'',
            'reconciliation_import'=>((new BankReconciliation($context))->importStatement($_FILES['statement']??[],$_POST['account_id']??null,(int)$user['id'])).' linhas importadas para revisão.',
            'reconciliation_review'=>$this->review($context,(int)$user['id']),
            'reconciliation_create'=>(new BankReconciliation($context))->createEntry($_POST['line_id']??null,$_POST['category_id']??null,(int)$user['id'])>0?'Lançamento criado a partir do extrato e conciliado.':'',
            default=>throw new InvalidArgumentException('Ação inválida.'),
        };$_SESSION['finance_flash']=['success',$message];
        } catch(InvalidArgumentException $e){$_SESSION['finance_flash']=['error',$e->getMessage()];} catch(Throwable $e){error_log('Realizze commercial finance: '.$e->getMessage());$_SESSION['finance_flash']=['error','Não foi possível salvar. Tente novamente.'];}
        $page=in_array($_POST['back']??'', ['faturas','conciliacao','centros-de-custo'],true)?$_POST['back']:'faturas';
        // Faturas volta para o mesmo cartão e mês.
        $query=$page==='faturas'?array_filter(['cartao'=>(string)($_POST['card_account_id']??''),'mes'=>(string)($_POST['month']??'')],static fn($v)=>$v!==''):[];
        header('Cache-Control: no-store');header('Location: '.\Moves\Services\AppUrl::section($page,null,$query),true,303);
    }
    private function review(int $context,int $userId): string {$status=(string)($_POST['status']??'');(new BankReconciliation($context))->review($_POST['line_id']??null,$_POST['entry_id']??null,$status,$userId);return ['matched'=>'Linha conciliada com o lançamento. Nenhum saldo foi alterado.','ignored'=>'Linha ignorada.','pending'=>'Revisão desfeita. A linha voltou para pendente.'][$status]??'Linha de extrato revisada. Nenhum saldo foi alterado.';}
}
