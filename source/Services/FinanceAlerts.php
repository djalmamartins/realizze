<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Vencimentos em aberto do contexto, pelo saldo que ainda falta. É a origem dos avisos de Notifications. */
final class FinanceAlerts
{
    public function __construct(private readonly int $contextId)
    {
    }

    /** @return list<array{id:int,kind:string,description:string,amount_cents:int,due_date:string,group:string}> */
    public function upcoming(int $days = 7): array
    {
        $statement = Connection::get()->prepare(
            "SELECT e.id, e.kind, e.description, e.due_date,
                    e.amount_cents - COALESCE((SELECT SUM(s.amount_cents) FROM finance_entry_settlements AS s WHERE s.entry_id = e.id), 0) AS balance,
                    CASE WHEN e.due_date < CURDATE() THEN 'overdue' WHEN e.due_date = CURDATE() THEN 'today' ELSE 'next' END AS grp
             FROM finance_entries AS e
             WHERE e.financial_context_id = ? AND e.kind IN ('income','expense') AND e.paid_at IS NULL
               AND COALESCE(e.status, 'open') <> 'cancelled' AND e.due_date <= CURDATE() + INTERVAL ? DAY
             HAVING balance > 0
             ORDER BY e.due_date, e.id LIMIT 200"
        );
        $statement->bindValue(1, $this->contextId, PDO::PARAM_INT);
        $statement->bindValue(2, $days, PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'kind' => $r['kind'], 'description' => $r['description'],
            'amount_cents' => (int) $r['balance'], 'due_date' => $r['due_date'], 'group' => $r['grp'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
