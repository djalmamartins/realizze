<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/** Faturamento gerencial baseado exclusivamente em Contas a Receber. */
final class BillingReport
{
    private PDO $pdo;

    public function __construct(private readonly int $contextId)
    {
        if ($contextId <= 0) throw new InvalidArgumentException('Contexto financeiro inválido.');
        $this->pdo = Connection::get();
    }

    /**
     * Faturado = receitas reconhecidas pela competência no período.
     * Recebido no período = baixas cuja data efetiva pertence ao período.
     * Em aberto = saldo das receitas faturadas no período, considerando baixas até o fim dele.
     *
     * @return array{start:string,end:string,totals:array<string,int>,series:array<string,array{billed:int,received:int}>,rows:list<array<string,mixed>>}
     */
    public function report(string $start, string $end): array
    {
        $startDate = $this->date($start);
        $endDate = $this->date($end);
        if ($endDate < $startDate) throw new InvalidArgumentException('Período inválido.');

        $orderColumns = $this->serviceOrdersReady()
            ? ',o.id AS service_order_id,o.order_number'
            : ',NULL AS service_order_id,NULL AS order_number';
        $orderJoin = $this->serviceOrdersReady()
            ? 'LEFT JOIN organization_service_orders o ON o.receivable_entry_id=e.id AND o.financial_context_id=e.financial_context_id'
            : '';
        $statement = $this->pdo->prepare(
            "SELECT e.id,e.description,e.amount_cents,e.due_date,COALESCE(e.competency_date,e.due_date) AS competency_date,
                    COALESCE(NULLIF(c.trade_name,''),NULLIF(c.social_name,''),c.legal_name,e.counterparty_name) AS customer_name,
                    e.document_number,COALESCE(SUM(CASE WHEN s.received_at <= ? THEN s.amount_cents ELSE 0 END),0) AS received_cents
                    {$orderColumns}
             FROM finance_entries e
             LEFT JOIN finance_contacts c ON c.id=e.contact_id AND c.financial_context_id=e.financial_context_id
             LEFT JOIN finance_entry_settlements s ON s.entry_id=e.id AND s.financial_context_id=e.financial_context_id
             {$orderJoin}
             WHERE e.financial_context_id=? AND e.kind='income'
               AND COALESCE(e.competency_date,e.due_date) BETWEEN ? AND ?
               AND (e.status IS NULL OR e.status <> 'cancelled')
             GROUP BY e.id
             ORDER BY competency_date,e.id"
        );
        $statement->execute([$end,$this->contextId,$start,$end]);

        $rows = [];
        $totals = [
            'billed_cents' => 0, 'received_for_billed_cents' => 0, 'open_cents' => 0,
            'cash_received_cents' => 0, 'overdue_cents' => 0, 'service_order_cents' => 0,
            'manual_cents' => 0, 'entries' => 0,
        ];
        $series = [];
        for ($cursor = $startDate->modify('first day of this month'); $cursor <= $endDate; $cursor = $cursor->modify('+1 month')) {
            $series[$cursor->format('Y-m')] = ['billed' => 0, 'received' => 0];
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (int) $row['id'];
            $row['amount_cents'] = (int) $row['amount_cents'];
            $row['received_cents'] = min($row['amount_cents'], (int) $row['received_cents']);
            $row['open_cents'] = max(0, $row['amount_cents'] - $row['received_cents']);
            $row['service_order_id'] = $row['service_order_id'] === null ? null : (int) $row['service_order_id'];
            $row['origin'] = $row['service_order_id'] === null ? 'manual' : 'service_order';
            $isOverdue = $row['open_cents'] > 0 && $row['due_date'] < $end;
            $row['status'] = $row['open_cents'] === 0 ? 'received'
                : ($isOverdue ? ($row['received_cents'] > 0 ? 'partial_overdue' : 'overdue')
                    : ($row['received_cents'] > 0 ? 'partial' : 'open'));
            $totals['billed_cents'] += $row['amount_cents'];
            $totals['received_for_billed_cents'] += $row['received_cents'];
            $totals['open_cents'] += $row['open_cents'];
            $totals[$row['origin'] === 'service_order' ? 'service_order_cents' : 'manual_cents'] += $row['amount_cents'];
            if ($isOverdue) $totals['overdue_cents'] += $row['open_cents'];
            $totals['entries']++;
            $series[substr((string) $row['competency_date'],0,7)]['billed'] += $row['amount_cents'];
            $rows[] = $row;
        }

        $cash = $this->pdo->prepare(
            "SELECT DATE_FORMAT(s.received_at,'%Y-%m') AS month,SUM(s.amount_cents) AS amount_cents
             FROM finance_entry_settlements s
             INNER JOIN finance_entries e ON e.id=s.entry_id AND e.financial_context_id=s.financial_context_id
             WHERE s.financial_context_id=? AND e.kind='income' AND s.received_at BETWEEN ? AND ?
               AND (e.status IS NULL OR e.status <> 'cancelled')
             GROUP BY month"
        );
        $cash->execute([$this->contextId,$start,$end]);
        foreach ($cash->fetchAll(PDO::FETCH_ASSOC) as $received) {
            $amount = (int) $received['amount_cents'];
            $totals['cash_received_cents'] += $amount;
            $series[$received['month']]['received'] += $amount;
        }

        return ['start'=>$start,'end'=>$end,'totals'=>$totals,'series'=>$series,'rows'=>$rows];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if ($date === false || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Período inválido.');
        return $date;
    }

    private function serviceOrdersReady(): bool
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute(['organization_service_orders']);
        return (int)$q->fetchColumn() === 1;
    }
}
