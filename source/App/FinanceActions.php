<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Authorization;
use Moves\Services\BillingReport;
use Moves\Services\Csrf;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\GoalWallets;
use Moves\Services\ManagementDre;
use Moves\Services\ObligationReport;
use Moves\Services\OrganizationContext;
use Moves\Services\Organizations;
use Moves\Services\PersonalAccessContext;
use Moves\Services\Receivables;
use Moves\Services\RecurringFinance;
use Moves\Services\SessionAuth;
use Moves\Services\ServiceOrderReport;
use Throwable;

/** Mutações financeiras do app: exigem POST, sessão e CSRF; sempre no contexto atual. */
final class FinanceActions
{
    private const PAGES = ['income' => 'receber', 'expense' => 'pagar'];

    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $kind = in_array($_POST['kind'] ?? null, ['income', 'expense'], true) ? $_POST['kind'] : 'income';
        try {
            $organization = OrganizationContext::current();
            if ($organization !== null && $action !== 'company'
                && !(new Authorization())->can((int) $user['id'], (int) $organization['id'], 'finance.manage')) {
                \Moves\Services\AppError::render(403);
                return;
            }
            $contextId = (int) FinanceContext::currentId();
            $finance = new Finance($contextId);
            $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $message = match ($action) {
                'create' => $this->create($finance, $contextId, $kind, (int) $user['id']),
                'update' => $this->update($finance, $contextId, $kind, $id, (int) $user['id']),
                'company' => $this->company((int) $user['id']),
                'category' => [$finance->createCategory($_POST) > 0 ? 'success' : 'error', 'Categoria criada.'],
                'category_delete' => $finance->deleteCategory($id) ? ['success', 'Categoria excluída. Os lançamentos ficaram sem categoria.'] : ['error', 'Categoria não encontrada.'],
                'category_dre' => $this->categoryDre($contextId, $id, (int) $user['id']),
                'budget' => $this->budget($finance),
                'budget_delete' => $finance->deleteBudget($_POST['category_id'] ?? null, (string) ($_POST['mes'] ?? '')) ? ['success', 'Orçamento excluído. Os lançamentos não mudaram.'] : ['error', 'Orçamento não encontrado.'],
                'budget_copy' => $this->budgetCopy($finance),
                'goal' => [$finance->createGoal($_POST, $_FILES['photo'] ?? null, (int) $user['id']) > 0 ? 'success' : 'error', 'Meta criada.'],
                'goal_update' => $finance->updateGoal($id, $_POST, $_FILES['photo'] ?? null) ? ['success', 'Meta atualizada.'] : ['error', 'Meta não encontrada.'],
                'goal_add' => $finance->addToGoal($id, $_POST['amount'] ?? null, (int) $user['id'], $_POST['date'] ?? null) ? ['success', 'Valor adicionado à meta.'] : ['error', 'Meta não encontrada.'],
                'goal_contribution_delete' => $finance->deleteContribution($id) ? ['success', 'Aporte removido e valor guardado atualizado.'] : ['error', 'Aporte não encontrado.'],
                'goal_delete' => $finance->deleteGoal($id, (int) $user['id']) ? ['success', 'Meta excluída. O que estava reservado voltou para as contas.'] : ['error', 'Meta não encontrada.'],
                'goal_reserve' => (new GoalWallets($contextId))->reserve($id, $_POST, (int) $user['id'])['duplicate'] ? ['success', 'Este valor já havia sido guardado.'] : ['success', 'Valor guardado na meta. O saldo da conta não mudou.'],
                'goal_release' => (new GoalWallets($contextId))->release($id, $_POST, (int) $user['id'])['duplicate'] ? ['success', 'Este resgate já havia sido feito.'] : ['success', 'Valor resgatado e disponível na conta.'],
                'goal_transfer' => (new GoalWallets($contextId))->transfer($id, $_POST, (int) $user['id'])['duplicate'] ? ['success', 'Esta transferência já havia sido feita.'] : ['success', 'Reserva transferida entre as metas.'],
                'goal_pay' => (new GoalWallets($contextId))->payExpense($id, $_POST, (int) $user['id'])['duplicate'] ? ['success', 'Este pagamento já havia sido registrado.'] : ['success', 'Despesa paga com a reserva da meta.'],
                'goal_reverse' => (new GoalWallets($contextId))->reverse($id, (int) $user['id']) ? ['success', 'Operação estornada.'] : ['error', 'Operação não encontrada.'],
                'goal_status' => (new GoalWallets($contextId))->setPaused($id, ($_POST['status'] ?? '') === 'paused') ? ['success', ($_POST['status'] ?? '') === 'paused' ? 'Meta pausada.' : 'Meta retomada.'] : ['error', 'Meta não encontrada.'],
                'goal_schedule' => $this->goalSchedule($contextId, $id, (int) $user['id']),
                'goal_schedule_status' => (new GoalWallets($contextId))->setScheduleStatus($id, (string) ($_POST['status'] ?? '')) ? ['success', 'Aporte programado atualizado.'] : ['error', 'Aporte programado não encontrado.'],
                'account' => [$finance->createAccount($_POST) > 0 ? 'success' : 'error', 'Conta cadastrada.'],
                'account_update' => $finance->updateAccount($id, $_POST) ? ['success', 'Conta atualizada.'] : ['error', 'Conta não encontrada.'],
                'account_archive' => $finance->archiveAccount($id) ? ['success', 'Conta arquivada. O histórico continua nos relatórios.'] : ['error', 'Conta não encontrada.'],
                'transfer' => [$finance->transfer($_POST, (int) $user['id']) > 0 ? 'success' : 'error', 'Transferência registrada.'],
                'settle' => $this->settle($finance, $kind, $id, (int) $user['id']),
                'delete' => $finance->delete($id, (int) $user['id']) ? ['success', 'Lançamento excluído.'] : ['error', 'Lançamento não encontrado.'],
                default => ['error', 'Ação inválida.'],
            };
            // O pagamento segue a política de saldo da conta; a carteira não muda, e o usuário decide o que resgatar.
            if ($message[0] === 'success' && in_array($action, ['create', 'update', 'settle', 'transfer'], true)) {
                foreach ($finance->balanceSummary()['shortfalls'] as $short) {
                    $message = ['warning', $message[1] . ' Atenção: ' . $short['name'] . ' ficou com ' . Finance::money($short['cents']) . ' a menos que o reservado em metas.'];
                }
            }
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
        } catch (Throwable $exception) {
            error_log('Realizze finance: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível salvar. Tente novamente.'];
        }
        $back = $_POST['back'] ?? null;
        $_SESSION[$back === 'receber' ? 'receivables_flash' : 'finance_flash'] = $message;
        $month = is_string($_POST['mes'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_POST['mes']) ? $_POST['mes'] : date('Y-m');
        header('Cache-Control: no-store');
        if ($back === 'recorrencias') {
            header('Location: ' . \Moves\Services\AppUrl::section('recorrencias', OrganizationContext::current()), true, 303);
            return;
        }
        if ($back === 'receber') {
            $query = [];
            foreach (['inicio', 'fim', 'situacao', 'busca', 'categoria', 'cliente', 'contato', 'page'] as $name) {
                if (is_string($_POST[$name] ?? null) && $_POST[$name] !== '') {
                    $query[$name] = mb_substr($_POST[$name], 0, 160);
                }
            }
            header('Location: ' . \Moves\Services\AppUrl::section('receber', null, $query), true, 303);
            return;
        }
        if ($back === 'dre') {
            $query = ['criterio' => ($_POST['criterio'] ?? '') === 'caixa' ? 'caixa' : 'competencia'];
            foreach (['inicio', 'fim'] as $name) {
                if (is_string($_POST[$name] ?? null)) $query[$name] = $_POST[$name];
            }
            header('Location: ' . \Moves\Services\AppUrl::section('dre', OrganizationContext::current(), $query), true, 303);
            return;
        }
        $page = in_array($back, ['contas', 'movimentacoes', 'overview', 'categorias', 'planejamento', 'metas', 'pagar'], true) ? $back : self::PAGES[$kind];
        header('Location: ' . \Moves\Services\AppUrl::backTo($page, \Moves\Services\AppUrl::section($page, null, ['mes' => $month])), true, 303);
    }

    /** @return array{0:string,1:string} */
    private function goalSchedule(int $contextId, int $goalId, int $userId): array
    {
        (new GoalWallets($contextId))->saveSchedule($goalId, $_POST, $userId);
        return ['success', 'Aporte programado salvo.'];
    }

    /** @return array{0:string,1:string} */
    private function settle(Finance $finance, string $kind, int $entryId, int $userId): array
    {
        if ($kind !== 'expense') {
            return $finance->settle($entryId) ? ['success', 'Recebimento confirmado.'] : ['error', 'Lançamento não encontrado.'];
        }
        $result = $finance->settleExpense($entryId, $_POST, $userId);
        if ($result['duplicate']) return ['success', 'Este pagamento já havia sido processado.'];
        return ['success', $result['balance_cents'] === 0 ? 'Despesa quitada.' : 'Pagamento parcial registrado. Saldo em aberto: ' . Finance::money($result['balance_cents']) . '.'];
    }

    /** @return array{0:string,1:string} */
    private function budget(Finance $finance): array
    {
        $finance->saveBudget($_POST);
        return ['success', 'Orçamento salvo.'];
    }

    /** @return array{0:string,1:string} */
    private function categoryDre(int $contextId, int $categoryId, int $userId): array
    {
        $service = new ManagementDre($contextId);
        if (!$service->saveCategoryClassification($categoryId, is_string($_POST['management_dre_group'] ?? null) ? $_POST['management_dre_group'] : null)) {
            return ['error', 'Categoria não encontrada.'];
        }
        $organization = OrganizationContext::current();
        if ($organization !== null) {
            \Moves\Services\OrganizationAudit::record((int)$organization['id'],$userId,'dre.category_classified','finance_category',$categoryId,['group'=>$_POST['management_dre_group']??null]);
        }
        return ['success', 'Classificação gerencial atualizada.'];
    }

    /** @return array{0:string,1:string} */
    private function budgetCopy(Finance $finance): array
    {
        $count = $finance->copyPreviousBudgets((string) ($_POST['mes'] ?? ''));
        return $count > 0 ? ['success', $count === 1 ? '1 limite copiado do mês anterior.' : $count . ' limites copiados do mês anterior.'] : ['warning', 'Não há limites novos para copiar do mês anterior.'];
    }

    /** Exporta os lançamentos do período em CSV, abrível em planilhas. */
    public function export(): void
    {
        $user = SessionAuth::current();
        if ($user === null) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $organization = OrganizationContext::current();
        if ($organization !== null && !\Moves\Services\OrganizationTeam::can((int) $user['id'], (int) $organization['id'], 'reports.export')) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $period = Finance::period($_GET);
        if (($_GET['relatorio'] ?? '') === 'fluxo-caixa') {
            $start=$period['start']->format('Y-m-d');$end=$period['end']->format('Y-m-d');
            $finance=new Finance((int)FinanceContext::currentId());
            $realized=$finance->cashMovements($start,$end);$position=$finance->report($start,$end);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-fluxo-caixa-' . $start . '-a-' . $end . '.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Situação','Data','Natureza','Lançamento','Categoria','Conta','Origem','Valor'],';','"','');
            $safe=static fn(?string$text):string=>preg_replace('/^([=+\-@\t\r])/',"'$1",(string)$text)??'';
            $sources=['direct'=>'Pagamento direto','settlement'=>'Baixa','card_payment'=>'Pagamento de fatura','card_refund'=>'Estorno de fatura'];
            foreach($realized as$row)fputcsv($out,['Realizado',$row['date'],$row['kind']==='income'?'Entrada':'Saída',$safe($row['description']),$safe($row['category']),$safe($row['account']),$sources[$row['source']]??$row['source'],number_format($row['amount_cents']/100,2,',','')],';','"','');
            foreach($position['days']as$day)foreach($day['entries']as$row)if(!$row['cancelled']&&$row['kind']!=='transfer'&&$row['open_cents']>0)fputcsv($out,['Previsto',$row['due_date'],$row['kind']==='income'?'Entrada':'Saída',$safe($row['description']),$safe($row['category']),$safe($row['account']),'Saldo em aberto',number_format($row['open_cents']/100,2,',','')],';','"','');
            fclose($out);return;
        }
        if (($_GET['relatorio'] ?? '') === 'obrigacoes') {
            if ($organization === null) { \Moves\Services\AppError::render(403); return; }
            if (!ObligationReport::ready()) { \Moves\Services\AppError::render(404); return; }
            $report=(new ObligationReport((int)$organization['id']))->report(
                $period['start']->format('Y-m-d'),$period['end']->format('Y-m-d'),
                min($period['end']->format('Y-m-d'),date('Y-m-d')), [
                    'type'=>is_string($_GET['ob_tipo']??null)?$_GET['ob_tipo']:'',
                    'responsible'=>is_string($_GET['ob_responsavel']??null)?$_GET['ob_responsavel']:'',
                ]
            );
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-obrigacoes-'
                . $period['start']->format('Y-m-d') . '-a-' . $period['end']->format('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Obrigação','Tipo','Competência','Vencimento','Responsável','Periodicidade','Situação na data-base','Comprovantes','Valor informado'],';','"','');
            $safe=static fn(?string$text):string=>preg_replace('/^([=+\-@\t\r])/',"'$1",(string)$text)??'';
            $labels=['pending'=>'Pendente','upcoming'=>'Próxima','overdue'=>'Vencida','completed'=>'Concluída','cancelled'=>'Cancelada'];
            foreach($report['rows']as$row)fputcsv($out,[$safe($row['title']),$safe($row['obligation_type']),$row['competence_date'],$row['due_date'],$safe($row['responsible_name']),$row['frequency'],$labels[$row['display_status']]??$row['display_status'],$row['attachment_count'],$row['amount_cents']===null?'':number_format($row['amount_cents']/100,2,',','')],';','"','');
            fclose($out);return;
        }
        if (($_GET['relatorio'] ?? '') === 'ordens-servico') {
            if ($organization === null) { \Moves\Services\AppError::render(403); return; }
            if (!ServiceOrderReport::ready()) { \Moves\Services\AppError::render(404); return; }
            $report=(new ServiceOrderReport((int)$organization['id']))->report(
                $period['start']->format('Y-m-d'),$period['end']->format('Y-m-d'),
                min($period['end']->format('Y-m-d'),date('Y-m-d')), [
                    'status'=>is_string($_GET['os_situacao']??null)?$_GET['os_situacao']:'',
                    'customer'=>is_string($_GET['os_cliente']??null)?$_GET['os_cliente']:'',
                ]
            );
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-ordens-servico-'
                . $period['start']->format('Y-m-d') . '-a-' . $period['end']->format('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Ordem','Cliente','Descrição','Responsável','Início','Prazo','Situação','Subtotal','Desconto','Total','Cobrança vinculada','Recebido até a data-base','Em aberto'],';','"','');
            $safe=static fn(?string$text):string=>preg_replace('/^([=+\-@\t\r])/',"'$1",(string)$text)??'';
            $labels=['draft'=>'Rascunho','open'=>'Aberta','in_progress'=>'Em andamento','completed'=>'Concluída','cancelled'=>'Cancelada'];
            foreach($report['rows']as$row)fputcsv($out,[$safe($row['order_number']),$safe($row['customer_name']),$safe($row['description']),$safe($row['responsible_name']),$row['start_date'],$row['due_date'],$labels[$row['status']]??$row['status'],number_format($row['subtotal_cents']/100,2,',',''),number_format($row['discount_cents']/100,2,',',''),number_format($row['total_cents']/100,2,',',''),number_format($row['billed_cents']/100,2,',',''),number_format($row['received_cents']/100,2,',',''),number_format($row['open_cents']/100,2,',','')],';','"','');
            fclose($out);return;
        }
        if (($_GET['relatorio'] ?? '') === 'orcamento') {
            $month=$period['start']->format('Y-m');
            $asOf=min($period['end']->format('Y-m-d'),date('Y-m-d'));
            $finance=new Finance((int)FinanceContext::currentId());
            $rows=$finance->budgets($month,$asOf);
            $previous=[];
            $previousMonth=$period['start']->modify('-1 month')->format('Y-m');
            foreach($finance->budgets($previousMonth,$period['start']->modify('-1 day')->format('Y-m-d')) as$row)$previous[(int)$row['category_id']]=$row['spent'];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-orcamento-' . $month . '.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Categoria','Orçado','Comprometido','Pago até a data-base','Em aberto','Disponível','Comprometido no mês anterior','Variação'],';','"','');
            $safe=static fn(?string$text):string=>preg_replace('/^([=+\-@\t\r])/',"'$1",(string)$text)??'';
            foreach($rows as$row){$before=(int)($previous[(int)$row['category_id']]??0);fputcsv($out,[$safe($row['name']),number_format($row['budget']/100,2,',',''),number_format($row['spent']/100,2,',',''),number_format($row['realized']/100,2,',',''),number_format($row['pending']/100,2,',',''),number_format($row['available']/100,2,',',''),number_format($before/100,2,',',''),number_format(($row['spent']-$before)/100,2,',','')],';','"','');}
            fclose($out);return;
        }
        if (($_GET['relatorio'] ?? '') === 'posicao-contas') {
            $finance=new Finance((int)FinanceContext::currentId());
            $asOf=min($period['end']->format('Y-m-d'),date('Y-m-d'));
            $openingDate=min($period['start']->modify('-1 day')->format('Y-m-d'),$asOf);
            $opening=$finance->cashAccountsPositionOn($openingDate);
            $closing=$finance->cashAccountsPositionOn($asOf);
            $rows=[];
            foreach($opening as$row)$rows[$row['id']]=$row+['opening_cents'=>$row['balance_cents'],'closing_cents'=>0];
            foreach($closing as$row){$rows[$row['id']]??=$row+['opening_cents'=>0];$rows[$row['id']]['closing_cents']=$row['balance_cents'];}
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-posicao-contas-' . $asOf . '.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Conta','Tipo','Instituição','Saldo inicial','Movimentação líquida','Saldo na data-base'],';','"','');
            $safe=static fn(?string$text):string=>preg_replace('/^([=+\-@\t\r])/',"'$1",(string)$text)??'';
            $types=['checking'=>'Conta corrente','savings'=>'Poupança','wallet'=>'Carteira'];
            foreach($rows as$row)fputcsv($out,[$safe($row['name']),$types[$row['type']]??'Conta',$safe($row['institution']),number_format($row['opening_cents']/100,2,',',''),number_format(($row['closing_cents']-$row['opening_cents'])/100,2,',',''),number_format($row['closing_cents']/100,2,',','')],';','"','');
            fclose($out);return;
        }
        if (($_GET['relatorio'] ?? '') === 'faturamento') {
            $billingFilters = [
                'origin' => is_string($_GET['faturamento_origem'] ?? null) ? $_GET['faturamento_origem'] : '',
                'customer' => is_string($_GET['faturamento_cliente'] ?? null) ? $_GET['faturamento_cliente'] : '',
            ];
            $billing = (new BillingReport((int) FinanceContext::currentId()))->report(
                $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'), $billingFilters
            );
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-faturamento-'
                . $period['start']->format('Y-m-d') . '-a-' . $period['end']->format('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Competência', 'Vencimento', 'Cliente', 'Descrição', 'Origem', 'Documento', 'Faturado', 'Recebido até o período', 'Em aberto'], ';', '"', '');
            $safe = static fn (?string $text): string => preg_replace('/^([=+\-@\t\r])/', "'$1", (string) $text) ?? '';
            foreach ($billing['rows'] as $row) {
                fputcsv($out, [
                    $row['competency_date'], $row['due_date'], $safe($row['customer_name']), $safe($row['description']),
                    $row['origin'] === 'service_order' ? 'Ordem de serviço' : 'Lançamento manual', $safe($row['order_number'] ?? $row['document_number']),
                    number_format($row['amount_cents'] / 100,2,',',''), number_format($row['received_cents'] / 100,2,',',''),
                    number_format($row['open_cents'] / 100,2,',',''),
                ], ';', '"', '');
            }
            fclose($out);
            return;
        }
        if (($_GET['relatorio'] ?? '') === 'dre') {
            $criterion = ($_GET['criterio'] ?? '') === 'caixa' ? 'cash' : 'competence';
            $dre = (new ManagementDre((int) FinanceContext::currentId()))->report(
                $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'), $criterion
            );
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="realizze-dre-' . $criterion . '-' . $period['start']->format('Y-m-d') . '-a-' . $period['end']->format('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Critério', 'Data', 'Grupo', 'Lançamento', 'Categoria', 'Origem', 'Valor'], ';', '"', '');
            $safe = static fn (?string $text): string => preg_replace('/^([=+\-@\t\r])/', "'$1", (string) $text) ?? '';
            $sources = ['direct' => 'Pagamento direto', 'settlement' => 'Baixa', 'card_payment' => 'Pagamento de fatura', 'competence' => 'Competência'];
            foreach ($dre['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    fputcsv($out, [
                        $criterion === 'cash' ? 'Caixa' : 'Competência', $row['occurred_at'], $safe($group['label']),
                        $safe($row['description']), $safe($row['category']), $sources[$row['source']] ?? 'Lançamento',
                        number_format((int) $row['amount_cents'] / 100, 2, ',', ''),
                    ], ';', '"', '');
                }
            }
            fclose($out);
            return;
        }
        $rows = (new Finance((int) FinanceContext::currentId()))->exportRows(
            $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'), [
                'status' => is_string($_GET['pr_situacao'] ?? null) ? $_GET['pr_situacao'] : '',
                'account_id' => is_string($_GET['pr_conta'] ?? null) ? $_GET['pr_conta'] : '',
                'counterparty' => is_string($_GET['pr_contraparte'] ?? null) ? $_GET['pr_contraparte'] : '',
            ]
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="realizze-pagar-receber-'
            . $period['start']->format('Y-m-d') . '-a-' . $period['end']->format('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Vencimento', 'Pagamento', 'Tipo', 'Descrição', 'Categoria', 'Conta', 'Conta de destino', 'Valor', 'Realizado até a data-base', 'Em aberto', 'Situação'], ';', '"', '');
        $types = ['income' => 'Receita', 'expense' => 'Despesa', 'transfer' => 'Transferência'];
        $statuses = ['cancelled'=>'Cancelado','transfer'=>'Transferido','paid'=>'Realizado','partial'=>'Parcial','partial_overdue'=>'Parcial vencido','overdue'=>'Vencido','open'=>'Em aberto'];
        foreach ($rows as $row) {
            // Evita que planilhas interpretem textos do usuário como fórmulas.
            $safe = static fn (?string $text): string => preg_replace('/^([=+\-@\t\r])/', "'$1", (string) $text) ?? '';
            fputcsv($out, [
                $row['due_date'], $row['paid_at'], $types[$row['kind']], $safe($row['description']), $safe($row['category']),
                $safe($row['account']), $safe($row['target_account']), number_format((int) $row['amount_cents'] / 100, 2, ',', ''),
                number_format((int)$row['paid_cents']/100,2,',',''),number_format((int)$row['open_cents']/100,2,',',''),$statuses[$row['display_status']]??'Em aberto',
            ], ';', '"', '');
        }
        fclose($out);
    }

    /** @return array{0:string,1:string} */
    private function company(int $userId): array
    {
        $id = Organizations::create($userId, (string) ($_POST['name'] ?? ''), (string) ($_POST['cnpj'] ?? ''));
        OrganizationContext::select($id, $_POST['csrf_token'] ?? null);
        return ['success', 'Empresa cadastrada. Você já está nela.'];
    }

    /**
     * Destino único da modal de lançamento; cada caso segue pelo service que já cuida dele.
     *
     * @return array{0:string,1:string}
     */
    private function create(Finance $finance, int $contextId, string $kind, int $userId): array
    {
        $files = \Moves\Services\Helpdesk::uploadedFiles('attachments');
        if (($_POST['repeat_mode'] ?? '') === 'subscription') {
            return $this->createRecurring($finance, $contextId, $kind, $userId, $files);
        }
        $data = $_POST;
        $data['repeat'] = ($_POST['repeat_mode'] ?? '') === 'term' ? (string) ($_POST['repeat_value'] ?? '') : '';
        if ($kind === 'income') {
            $result = (new Receivables($contextId))->createWithOptions($this->incomeData($data), $userId, $files);
            return ['success', $result['duplicate'] ? 'Esta receita já havia sido registrada.' : ($result['count'] > 1 ? $result['count'] . ' receitas criadas.' : 'Receita registrada.')];
        }
        $finance->createEntry($kind, $data, $userId, $files);
        return ['success', $data['repeat'] !== '' ? (int) $data['repeat_count'] . ' despesas criadas.' : 'Despesa registrada.'];
    }

    /**
     * @param list<array<string,mixed>> $files
     * @return array{0:string,1:string}
     */
    private function createRecurring(Finance $finance, int $contextId, string $kind, int $userId, array $files): array
    {
        $this->requireOwner();
        // finance_recurring_items não tem chave de envio: a sessão segura o duplo clique.
        $key = (string) ($_POST['idempotency_key'] ?? '');
        if ($key !== '' && isset($_SESSION['entry_recurring_keys'][$key])) {
            return ['success', 'Esta assinatura já havia sido registrada.'];
        }
        $tags = Finance::parseTags($_POST['tags'] ?? '');
        \Moves\Services\FinanceAttachments::validate($files);
        $service = new RecurringFinance($contextId);
        $auto = isset($_POST['auto_create']) && $service->occurrencesReady();
        if (!$auto && ($tags !== [] || $files !== [])) {
            throw new InvalidArgumentException('Tags e anexos ficam no primeiro lançamento gerado: ative a criação automática ou retire-os.');
        }
        $id = $service->create($this->recurringData($kind, $auto), $userId);
        $_SESSION['entry_recurring_keys'][$key] = true;
        $entryId = $service->firstEntryId($id);
        if ($entryId !== null) {
            $finance->updateEntryExtras($entryId, ['tags' => $_POST['tags'] ?? ''], $userId, $files);
            if (!empty($_POST['paid'])) {
                $this->settleFirst($finance, $contextId, $kind, $entryId, $userId);
            }
        }
        return ['success', $auto ? 'Assinatura criada. Os lançamentos seguem até o cancelamento.' : 'Assinatura criada. A cobrança aparece como prevista.'];
    }

    private function settleFirst(Finance $finance, int $contextId, string $kind, int $entryId, int $userId): void
    {
        if ($kind === 'expense') {
            $finance->settle($entryId);
            return;
        }
        $amount = (string) ($_POST['amount'] ?? '');
        (new Receivables($contextId))->settle($entryId, [
            'amount' => $amount, 'received_at' => min((string) $_POST['due_date'], date('Y-m-d')), 'account_id' => $_POST['account_id'] ?? null,
            'payment_method' => $_POST['payment_method'] ?? 'other', 'idempotency_key' => bin2hex(random_bytes(16)),
        ], $userId);
    }

    /** @return array{0:string,1:string} */
    private function update(Finance $finance, int $contextId, string $kind, int $id, int $userId): array
    {
        if (($_POST['source'] ?? '') === 'recurring') {
            $this->requireOwner();
            (new RecurringFinance($contextId))->update($id, $this->recurringData($kind, isset($_POST['auto_create'])));
            return ['success', 'Assinatura atualizada.'];
        }
        $files = \Moves\Services\Helpdesk::uploadedFiles('attachments');
        if ($kind === 'income') {
            \Moves\Services\FinanceAttachments::validate($files);
            (new Receivables($contextId))->update($id, $this->incomeData($_POST), $userId);
            $finance->updateEntryExtras($id, $_POST, $userId, $files);
            return ['success', 'Receita atualizada.'];
        }
        $finance->updateExpense($id, $_POST, $userId, $files);
        return ['success', 'Despesa atualizada.'];
    }

    /** Acesso compartilhado não gerencia recorrências, como nas rotas de pausar e cancelar. */
    private function requireOwner(): void
    {
        if (PersonalAccessContext::ownerId() !== null) {
            throw new InvalidArgumentException('Somente o titular da conta pode gerenciar assinaturas.');
        }
    }

    /** Competência e forma ficam recolhidas na modal: sem preenchimento, seguem a data do lançamento. */
    private function incomeData(array $data): array
    {
        $data['competence'] = ($data['competence'] ?? '') !== '' ? $data['competence'] : substr((string) ($data['due_date'] ?? ''), 0, 7);
        $data['payment_method'] = ($data['payment_method'] ?? '') !== '' ? $data['payment_method'] : 'other';
        return $data;
    }

    private function recurringData(string $kind, bool $auto): array
    {
        $data = ['kind' => $kind, 'name' => $_POST['description'] ?? '', 'next_due_date' => $_POST['due_date'] ?? ''] + $_POST;
        unset($data['auto_create']);
        return $auto ? $data + ['auto_create' => '1'] : $data;
    }

    /** Baixa um anexo; só encontra arquivos do contexto atual. */
    public function attachment(): void
    {
        if (SessionAuth::current() === null) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $id = (int) filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        $attachment = (new Finance((int) FinanceContext::currentId()))->attachment($id);
        if ($attachment === null) {
            \Moves\Services\AppError::render(404);
            return;
        }
        \Moves\Services\FinanceAttachments::stream($attachment, ($_GET['ver'] ?? '') === '1');
    }

    /** Entrega a foto de uma meta; só encontra metas do contexto atual. */
    public function goalPhoto(): void
    {
        if (SessionAuth::current() === null) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $id = (int) filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        $photo = (new Finance((int) FinanceContext::currentId()))->goalPhoto($id);
        if ($photo === null) {
            \Moves\Services\AppError::render(404);
            return;
        }
        \Moves\Services\GoalPhotos::stream($photo);
    }
}
