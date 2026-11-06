<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Agenda gerencial de obrigações. Não calcula tributos e não cria lançamentos financeiros. */
final class ObligationReport
{
    private PDO $pdo;

    public function __construct(private readonly int $organizationId)
    {
        if ($organizationId <= 0) throw new InvalidArgumentException('Empresa inválida.');
        $this->pdo=Connection::get();
    }

    public static function ready(): bool
    {
        return Connection::get()->query("SHOW TABLES LIKE 'organization_obligations'")->fetchColumn() !== false;
    }

    /** @return array{start:string,end:string,as_of:string,totals:array<string,int>,status:array<string,int>,rows:list<array<string,mixed>>} */
    public function report(string $start,string $end,?string $asOf=null):array
    {
        $start=$this->date($start); $end=$this->date($end);
        if($end<$start)throw new InvalidArgumentException('Período inválido.');
        $asOf=$this->date($asOf??$end); $asOf=min($asOf,$end);
        $q=$this->pdo->prepare(
            "SELECT o.id,o.series_id,o.sequence_no,o.title,o.description,o.obligation_type,o.competence_date,
                    o.due_date,o.frequency,o.amount_cents,o.status,o.completed_at,o.cancelled_at,o.created_at,
                    TRIM(CONCAT_WS(' ',u.first_name,u.last_name)) responsible_name,
                    COALESCE(a.attachment_count,0) attachment_count
             FROM organization_obligations o
             LEFT JOIN users u ON u.id=o.responsible_user_id
             LEFT JOIN (SELECT obligation_id,COUNT(*) attachment_count FROM organization_obligation_attachments
                        WHERE organization_id=? GROUP BY obligation_id) a ON a.obligation_id=o.id
             WHERE o.organization_id=? AND o.due_date BETWEEN ? AND ? AND DATE(o.created_at)<=?
             ORDER BY o.due_date,o.id"
        );
        $q->execute([$this->organizationId,$this->organizationId,$start,$end,$asOf]);
        $totals=['obligations'=>0,'declared_cents'=>0,'completed_cents'=>0,'pending_cents'=>0,'overdue_cents'=>0,'with_proof'=>0];
        $status=['pending'=>0,'upcoming'=>0,'overdue'=>0,'completed'=>0,'cancelled'=>0];
        $soon=(new DateTimeImmutable($asOf))->modify('+7 days')->format('Y-m-d'); $rows=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC)as$row){
            $row['id']=(int)$row['id']; $row['sequence_no']=(int)$row['sequence_no'];
            $row['amount_cents']=$row['amount_cents']===null?null:(int)$row['amount_cents'];
            $row['attachment_count']=(int)$row['attachment_count'];
            $completed=$row['completed_at']!==null&&substr((string)$row['completed_at'],0,10)<=$asOf;
            $cancelled=$row['cancelled_at']!==null&&substr((string)$row['cancelled_at'],0,10)<=$asOf;
            $display=$cancelled?'cancelled':($completed?'completed':($row['due_date']<$asOf?'overdue':($row['due_date']<=$soon?'upcoming':'pending')));
            $row['display_status']=$display; $status[$display]++; $totals['obligations']++;
            if($row['attachment_count']>0)$totals['with_proof']++;
            $amount=(int)($row['amount_cents']??0);
            if($display!=='cancelled')$totals['declared_cents'] += $amount;
            if($display==='completed')$totals['completed_cents'] += $amount;
            elseif(in_array($display,['pending','upcoming','overdue'],true))$totals['pending_cents'] += $amount;
            if($display==='overdue')$totals['overdue_cents'] += $amount;
            $rows[]=$row;
        }
        return['start'=>$start,'end'=>$end,'as_of'=>$asOf,'totals'=>$totals,'status'=>$status,'rows'=>$rows];
    }

    private function date(string$value):string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if($date===false||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Data inválida.');
        return$value;
    }
}
