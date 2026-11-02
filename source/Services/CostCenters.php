<?php
declare(strict_types=1);
namespace Moves\Services;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Centros de custo pertencem ao contexto da empresa; nunca expõem outros contextos. */
final class CostCenters
{
    private PDO $pdo;
    public function __construct(private readonly int $contextId) { $this->pdo = Connection::get(); }
    public function all(bool $includeInactive = false): array { $q=$this->pdo->prepare('SELECT c.id,c.name,c.code,c.status,COALESCE(SUM(CASE WHEN e.kind=\'income\' THEN e.amount_cents ELSE 0 END),0) income_cents,COALESCE(SUM(CASE WHEN e.kind=\'expense\' THEN e.amount_cents ELSE 0 END),0) expense_cents FROM finance_cost_centers c LEFT JOIN finance_entries e ON e.cost_center_id=c.id AND e.financial_context_id=c.financial_context_id WHERE c.financial_context_id=?'.($includeInactive?'':" AND c.status='active'").' GROUP BY c.id ORDER BY c.status,c.name');$q->execute([$this->contextId]);return $q->fetchAll(PDO::FETCH_ASSOC); }
    public function save(array $data,int $userId): int { $name=trim((string)($data['name']??''));$code=trim((string)($data['code']??''));if($name===''||mb_strlen($name)>100||mb_strlen($code)>40)throw new InvalidArgumentException('Informe nome e código válidos.');$status=($data['status']??'active')==='inactive'?'inactive':'active';$id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id!==false){$q=$this->pdo->prepare('UPDATE finance_cost_centers SET name=?,code=?,status=? WHERE id=? AND financial_context_id=?');$q->execute([$name,$code?:null,$status,$id,$this->contextId]);if($q->rowCount()===0)throw new InvalidArgumentException('Centro de custo não encontrado.');return (int)$id;}$q=$this->pdo->prepare('INSERT INTO finance_cost_centers (financial_context_id,name,code,status,created_by) VALUES (?,?,?,?,?)');$q->execute([$this->contextId,$name,$code?:null,$status,$userId]);return (int)$this->pdo->lastInsertId(); }
}
