<?php
declare(strict_types=1);
namespace Moves\Services;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use PDOException;

/** Centros de custo pertencem ao contexto da empresa; nunca expõem outros contextos. */
final class CostCenters
{
    private PDO $pdo;
    public function __construct(private readonly int $contextId) { $this->pdo = Connection::get(); }

    /** Lista com o total de tudo o que já foi lançado em cada centro, sem os cancelados. */
    public function all(bool $includeInactive = false): array
    {
        $q = $this->pdo->prepare("SELECT c.id,c.name,c.code,c.status,
                COALESCE(SUM(CASE WHEN e.kind='income' THEN e.amount_cents ELSE 0 END),0) income_cents,
                COALESCE(SUM(CASE WHEN e.kind='expense' THEN e.amount_cents ELSE 0 END),0) expense_cents
            FROM finance_cost_centers c
            LEFT JOIN finance_entries e ON e.cost_center_id=c.id AND e.financial_context_id=c.financial_context_id AND (e.status IS NULL OR e.status<>'cancelled')
            WHERE c.financial_context_id=?" . ($includeInactive ? '' : " AND c.status='active'") . ' GROUP BY c.id ORDER BY c.status,c.name');
        $q->execute([$this->contextId]);
        return array_map(static fn (array $r): array => ['income_cents' => (int) $r['income_cents'], 'expense_cents' => (int) $r['expense_cents']] + $r, $q->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Receitas e despesas de cada centro no período, por vencimento, separando realizado e previsto
     * como Finance::summary (baixa parcial conta no realizado). A última linha junta o que está sem centro.
     *
     * @return list<array{id:int|null,name:string,code:?string,status:string,income_done:int,income_open:int,expense_done:int,expense_open:int}>
     */
    public function totals(string $start, string $end): array
    {
        $q = $this->pdo->prepare("SELECT e.cost_center_id, e.kind,
                SUM(CASE WHEN e.paid_at IS NOT NULL THEN e.amount_cents ELSE LEAST(e.amount_cents, COALESCE(s.settled,0)) END) AS done,
                SUM(e.amount_cents) AS total
            FROM finance_entries e
            LEFT JOIN (SELECT entry_id, SUM(amount_cents) AS settled FROM finance_entry_settlements WHERE financial_context_id=? GROUP BY entry_id) s ON s.entry_id=e.id
            WHERE e.financial_context_id=? AND e.kind IN ('income','expense') AND e.due_date BETWEEN ? AND ? AND (e.status IS NULL OR e.status<>'cancelled')
            GROUP BY e.cost_center_id, e.kind");
        $q->execute([$this->contextId, $this->contextId, $start, $end]);
        $sums = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['cost_center_id'] === null ? 0 : (int) $row['cost_center_id'];
            $sums[$key][$row['kind'] . '_done'] = (int) $row['done'];
            $sums[$key][$row['kind'] . '_open'] = (int) $row['total'] - (int) $row['done'];
        }
        $zero = ['income_done' => 0, 'income_open' => 0, 'expense_done' => 0, 'expense_open' => 0];
        $rows = [];
        foreach ($this->all(true) as $center) {
            $rows[] = ['id' => (int) $center['id'], 'name' => (string) $center['name'], 'code' => $center['code'], 'status' => (string) $center['status']] + ($sums[(int) $center['id']] ?? []) + $zero;
        }
        $rows[] = ['id' => null, 'name' => 'Sem centro de custo', 'code' => null, 'status' => 'active'] + ($sums[0] ?? []) + $zero;
        return $rows;
    }

    public function save(array $data,int $userId): int
    {
        $name=trim((string)($data['name']??''));$code=trim((string)($data['code']??''));
        if($name===''||mb_strlen($name)>100||mb_strlen($code)>40)throw new InvalidArgumentException('Informe nome e código válidos.');
        $status=($data['status']??'active')==='inactive'?'inactive':'active';
        $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        try {
            if($id!==false){
                $find=$this->pdo->prepare('SELECT 1 FROM finance_cost_centers WHERE id=? AND financial_context_id=?');$find->execute([$id,$this->contextId]);
                if($find->fetchColumn()===false)throw new InvalidArgumentException('Centro de custo não encontrado.');
                $this->pdo->prepare('UPDATE finance_cost_centers SET name=?,code=?,status=? WHERE id=? AND financial_context_id=?')->execute([$name,$code?:null,$status,$id,$this->contextId]);
                return (int)$id;
            }
            $q=$this->pdo->prepare('INSERT INTO finance_cost_centers (financial_context_id,name,code,status,created_by) VALUES (?,?,?,?,?)');
            $q->execute([$this->contextId,$name,$code?:null,$status,$userId]);
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new InvalidArgumentException('Já existe um centro de custo com este nome ou código.');
            throw $e;
        }
    }
}
