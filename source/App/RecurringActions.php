<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\{AppError,Authorization,Csrf,FinanceContext,OrganizationContext,RecurringFinance,SessionAuth};

final class RecurringActions
{
    public function handle(string $action): void
    {
        $user=SessionAuth::current();
        if($user===null||!Csrf::validate($_POST['csrf_token']??null)){AppError::render(403);return;}
        try{
            $organization=OrganizationContext::current();
            if($organization!==null&&!(new Authorization())->can((int)$user['id'],(int)$organization['id'],'finance.manage')){AppError::render(403);return;}
            $service=new RecurringFinance((int)FinanceContext::currentId());
            if($action==='create'){$service->create($_POST,(int)$user['id']);$message='Recorrência criada.';}
            else{$id=(int)filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$status=['pause'=>'paused','activate'=>'active','cancel'=>'canceled'][$action]??'';if(!$service->changeStatus($id,$status))throw new \InvalidArgumentException('Recorrência não encontrada.');$message=['paused'=>'Recorrência pausada.','active'=>'Recorrência reativada.','canceled'=>'Recorrência cancelada.'][$status];}
            $_SESSION['finance_flash']=['success',$message];
        }catch(\InvalidArgumentException $e){$_SESSION['finance_flash']=['error',$e->getMessage()];}
        catch(\Throwable $e){error_log('Realizze recorrências: '.$e->getMessage());$_SESSION['finance_flash']=['error','Não foi possível salvar. Tente novamente.'];}
        header('Cache-Control: no-store');header('Location: '.\Moves\Services\AppUrl::section('recorrencias',OrganizationContext::current()),true,303);
    }
}
