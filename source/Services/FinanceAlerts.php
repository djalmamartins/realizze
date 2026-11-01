<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Avisos calculados dos lançamentos em aberto do contexto: nada é enviado nem gravado. */
final class FinanceAlerts
{
    public function __construct(private readonly int $contextId)
    {
    }

    /** @return list<array{id:int,kind:string,description:string,amount_cents:int,due_date:string,group:string}> */
    public function upcoming(int $days = 7): array
    {
        $statement = Connection::get()->prepare(
            "SELECT id, kind, description, amount_cents, due_date,
                    CASE WHEN due_date < CURDATE() THEN 'overdue' WHEN due_date = CURDATE() THEN 'today' ELSE 'next' END AS grp
             FROM finance_entries
             WHERE financial_context_id = ? AND kind IN ('income','expense') AND paid_at IS NULL
               AND COALESCE(status, 'open') <> 'cancelled' AND due_date <= CURDATE() + INTERVAL ? DAY
             ORDER BY due_date, id LIMIT 200"
        );
        $statement->bindValue(1, $this->contextId, PDO::PARAM_INT);
        $statement->bindValue(2, $days, PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'kind' => $r['kind'], 'description' => $r['description'],
            'amount_cents' => (int) $r['amount_cents'], 'due_date' => $r['due_date'], 'group' => $r['grp'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
