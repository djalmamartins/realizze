<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Relatório operacional de ordens de serviço. Não calcula margem nem faturamento fiscal. */
final class ServiceOrderReport
{
    private PDO $pdo;
    private int $contextId;

    public function __construct(private readonly int $organizationId)
    {
        $this->pdo = Connection::get();
        $this->contextId = FinanceContext::forOrganization($organizationId);
    }

    public static function ready(): bool
    {
        return Connection::get()->query("SHOW TABLES LIKE 'organization_service_orders'")->fetchColumn() !== false;
    }

    /**
     * O período usa a data de início da OS; quando ausente, usa a data de criação.
     * A cobrança e seus recebimentos respeitam a data-base, sem alterar saldos.
     *
     * @return array{start:string,end:string,as_of:string,totals:array<string,int>,status:array<string,int>,rows:list<array<string,mixed>>}
     */
    public function report(string $start, string $end, ?string $asOf = null): array
    {
        $start = $this->date($start); $end = $this->date($end);
        if ($end < $start) throw new InvalidArgumentException('Período inválido.');
        $asOf = $this->date($asOf ?? $end);
        $asOf = min($asOf, $end);
        $q = $this->pdo->prepare(
            "SELECT o.id,o.order_number,o.description,o.start_date,o.due_date,o.status,o.subtotal_cents,
                    o.discount_cents,o.total_cents,o.receivable_entry_id,o.created_at,
                    COALESCE(NULLIF(c.trade_name,''),c.legal_name) customer_name,
                    TRIM(CONCAT_WS(' ',u.first_name,u.last_name)) responsible_name,
                    e.amount_cents receivable_cents,e.status receivable_status,e.paid_at,e.created_at receivable_created_at,
                    COALESCE(s.paid_cents,0) settlement_cents
             FROM organization_service_orders o
             INNER JOIN finance_contacts c ON c.id=o.customer_contact_id AND c.financial_context_id=o.financial_context_id
             LEFT JOIN users u ON u.id=o.responsible_user_id
             LEFT JOIN finance_entries e ON e.id=o.receivable_entry_id AND e.financial_context_id=o.financial_context_id
             LEFT JOIN (SELECT entry_id,SUM(amount_cents) paid_cents FROM finance_entry_settlements
                        WHERE financial_context_id=? AND received_at<=? GROUP BY entry_id) s ON s.entry_id=e.id
             WHERE o.organization_id=? AND o.financial_context_id=?
               AND COALESCE(o.start_date,DATE(o.created_at)) BETWEEN ? AND ?
             ORDER BY COALESCE(o.start_date,DATE(o.created_at)),o.id"
        );
        $q->execute([$this->contextId,$asOf,$this->organizationId,$this->contextId,$start,$end]);
        $totals=['orders'=>0,'order_cents'=>0,'discount_cents'=>0,'completed_cents'=>0,'billed_cents'=>0,'received_cents'=>0,'open_cents'=>0,'unbilled_cents'=>0];
        $status=['draft'=>0,'open'=>0,'in_progress'=>0,'completed'=>0,'cancelled'=>0];
        $rows=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as$row){
            $row['id']=(int)$row['id']; $row['subtotal_cents']=(int)$row['subtotal_cents'];
            $row['discount_cents']=(int)$row['discount_cents']; $row['total_cents']=(int)$row['total_cents'];
            $status[$row['status']]++; $totals['orders']++;
            $billed=$row['receivable_entry_id']!==null && $row['receivable_created_at']!==null && substr((string)$row['receivable_created_at'],0,10)<=$asOf
                && $row['receivable_status']!=='cancelled';
            $billedCents=$billed?(int)$row['receivable_cents']:0;
            $received=$billed ? ((int)$row['settlement_cents']>0
                ? min($billedCents,(int)$row['settlement_cents'])
                : ($row['paid_at']!==null && $row['paid_at']<=$asOf ? $billedCents : 0)) : 0;
            $row['billed_cents']=$billedCents; $row['received_cents']=$received; $row['open_cents']=$billedCents-$received;
            if($row['status']!=='cancelled'){
                $totals['order_cents'] += $row['total_cents']; $totals['discount_cents'] += $row['discount_cents'];
                if($row['status']==='completed')$totals['completed_cents'] += $row['total_cents'];
                $totals['billed_cents'] += $billedCents; $totals['received_cents'] += $received; $totals['open_cents'] += $row['open_cents'];
                if(!$billed)$totals['unbilled_cents'] += $row['total_cents'];
            }
            $rows[]=$row;
        }
        return ['start'=>$start,'end'=>$end,'as_of'=>$asOf,'totals'=>$totals,'status'=>$status,'rows'=>$rows];
    }

    private function date(string $value): string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if($date===false || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Data inválida.');
        return $value;
    }
}
