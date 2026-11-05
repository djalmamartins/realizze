<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** DRE gerencial: relatório de gestão, não demonstração contábil oficial. */
final class ManagementDre
{
    private PDO $pdo;
    public function __construct(private readonly int $contextId) { $this->pdo = Connection::get(); }

    /** @return array{groups:array<string,array{label:string,total:int,rows:list<array<string,mixed>>}>,result:int} */
    public function report(string $start, string $end, string $criterion = 'competence'): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) throw new InvalidArgumentException('Período inválido.');
        $criterion = $criterion === 'cash' ? 'cash' : 'competence';
        if ($criterion === 'cash') {
            $sql = "SELECT e.kind,e.description,e.due_date,s.received_at AS occurred_at,s.amount_cents,c.name AS category
                    FROM finance_entry_settlements s INNER JOIN finance_entries e ON e.id=s.entry_id AND e.financial_context_id=s.financial_context_id
                    LEFT JOIN finance_categories c ON c.id=e.category_id
                    WHERE s.financial_context_id=? AND s.received_at BETWEEN ? AND ? AND e.kind IN ('income','expense') AND (e.status IS NULL OR e.status <> 'cancelled')";
        } else {
            $sql = "SELECT e.kind,e.description,e.due_date,COALESCE(e.competency_date,e.due_date) AS occurred_at,e.amount_cents,c.name AS category
                    FROM finance_entries e LEFT JOIN finance_categories c ON c.id=e.category_id
                    WHERE e.financial_context_id=? AND COALESCE(e.competency_date,e.due_date) BETWEEN ? AND ? AND e.kind IN ('income','expense') AND (e.status IS NULL OR e.status <> 'cancelled')";
        }
        $q=$this->pdo->prepare($sql); $q->execute([$this->contextId,$start,$end]);
        $groups=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group=$this->groupFor((string)$row['kind'], (string)($row['category'] ?? ''));
            $groups[$group['key']] ??=['label'=>$group['label'],'total'=>0,'rows'=>[]];
            $row['amount_cents']=(int)$row['amount_cents']; $groups[$group['key']]['total'] += $row['amount_cents']; $groups[$group['key']]['rows'][]=$row;
        }
        foreach (['gross'=>'Receita bruta','deductions'=>'Deduções','costs'=>'Custos','operating'=>'Despesas operacionais','other_income'=>'Outras receitas','other_expense'=>'Outras despesas'] as $key=>$label) $groups[$key] ??=['label'=>$label,'total'=>0,'rows'=>[]];
        $result=$groups['gross']['total']-$groups['deductions']['total']-$groups['costs']['total']-$groups['operating']['total']+$groups['other_income']['total']-$groups['other_expense']['total'];
        return ['groups'=>$groups,'result'=>$result];
    }
    /** Sem plano de contas contábil, usa classificação explícita por prefixo de categoria. */
    private function groupFor(string $kind,string $category): array
    {
        $name=mb_strtolower(trim($category));
        if ($kind==='income') return str_starts_with($name,'outra receita')?['key'=>'other_income','label'=>'Outras receitas']:['key'=>'gross','label'=>'Receita bruta'];
        if (str_starts_with($name,'deduç') || str_starts_with($name,'impost')) return ['key'=>'deductions','label'=>'Deduções'];
        if (str_starts_with($name,'custo') || str_starts_with($name,'cmv')) return ['key'=>'costs','label'=>'Custos'];
        return str_starts_with($name,'outra despesa')?['key'=>'other_expense','label'=>'Outras despesas']:['key'=>'operating','label'=>'Despesas operacionais'];
    }
}
