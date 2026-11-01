<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;

/**
 * Planos, histórico de preços, assinaturas e cobranças da assinatura Realizze.
 * Não usa finance_*: contas a receber dos clientes são outro domínio.
 * Não há provedor de pagamento: pagamento só é registrado por pessoa autorizada e com comprovante.
 */
final class PlatformBilling
{
    // ---- Planos ----
    public static function plans(array $filters = []): array
    {
        $where = '1=1';
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (p.name LIKE ? OR p.plan_key LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        return CmsQuery::all(
            "SELECT p.*, pp.id AS price_id, pp.monthly_price, pp.annual_price, pp.currency,
                    (SELECT COUNT(*) FROM subscriptions s WHERE s.plan_id=p.id AND s.status IN ('active','past_due','pending')) AS subscribers
             FROM plans p LEFT JOIN plan_prices pp ON pp.plan_id=p.id AND pp.valid_to IS NULL WHERE {$where} ORDER BY p.sort_order, p.id",
            $args
        );
    }

    public static function plan(int $id): ?array
    {
        return CmsQuery::one(
            'SELECT p.*, pp.id AS price_id, pp.monthly_price, pp.annual_price, pp.currency FROM plans p LEFT JOIN plan_prices pp ON pp.plan_id=p.id AND pp.valid_to IS NULL WHERE p.id=?',
            [$id]
        );
    }

    public static function priceHistory(int $planId): array
    {
        return CmsQuery::all('SELECT h.*, u.email AS author FROM plan_prices h LEFT JOIN users u ON u.id=h.created_by WHERE h.plan_id=? ORDER BY h.id DESC', [$planId]);
    }

    /** Decimal exato em string: rejeita notação científica e arredondamento de float. */
    public static function money(mixed $value, string $label): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{1,2}$/', $value) === 1) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,10},\d{1,2}$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
        }
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException($label . ': valor inválido.');
        }
        return $value;
    }

    private static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function savePlan(?int $id, array $input, int $actorId): int
    {
        $name = CmsContent::text($input['name'] ?? '', 120, 'Nome');
        $key = str_slug(is_string($input['plan_key'] ?? null) && $input['plan_key'] !== '' ? $input['plan_key'] : $name);
        if ($name === '' || $key === '' || strlen($key) > 60) {
            throw new InvalidArgumentException('Informe nome e identificador válidos.');
        }
        if (CmsQuery::value('SELECT COUNT(*) FROM plans WHERE plan_key=? AND id<>?', [$key, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Identificador já utilizado.');
        }
        $limit = static function (mixed $value, string $label): ?int {
            if ($value === null || $value === '') {
                return null;
            }
            $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
            if ($number === false) {
                throw new InvalidArgumentException($label . ': use um número inteiro.');
            }
            return $number;
        };
        $lines = static fn (mixed $text): string => json_encode(array_values(array_filter(array_map(
            static fn (string $line): string => mb_substr(trim($line), 0, 160),
            preg_split('/\R/', is_string($text) ? $text : '') ?: []
        ), 'strlen')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $status = ($input['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $public = ($input['is_public'] ?? '0') === '1' ? 1 : 0;
        $values = [$key, $name, CmsContent::text($input['description'] ?? '', 500, 'Descrição'), $lines($input['benefits'] ?? ''), $lines($input['features'] ?? ''),
            $limit($input['max_companies'] ?? null, 'Empresas'), $limit($input['max_users'] ?? null, 'Usuários'),
            ($input['is_featured'] ?? '0') === '1' ? 1 : 0, max(0, (int) ($input['sort_order'] ?? 0)), $status, $public];
        $monthly = self::money($input['monthly_price'] ?? '', 'Preço mensal');
        $annual = self::money($input['annual_price'] ?? '', 'Preço anual');
        return CmsQuery::transaction(static function () use ($id, $values, $monthly, $annual, $input, $actorId, $key): int {
            if ($id === null) {
                CmsQuery::run('INSERT INTO plans (plan_key,name,description,benefits_json,features_json,max_companies,max_users,is_featured,sort_order,status,is_public) VALUES (?,?,?,?,?,?,?,?,?,?,?)', $values);
                $id = (int) Connection::get()->lastInsertId();
                CmsAudit::record($actorId, 'plan.created', 'plan', (string) $id, ['key' => $key]);
            } else {
                CmsQuery::run('UPDATE plans SET plan_key=?,name=?,description=?,benefits_json=?,features_json=?,max_companies=?,max_users=?,is_featured=?,sort_order=?,status=?,is_public=? WHERE id=?', [...$values, $id]);
                CmsAudit::record($actorId, 'plan.updated', 'plan', (string) $id, ['key' => $key, 'status' => $values[9], 'public' => $values[10]]);
            }
            self::applyPrice($id, $monthly, $annual, CmsContent::text($input['change_reason'] ?? '', 255, 'Motivo'), $actorId);
            return $id;
        });
    }

    /** Preço novo fecha a versão anterior; contratos seguem presos ao plan_price_id e ao valor contratado. */
    private static function applyPrice(int $planId, string $monthly, string $annual, string $reason, int $actorId): void
    {
        $current = CmsQuery::one('SELECT id,monthly_price,annual_price FROM plan_prices WHERE plan_id=? AND valid_to IS NULL FOR UPDATE', [$planId]);
        if ($current !== null && self::cents($current['monthly_price']) === self::cents($monthly) && self::cents($current['annual_price']) === self::cents($annual)) {
            return;
        }
        if ($current !== null) {
            if ($reason === '') {
                throw new InvalidArgumentException('Informe o motivo da alteração de preço.');
            }
            CmsQuery::run('UPDATE plan_prices SET valid_to=NOW() WHERE id=?', [$current['id']]);
        }
        CmsQuery::run('INSERT INTO plan_prices (plan_id,monthly_price,annual_price,valid_from,change_reason,created_by) VALUES (?,?,?,NOW(),?,?)', [$planId, $monthly, $annual, $reason, $actorId]);
        CmsAudit::record($actorId, 'plan.price_changed', 'plan', (string) $planId, [
            'from_monthly' => $current['monthly_price'] ?? null, 'from_annual' => $current['annual_price'] ?? null, 'monthly' => $monthly, 'annual' => $annual,
        ]);
    }

    public static function publicPlans(): array
    {
        try {
            $rows = CmsQuery::all(
                "SELECT p.plan_key,p.name,p.description,p.benefits_json,p.features_json,p.max_companies,p.max_users,p.is_featured,pp.monthly_price,pp.annual_price,pp.currency
                 FROM plans p JOIN plan_prices pp ON pp.plan_id=p.id AND pp.valid_to IS NULL
                 WHERE p.status='active' AND p.is_public=1 ORDER BY p.sort_order,p.id"
            );
        } catch (\Throwable $error) {
            error_log('Realizze site: planos indisponíveis.');
            return [];
        }
        foreach ($rows as &$row) {
            $row['benefits'] = json_decode($row['benefits_json'], true) ?: [];
            $row['features'] = json_decode($row['features_json'], true) ?: [];
        }
        return $rows;
    }

    /** Plano vigente do contexto atual, para o App. Sem dados financeiros do cliente. */
    public static function currentContract(int $userId, ?int $organizationId): ?array
    {
        $column = $organizationId !== null ? 's.organization_id' : 's.user_id';
        $row = CmsQuery::one(
            "SELECT s.status,s.billing_cycle,s.contracted_amount,s.currency,s.started_at,s.renews_at,p.name AS plan_name,p.max_companies,p.max_users,p.features_json,p.benefits_json
             FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE {$column}=? AND s.status<>'canceled' ORDER BY s.id DESC LIMIT 1",
            [$organizationId ?? $userId]
        );
        if ($row !== null) {
            $row['features'] = json_decode($row['features_json'], true) ?: [];
            $row['benefits'] = json_decode($row['benefits_json'], true) ?: [];
        }
        return $row;
    }

    // ---- Assinaturas ----
    private const OWNER_SQL = "COALESCE(o.name, CONCAT(u.first_name,' ',u.last_name)) AS owner_name, COALESCE(u.email,o.email) AS owner_email";
    private const SUB_FROM = 'FROM subscriptions s JOIN plans p ON p.id=s.plan_id LEFT JOIN users u ON u.id=s.user_id LEFT JOIN organizations o ON o.id=s.organization_id';

    public static function subscriptions(array $filters, int $page): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = "(u.email LIKE ? OR o.name LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        if (in_array($filters['status'] ?? '', ['pending', 'active', 'past_due', 'canceled'], true)) {
            $where[] = 's.status=?';
            $args[] = $filters['status'];
        }
        if ((int) ($filters['user_id'] ?? 0) > 0) {
            $where[] = 's.user_id=?';
            $args[] = (int) $filters['user_id'];
        }
        if ((int) ($filters['organization_id'] ?? 0) > 0) {
            $where[] = 's.organization_id=?';
            $args[] = (int) $filters['organization_id'];
        }
        return CmsQuery::page(self::SUB_FROM . ' WHERE ' . implode(' AND ', $where), 's.*, p.name AS plan_name, ' . self::OWNER_SQL, 's.id DESC', $args, $page);
    }

    public static function subscription(int $id): ?array
    {
        return CmsQuery::one('SELECT s.*, p.name AS plan_name, ' . self::OWNER_SQL . ' ' . self::SUB_FROM . ' WHERE s.id=?', [$id]);
    }

    public static function subscriptionEvents(int $id): array
    {
        return CmsQuery::all('SELECT e.*, x.email AS actor_email FROM subscription_events e LEFT JOIN users x ON x.id=e.actor_user_id WHERE e.subscription_id=? ORDER BY e.id DESC', [$id]);
    }

    /** Assinatura nasce pendente com a primeira cobrança; só o pagamento confirmado a ativa. */
    public static function createSubscription(array $input, int $actorId): int
    {
        $email = CmsContent::text($input['owner_email'] ?? '', 255, 'Assinante');
        $organizationId = filter_var($input['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $userId = null;
        if ($organizationId !== null) {
            if (PlatformDirectory::company($organizationId) === null) {
                throw new InvalidArgumentException('Empresa não encontrada.');
            }
        } else {
            $userId = CmsQuery::value('SELECT id FROM users WHERE email=?', [$email]);
            if ($userId === false) {
                throw new InvalidArgumentException('Usuário não encontrado para este e-mail.');
            }
            $userId = (int) $userId;
        }
        $column = $organizationId !== null ? 'organization_id' : 'user_id';
        if (CmsQuery::value("SELECT COUNT(*) FROM subscriptions WHERE {$column}=? AND status<>'canceled'", [$organizationId ?? $userId]) > 0) {
            throw new InvalidArgumentException('Este assinante já possui assinatura vigente. Altere o plano dela.');
        }
        [$plan, $cycle, $amount] = self::contractTerms($input);
        return CmsQuery::transaction(static function () use ($userId, $organizationId, $plan, $cycle, $amount, $actorId): int {
            $free = self::cents($amount) === 0;
            CmsQuery::run(
                'INSERT INTO subscriptions (user_id,organization_id,plan_id,plan_price_id,billing_cycle,contracted_amount,currency,status,started_at,renews_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$userId, $organizationId, $plan['id'], $plan['price_id'], $cycle, $amount, $plan['currency'], $free ? 'active' : 'pending', $free ? date('Y-m-d H:i:s') : null, null, $actorId]
            );
            $id = (int) Connection::get()->lastInsertId();
            self::event($id, 'created', null, $free ? 'active' : 'pending', null, (int) $plan['id'], $amount, '', $actorId);
            if (!$free) {
                self::createCharge($id, $amount, $plan['currency'], date('Y-m-d'));
            }
            CmsAudit::record($actorId, 'subscription.created', 'subscription', (string) $id, ['plan' => $plan['plan_key'], 'cycle' => $cycle, 'amount' => $amount]);
            return $id;
        });
    }

    /** Troca de plano registra o novo contrato; não altera cobranças já emitidas. */
    public static function changePlan(int $id, array $input, int $actorId): void
    {
        $sub = self::subscription($id);
        if ($sub === null || $sub['status'] === 'canceled') {
            throw new InvalidArgumentException('Assinatura não disponível para alteração.');
        }
        [$plan, $cycle, $amount] = self::contractTerms($input);
        CmsQuery::transaction(static function () use ($id, $sub, $plan, $cycle, $amount, $actorId, $input): void {
            CmsQuery::run('UPDATE subscriptions SET plan_id=?,plan_price_id=?,billing_cycle=?,contracted_amount=?,currency=? WHERE id=?', [$plan['id'], $plan['price_id'], $cycle, $amount, $plan['currency'], $id]);
            self::event($id, 'plan_changed', $sub['status'], $sub['status'], (int) $sub['plan_id'], (int) $plan['id'], $amount, CmsContent::text($input['note'] ?? '', 255, 'Observação'), $actorId);
            CmsAudit::record($actorId, 'subscription.plan_changed', 'subscription', (string) $id, ['from_plan' => (int) $sub['plan_id'], 'to_plan' => (int) $plan['id'], 'amount' => $amount]);
        });
    }

    public static function cancelSubscription(int $id, string $reason, int $actorId): void
    {
        $reason = CmsContent::text($reason, 255, 'Motivo');
        $sub = self::subscription($id);
        if ($sub === null || $sub['status'] === 'canceled') {
            throw new InvalidArgumentException('Assinatura não disponível para cancelamento.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Informe o motivo do cancelamento.');
        }
        CmsQuery::transaction(static function () use ($id, $sub, $reason, $actorId): void {
            CmsQuery::run("UPDATE subscriptions SET status='canceled',canceled_at=NOW(),cancel_reason=? WHERE id=?", [$reason, $id]);
            CmsQuery::run("UPDATE platform_charges SET status='canceled' WHERE subscription_id=? AND status IN ('pending','overdue')", [$id]);
            self::event($id, 'canceled', $sub['status'], 'canceled', null, null, null, $reason, $actorId);
            CmsAudit::record($actorId, 'subscription.canceled', 'subscription', (string) $id, ['reason' => $reason]);
        });
    }

    private static function contractTerms(array $input): array
    {
        $planId = filter_var($input['plan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $plan = $planId === false ? null : self::plan($planId);
        if ($plan === null || $plan['price_id'] === null || $plan['status'] !== 'active') {
            throw new InvalidArgumentException('Selecione um plano ativo com preço definido.');
        }
        $cycle = ($input['billing_cycle'] ?? '') === 'annual' ? 'annual' : 'monthly';
        return [$plan, $cycle, $cycle === 'annual' ? $plan['annual_price'] : $plan['monthly_price']];
    }

    private static function event(int $id, string $type, ?string $from, ?string $to, ?int $fromPlan, ?int $toPlan, ?string $amount, string $note, ?int $actor): void
    {
        CmsQuery::run(
            'INSERT INTO subscription_events (subscription_id,event_type,from_status,to_status,from_plan_id,to_plan_id,contracted_amount,note,actor_user_id) VALUES (?,?,?,?,?,?,?,?,?)',
            [$id, $type, $from, $to, $fromPlan, $toPlan, $amount, $note, $actor]
        );
    }

    // ---- Cobranças ----
    private static function createCharge(int $subscriptionId, string $amount, string $currency, string $dueDate): int
    {
        CmsQuery::run('INSERT INTO platform_charges (subscription_id,amount,currency,due_date) VALUES (?,?,?,?)', [$subscriptionId, $amount, $currency, $dueDate]);
        $id = (int) Connection::get()->lastInsertId();
        CmsQuery::run('UPDATE platform_charges SET reference=? WHERE id=?', ['RC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id]);
        return $id;
    }

    /** Emite a próxima cobrança uma vez por vencimento (idempotente pela chave única). */
    public static function issueRenewal(int $subscriptionId, int $actorId): int
    {
        $sub = self::subscription($subscriptionId);
        if ($sub === null || !in_array($sub['status'], ['active', 'past_due'], true) || $sub['renews_at'] === null) {
            throw new InvalidArgumentException('Assinatura sem renovação disponível.');
        }
        if (self::cents($sub['contracted_amount']) === 0) {
            throw new InvalidArgumentException('Plano gratuito não gera cobrança.');
        }
        $existing = CmsQuery::value('SELECT id FROM platform_charges WHERE subscription_id=? AND due_date=?', [$subscriptionId, $sub['renews_at']]);
        if ($existing !== false) {
            return (int) $existing;
        }
        $id = self::createCharge($subscriptionId, $sub['contracted_amount'], $sub['currency'], $sub['renews_at']);
        CmsAudit::record($actorId, 'charge.issued', 'charge', (string) $id, ['subscription' => $subscriptionId]);
        return $id;
    }

    public static function syncOverdue(): void
    {
        CmsQuery::run("UPDATE platform_charges SET status='overdue' WHERE status='pending' AND due_date < CURDATE()");
    }

    public static function charges(array $filters, int $page): array
    {
        self::syncOverdue();
        $where = ['1=1'];
        $args = [];
        if (isset(\Moves\Support\CmsView::CHARGE_STATUS[$filters['status'] ?? ''])) {
            $where[] = 'c.status=?';
            $args[] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = "(c.reference LIKE ? OR u.email LIKE ? OR o.name LIKE ?)";
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        return CmsQuery::page(
            'FROM platform_charges c JOIN subscriptions s ON s.id=c.subscription_id JOIN plans p ON p.id=s.plan_id LEFT JOIN users u ON u.id=s.user_id LEFT JOIN organizations o ON o.id=s.organization_id WHERE ' . implode(' AND ', $where),
            'c.*, p.name AS plan_name, ' . self::OWNER_SQL, 'c.due_date DESC, c.id DESC', $args, $page
        );
    }

    public static function subscriptionCharges(int $subscriptionId): array
    {
        self::syncOverdue();
        return CmsQuery::all('SELECT * FROM platform_charges WHERE subscription_id=? ORDER BY due_date DESC, id DESC', [$subscriptionId]);
    }

    public static function charge(int $id): ?array
    {
        self::syncOverdue();
        return CmsQuery::one('SELECT c.*, s.status AS subscription_status, s.billing_cycle, s.renews_at, p.name AS plan_name, ' . self::OWNER_SQL . ' FROM platform_charges c JOIN subscriptions s ON s.id=c.subscription_id JOIN plans p ON p.id=s.plan_id LEFT JOIN users u ON u.id=s.user_id LEFT JOIN organizations o ON o.id=s.organization_id WHERE c.id=?', [$id]);
    }

    public static function attempts(int $chargeId): array
    {
        return CmsQuery::all('SELECT a.*, u.email AS actor_email FROM platform_charge_attempts a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.charge_id=? ORDER BY a.id DESC', [$chargeId]);
    }

    /**
     * Confirmação manual: exige referência do comprovante, única por provedor.
     * Reenvio com a mesma referência não duplica nem reativa nada.
     */
    public static function confirmPayment(int $chargeId, string $reference, int $actorId): void
    {
        $reference = CmsContent::text($reference, 120, 'Comprovante');
        if ($reference === '') {
            throw new InvalidArgumentException('Informe a referência do comprovante para confirmar o pagamento.');
        }
        CmsQuery::transaction(static function () use ($chargeId, $reference, $actorId): void {
            $charge = CmsQuery::one('SELECT * FROM platform_charges WHERE id=? FOR UPDATE', [$chargeId]);
            if ($charge === null) {
                throw new InvalidArgumentException('Cobrança não encontrada.');
            }
            if ($charge['status'] === 'paid') {
                if ($charge['provider_reference'] === $reference) {
                    return;
                }
                throw new InvalidArgumentException('Esta cobrança já está paga.');
            }
            if ($charge['status'] === 'canceled') {
                throw new InvalidArgumentException('Cobrança cancelada não pode ser paga.');
            }
            if (CmsQuery::value("SELECT COUNT(*) FROM platform_charges WHERE provider='manual' AND provider_reference=? AND id<>?", [$reference, $chargeId]) > 0) {
                throw new InvalidArgumentException('Este comprovante já foi usado em outra cobrança.');
            }
            CmsQuery::run("UPDATE platform_charges SET status='paid',paid_at=NOW(),provider='manual',provider_reference=? WHERE id=?", [$reference, $chargeId]);
            CmsQuery::run("INSERT INTO platform_charge_attempts (charge_id,outcome,provider,provider_reference,note,actor_user_id) VALUES (?,'paid','manual',?,'Confirmação manual',?)", [$chargeId, $reference, $actorId]);
            $sub = CmsQuery::one('SELECT * FROM subscriptions WHERE id=? FOR UPDATE', [$charge['subscription_id']]);
            if ($sub['status'] !== 'canceled') {
                $base = max(strtotime($charge['due_date']), strtotime($sub['renews_at'] ?? '1970-01-01'));
                $renews = date('Y-m-d', strtotime($sub['billing_cycle'] === 'annual' ? '+1 year' : '+1 month', $base));
                CmsQuery::run("UPDATE subscriptions SET status='active',started_at=COALESCE(started_at,NOW()),renews_at=? WHERE id=?", [$renews, $sub['id']]);
                self::event((int) $sub['id'], 'payment_confirmed', $sub['status'], 'active', null, null, $charge['amount'], $charge['reference'], $actorId);
            }
            CmsAudit::record($actorId, 'charge.paid', 'charge', (string) $chargeId, ['reference' => $charge['reference']]);
        });
    }

    public static function registerFailure(int $chargeId, string $note, int $actorId): void
    {
        $note = CmsContent::text($note, 255, 'Motivo');
        if ($note === '') {
            throw new InvalidArgumentException('Informe o motivo da falha.');
        }
        CmsQuery::transaction(static function () use ($chargeId, $note, $actorId): void {
            $charge = CmsQuery::one('SELECT * FROM platform_charges WHERE id=? FOR UPDATE', [$chargeId]);
            if ($charge === null || in_array($charge['status'], ['paid', 'canceled'], true)) {
                throw new InvalidArgumentException('Cobrança não disponível para esta ação.');
            }
            CmsQuery::run("UPDATE platform_charges SET status='failed' WHERE id=?", [$chargeId]);
            CmsQuery::run("INSERT INTO platform_charge_attempts (charge_id,outcome,note,actor_user_id) VALUES (?,'failed',?,?)", [$chargeId, $note, $actorId]);
            $sub = CmsQuery::one('SELECT id,status FROM subscriptions WHERE id=? FOR UPDATE', [$charge['subscription_id']]);
            if ($sub['status'] === 'active') {
                CmsQuery::run("UPDATE subscriptions SET status='past_due' WHERE id=?", [$sub['id']]);
                self::event((int) $sub['id'], 'payment_failed', 'active', 'past_due', null, null, $charge['amount'], $note, $actorId);
            }
            CmsAudit::record($actorId, 'charge.failed', 'charge', (string) $chargeId);
        });
    }
}
