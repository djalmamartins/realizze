<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** DRE gerencial: relatório de gestão, não demonstração contábil oficial. */
final class ManagementDre
{
    private const GROUPS = [
        'gross' => 'Receita bruta', 'deductions' => 'Deduções', 'costs' => 'Custos',
        'operating' => 'Despesas operacionais', 'other_income' => 'Outras receitas',
        'other_expense' => 'Outras despesas',
    ];
    private PDO $pdo;
    public function __construct(private readonly int $contextId) { $this->pdo = Connection::get(); }

    /** @return array{criterion:string,start:string,end:string,groups:array<string,array{label:string,total:int,rows:list<array<string,mixed>>}>,result:int} */
    public function report(string $start, string $end, string $criterion = 'competence'): array
    {
        $validDate = static function (string $value): bool {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        if (!$validDate($start) || !$validDate($end) || $end < $start) throw new InvalidArgumentException('Período inválido.');
        $criterion = $criterion === 'cash' ? 'cash' : 'competence';
        $classification = $this->classificationReady() ? 'c.management_dre_group' : 'NULL';
        if ($criterion === 'cash') {
            // Caixa reconhece cada movimentação efetiva. Compras no cartão só entram quando a
            // fatura é paga, rateadas pelas alocações para preservar categoria e rastreabilidade.
            $sql = "SELECT e.id AS entry_id,e.kind,e.description,e.due_date,e.paid_at AS occurred_at,e.amount_cents,c.name AS category,{$classification} AS management_dre_group,'direct' AS source
                    FROM finance_entries e
                    INNER JOIN finance_accounts a ON a.id=e.account_id AND a.financial_context_id=e.financial_context_id
                    LEFT JOIN finance_categories c ON c.id=e.category_id AND c.financial_context_id=e.financial_context_id
                    WHERE e.financial_context_id=? AND e.paid_at BETWEEN ? AND ? AND e.kind IN ('income','expense')
                      AND a.type <> 'credit_card' AND (e.status IS NULL OR e.status <> 'cancelled')
                      AND NOT EXISTS (SELECT 1 FROM finance_entry_settlements x WHERE x.entry_id=e.id)
                    UNION ALL
                    SELECT e.id,e.kind,e.description,e.due_date,s.received_at,s.amount_cents,c.name,{$classification},'settlement'
                    FROM finance_entry_settlements s
                    INNER JOIN finance_entries e ON e.id=s.entry_id AND e.financial_context_id=s.financial_context_id
                    INNER JOIN finance_accounts a ON a.id=s.account_id AND a.financial_context_id=s.financial_context_id
                    LEFT JOIN finance_categories c ON c.id=e.category_id AND c.financial_context_id=e.financial_context_id
                    WHERE s.financial_context_id=? AND s.received_at BETWEEN ? AND ? AND e.kind IN ('income','expense')
                      AND a.type <> 'credit_card' AND (e.status IS NULL OR e.status <> 'cancelled')";
            $parameters = [$this->contextId,$start,$end,$this->contextId,$start,$end];
            if ($this->cardPaymentsReady()) {
                $sql .= " UNION ALL
                    SELECT e.id,e.kind,e.description,e.due_date,p.paid_on,a.amount_cents,c.name,{$classification},'card_payment'
                    FROM finance_card_payment_allocations a
                    INNER JOIN finance_card_invoice_payments p ON p.id=a.payment_id AND p.financial_context_id=a.financial_context_id
                    INNER JOIN finance_entries e ON e.id=a.card_entry_id AND e.financial_context_id=a.financial_context_id
                    LEFT JOIN finance_categories c ON c.id=e.category_id AND c.financial_context_id=e.financial_context_id
                    WHERE a.financial_context_id=? AND p.paid_on BETWEEN ? AND ? AND p.reversed_at IS NULL
                      AND e.kind='expense' AND (e.status IS NULL OR e.status <> 'cancelled')";
                array_push($parameters,$this->contextId,$start,$end);
            }
        } else {
            $sql = "SELECT e.id AS entry_id,e.kind,e.description,e.due_date,COALESCE(e.competency_date,e.due_date) AS occurred_at,e.amount_cents,c.name AS category,{$classification} AS management_dre_group,'competence' AS source
                    FROM finance_entries e LEFT JOIN finance_categories c ON c.id=e.category_id AND c.financial_context_id=e.financial_context_id
                    WHERE e.financial_context_id=? AND COALESCE(e.competency_date,e.due_date) BETWEEN ? AND ? AND e.kind IN ('income','expense') AND (e.status IS NULL OR e.status <> 'cancelled')";
            $parameters = [$this->contextId,$start,$end];
        }
        $q=$this->pdo->prepare($sql); $q->execute($parameters);
        $groups=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group=$this->groupFor((string)$row['kind'], (string)($row['category'] ?? ''), $row['management_dre_group'] === null ? null : (string)$row['management_dre_group']);
            $groups[$group['key']] ??=['label'=>$group['label'],'total'=>0,'rows'=>[]];
            $row['amount_cents']=(int)$row['amount_cents']; $groups[$group['key']]['total'] += $row['amount_cents']; $groups[$group['key']]['rows'][]=$row;
        }
        foreach (self::GROUPS as $key=>$label) $groups[$key] ??=['label'=>$label,'total'=>0,'rows'=>[]];
        $result=$groups['gross']['total']-$groups['deductions']['total']-$groups['costs']['total']-$groups['operating']['total']+$groups['other_income']['total']-$groups['other_expense']['total'];
        return ['criterion'=>$criterion,'start'=>$start,'end'=>$end,'groups'=>$groups,'result'=>$result];
    }

    private function cardPaymentsReady(): bool
    {
        foreach (['finance_card_payment_allocations','finance_card_invoice_payments'] as $table) {
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
            $q->execute([$table]);
            if ((int)$q->fetchColumn() !== 1) return false;
        }
        return true;
    }

    public function classificationReady(): bool
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $q->execute(['finance_categories','management_dre_group']);
        return (int)$q->fetchColumn()===1;
    }

    /** @return list<array{id:int,name:string,kind:string,configured_group:?string,effective_group:string,effective_label:string}> */
    public function categoryClassifications(): array
    {
        $column=$this->classificationReady()?'management_dre_group':'NULL AS management_dre_group';
        $q=$this->pdo->prepare("SELECT id,name,kind,{$column} FROM finance_categories WHERE financial_context_id=? ORDER BY kind DESC,name");
        $q->execute([$this->contextId]);
        return array_map(function(array$row):array{
            $configured=$row['management_dre_group']===null?null:(string)$row['management_dre_group'];
            $effective=$this->groupFor((string)$row['kind'],(string)$row['name'],$configured);
            return ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'kind'=>(string)$row['kind'],'configured_group'=>$configured,'effective_group'=>$effective['key'],'effective_label'=>$effective['label']];
        },$q->fetchAll(PDO::FETCH_ASSOC));
    }

    public function saveCategoryClassification(int $categoryId, ?string $group): bool
    {
        if(!$this->classificationReady()) throw new InvalidArgumentException('A estrutura de classificação da DRE ainda não está disponível.');
        $group=$group===null||$group===''?null:$group;
        $q=$this->pdo->prepare('SELECT kind FROM finance_categories WHERE id=? AND financial_context_id=?');
        $q->execute([$categoryId,$this->contextId]);
        $kind=$q->fetchColumn();
        if(!is_string($kind)) return false;
        $allowed=$kind==='income'?['gross','other_income']:['deductions','costs','operating','other_expense'];
        if($group!==null&&!in_array($group,$allowed,true)) throw new InvalidArgumentException('Grupo incompatível com o tipo da categoria.');
        $update=$this->pdo->prepare('UPDATE finance_categories SET management_dre_group=? WHERE id=? AND financial_context_id=?');
        $update->execute([$group,$categoryId,$this->contextId]);
        return $update->rowCount()===1 || $group===$this->configuredGroup($categoryId);
    }

    private function configuredGroup(int $categoryId): ?string
    {
        $q=$this->pdo->prepare('SELECT management_dre_group FROM finance_categories WHERE id=? AND financial_context_id=?');
        $q->execute([$categoryId,$this->contextId]);
        $value=$q->fetchColumn();
        return $value===false||$value===null?null:(string)$value;
    }

    /** Sem configuração explícita, mantém o fallback compatível por prefixo de categoria. */
    private function groupFor(string $kind,string $category,?string $configured=null): array
    {
        $allowed=$kind==='income'?['gross','other_income']:['deductions','costs','operating','other_expense'];
        if($configured!==null&&in_array($configured,$allowed,true)) return ['key'=>$configured,'label'=>self::GROUPS[$configured]];
        $name=mb_strtolower(trim($category));
        if ($kind==='income') return str_starts_with($name,'outra receita')?['key'=>'other_income','label'=>'Outras receitas']:['key'=>'gross','label'=>'Receita bruta'];
        if (str_starts_with($name,'deduç') || str_starts_with($name,'impost')) return ['key'=>'deductions','label'=>'Deduções'];
        if (str_starts_with($name,'custo') || str_starts_with($name,'cmv')) return ['key'=>'costs','label'=>'Custos'];
        return str_starts_with($name,'outra despesa')?['key'=>'other_expense','label'=>'Outras despesas']:['key'=>'operating','label'=>'Despesas operacionais'];
    }
}
