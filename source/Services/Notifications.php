<?php
declare(strict_types=1);

namespace Moves\Services;

use DateTimeImmutable;
use JsonException;

/**
 * Notificações do usuário no App. Avisos financeiros são recalculados e gravados em
 * `notifications`, um por registro: o aviso muda de estágio em vez de se repetir e
 * sai da lista quando deixa de valer. Sem a migration 109, mostra os avisos calculados,
 * sem situação de leitura.
 */
final class Notifications
{
    public const FILTERS = ['unread' => 'Não lidas', 'financeiro' => 'Financeiro', 'comunicado' => 'Comunicados', 'suporte' => 'Suporte'];
    public const DUE_DAYS = [7 => '7 dias antes', 3 => '3 dias antes', 1 => '1 dia antes', 0 => 'Só no dia do vencimento'];
    private const CATEGORY_LABELS = ['financeiro' => 'Financeiro', 'comunicado' => 'Comunicado', 'suporte' => 'Suporte'];
    private const NOTES_PAGE = '/suporte/notificacoes';

    private static ?bool $supported = null;

    public static function supported(): bool
    {
        return self::$supported ??= CmsQuery::all("SHOW COLUMNS FROM notifications LIKE 'target_json'") !== [];
    }

    /**
     * Quem está na sessão e qual área financeira pode ver. Sem finance.manage, a empresa não entra.
     * @return array{user_id:int,context_id:int}|null
     */
    public static function scope(): ?array
    {
        $user = SessionAuth::current();
        if ($user === null) {
            return null;
        }
        $userId = (int) $user['id'];
        $organization = OrganizationContext::current();
        $allowed = $organization === null || (new Authorization())->can($userId, (int) $organization['id'], 'finance.manage');
        return ['user_id' => $userId, 'context_id' => $allowed ? (int) FinanceContext::currentId() : 0];
    }

    /** Entrega o que estiver pendente e devolve a caixa: é o que o sininho chama a cada tela. */
    public static function refresh(array $scope, int $limit = 100): array
    {
        CmsBroadcasts::dispatchDue();
        if (self::supported() && $scope['context_id'] > 0) {
            self::sync($scope['user_id'], $scope['context_id']);
        }
        return self::feed($scope, $limit);
    }

    /** Sincroniza os avisos financeiros de um usuário em uma área. Retorna quantos mudaram. */
    public static function sync(int $userId, int $contextId): int
    {
        $wanted = self::desired($contextId, self::preferences($userId));
        $current = [];
        foreach (CmsQuery::all(
            "SELECT id,dedupe_key,event_type,title,body FROM notifications WHERE user_id=? AND audience='customer' AND financial_context_id=?
             AND event_type LIKE 'finance.%' AND (expires_at IS NULL OR expires_at > NOW())",
            [$userId, $contextId]
        ) as $row) {
            $current[$row['dedupe_key']] = $row;
        }
        $changed = 0;
        foreach ($wanted as $key => $note) {
            $known = $current[$key] ?? null;
            if ($known !== null && $known['event_type'] === $note['event_type'] && $known['title'] === $note['title'] && $known['body'] === $note['body']) {
                continue;
            }
            // Mudar de estágio volta a chamar atenção; mudar só o texto não. event_type vem por último: o MySQL aplica em ordem.
            CmsQuery::run(
                "INSERT INTO notifications (user_id,audience,financial_context_id,event_type,title,body,priority,target_json,dedupe_key,created_at)
                 VALUES (?,'customer',?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE
                   read_at=IF(event_type=VALUES(event_type),read_at,NULL), created_at=IF(event_type=VALUES(event_type),created_at,NOW()),
                   title=VALUES(title), body=VALUES(body), priority=VALUES(priority), target_json=VALUES(target_json), expires_at=NULL,
                   event_type=VALUES(event_type)",
                [$userId, $contextId, $note['event_type'], $note['title'], $note['body'], $note['priority'], json_encode($note['target'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $key]
            );
            $changed++;
        }
        foreach (array_diff_key($current, $wanted) as $row) {
            CmsQuery::run('UPDATE notifications SET expires_at=NOW() WHERE id=?', [$row['id']]);
            $changed++;
        }
        return $changed;
    }

    /** Para o processador agendado: todas as áreas com algo a avisar ou a retirar. */
    public static function syncAll(): int
    {
        if (!self::supported()) {
            return 0;
        }
        $contexts = CmsQuery::all(
            "SELECT financial_context_id AS id FROM finance_entries WHERE paid_at IS NULL AND due_date <= CURDATE() + INTERVAL 7 DAY
             UNION SELECT financial_context_id FROM finance_budgets WHERE month = DATE_FORMAT(CURDATE(), '%Y-%m')
             UNION SELECT financial_context_id FROM finance_goals WHERE status = 'active' AND deadline <= CURDATE() + INTERVAL 7 DAY
             UNION SELECT financial_context_id FROM notifications WHERE event_type LIKE 'finance.%' AND financial_context_id IS NOT NULL AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $changed = 0;
        foreach ($contexts as $context) {
            foreach (self::recipients((int) $context['id']) as $userId) {
                $changed += self::sync($userId, (int) $context['id']);
            }
        }
        return $changed;
    }

    /** @return list<int> */
    private static function recipients(int $contextId): array
    {
        $personal = CmsQuery::all(
            "SELECT u.id FROM financial_contexts c INNER JOIN users u ON u.id=c.personal_user_id WHERE c.id=? AND u.status='active'",
            [$contextId]
        );
        if ($personal !== []) {
            return [(int) $personal[0]['id']];
        }
        $members = CmsQuery::all(
            "SELECT m.user_id, m.organization_id FROM financial_contexts c
             INNER JOIN organization_members m ON m.organization_id=c.organization_id AND m.status='active'
             INNER JOIN users u ON u.id=m.user_id AND u.status='active' WHERE c.id=?",
            [$contextId]
        );
        $authorization = new Authorization();
        $allowed = array_filter($members, static fn (array $m): bool => $authorization->can((int) $m['user_id'], (int) $m['organization_id'], 'finance.manage'));
        return array_map(static fn (array $m): int => (int) $m['user_id'], array_values($allowed));
    }

    /** @return array{due_days:int,overdue_enabled:bool,budget_enabled:bool,goal_enabled:bool} */
    public static function preferences(int $userId): array
    {
        $saved = self::supported() ? CmsQuery::all('SELECT due_days,overdue_enabled,budget_enabled,goal_enabled FROM notification_preferences WHERE user_id=?', [$userId]) : [];
        $row = $saved[0] ?? ['due_days' => 7, 'overdue_enabled' => 1, 'budget_enabled' => 1, 'goal_enabled' => 1];
        return [
            'due_days' => (int) $row['due_days'], 'overdue_enabled' => (bool) $row['overdue_enabled'],
            'budget_enabled' => (bool) $row['budget_enabled'], 'goal_enabled' => (bool) $row['goal_enabled'],
        ];
    }

    public static function savePreferences(int $userId, array $input): void
    {
        $days = (int) ($input['due_days'] ?? 7);
        CmsQuery::run(
            'INSERT INTO notification_preferences (user_id,due_days,overdue_enabled,budget_enabled,goal_enabled) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE due_days=VALUES(due_days), overdue_enabled=VALUES(overdue_enabled), budget_enabled=VALUES(budget_enabled), goal_enabled=VALUES(goal_enabled)',
            [$userId, isset(self::DUE_DAYS[$days]) ? $days : 7, isset($input['overdue_enabled']) ? 1 : 0, isset($input['budget_enabled']) ? 1 : 0, isset($input['goal_enabled']) ? 1 : 0]
        );
    }

    /** Tempo curto para a lista: "há 2 h", "ontem". */
    public static function ago(string $createdAt): string
    {
        $created = new DateTimeImmutable($createdAt);
        $seconds = time() - $created->getTimestamp();
        $days = (int) (new DateTimeImmutable('today'))->diff($created->setTime(0, 0))->days;
        return match (true) {
            $seconds < 60 => 'agora',
            $seconds < 3600 => 'há ' . (int) ($seconds / 60) . ' min',
            $days === 0 => 'há ' . (int) ($seconds / 3600) . ' h',
            $days === 1 => 'ontem',
            $days < 7 => 'há ' . $days . ' dias',
            default => $created->format('d/m/Y'),
        };
    }

    /** O que deveria estar avisado agora nesta área, para estas preferências, por chave única. */
    private static function desired(int $contextId, array $preferences): array
    {
        $notes = [];
        foreach ((new FinanceAlerts($contextId))->upcoming($preferences['due_days']) as $entry) {
            if ($entry['group'] === 'overdue' && !$preferences['overdue_enabled']) {
                continue;
            }
            $income = $entry['kind'] === 'income';
            $date = (new DateTimeImmutable($entry['due_date']))->format('d/m/Y');
            [$event, $priority, $title] = match ($entry['group']) {
                'overdue' => ['finance.overdue', 'critical', $income ? 'Recebimento em atraso' : 'Conta a pagar em atraso'],
                'today' => ['finance.due_today', 'warning', $income ? 'Recebimento previsto para hoje' : 'Conta a pagar vence hoje'],
                default => ['finance.due_soon', 'info', ($income ? 'Recebimento previsto para ' : 'Conta a pagar vence em ') . $date],
            };
            $params = $income ? ['inicio' => $entry['due_date'], 'fim' => $entry['due_date']] : ['mes' => substr($entry['due_date'], 0, 7)];
            $notes['finance:entry:' . $entry['id'] . ':' . $entry['due_date']] = [
                'event_type' => $event, 'priority' => $priority, 'title' => $title,
                'body' => $entry['description'] . ' · ' . Finance::money($entry['amount_cents']) . ' · vencimento ' . $date,
                'target' => ['screen' => $income ? 'receber' : 'pagar', 'entity' => 'finance_entry', 'id' => $entry['id'], 'params' => $params],
            ];
        }
        $month = date('Y-m');
        foreach ($preferences['budget_enabled'] ? (new Finance($contextId))->budgets($month) : [] as $budget) {
            if ($budget['budget'] > 0 && $budget['spent'] > $budget['budget']) {
                $notes['finance:budget:' . $budget['category_id'] . ':' . $month] = [
                    'event_type' => 'finance.budget_exceeded', 'priority' => 'warning', 'title' => 'Orçamento ultrapassado: ' . mb_substr((string) $budget['name'], 0, 120),
                    'body' => 'Gasto de ' . Finance::money($budget['spent']) . ' para um orçamento de ' . Finance::money($budget['budget']) . ' no mês.',
                    'target' => ['screen' => 'planejamento', 'entity' => 'finance_budget', 'id' => (int) $budget['category_id'], 'params' => ['mes' => $month]],
                ];
            }
        }
        foreach ($preferences['goal_enabled'] ? CmsQuery::all(
            "SELECT id,name,target_cents,saved_cents,deadline,deadline < CURDATE() AS late FROM finance_goals
             WHERE financial_context_id=? AND status='active' AND saved_cents < target_cents AND deadline <= CURDATE() + INTERVAL 7 DAY ORDER BY deadline,id LIMIT 50",
            [$contextId]
        ) : [] as $goal) {
            $notes['finance:goal:' . $goal['id'] . ':' . $goal['deadline']] = [
                'event_type' => 'finance.goal_deadline', 'priority' => $goal['late'] ? 'warning' : 'info', 'title' => $goal['late'] ? 'Prazo da meta encerrado' : 'Meta perto do prazo',
                'body' => $goal['name'] . ' · faltam ' . Finance::money((int) $goal['target_cents'] - (int) $goal['saved_cents']) . ' · prazo ' . (new DateTimeImmutable($goal['deadline']))->format('d/m/Y'),
                'target' => ['screen' => 'metas', 'entity' => 'finance_goal', 'id' => (int) $goal['id'], 'params' => []],
            ];
        }
        return $notes;
    }

    /** Linhas da área atual e as gerais, com categoria: não lidas primeiro e, entre elas, as mais graves. */
    public static function feed(array $scope, int $limit = 100): array
    {
        if (!self::supported()) {
            return self::computed($scope);
        }
        $rows = CmsQuery::all(
            "SELECT * FROM notifications WHERE user_id=? AND audience='customer' AND (financial_context_id=? OR financial_context_id IS NULL)
             AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY read_at IS NULL DESC, FIELD(priority,'critical','warning','info'), created_at DESC, id DESC LIMIT " . max(1, min($limit, 200)),
            [$scope['user_id'], $scope['context_id']]
        );
        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'category' => self::category($row['event_type'])] + $row, $rows);
    }

    private static function computed(array $scope): array
    {
        $rows = [];
        if ($scope['context_id'] > 0) {
            foreach (self::desired($scope['context_id'], self::preferences($scope['user_id'])) as $note) {
                $rows[] = [
                    'id' => 0, 'category' => 'financeiro', 'event_type' => $note['event_type'], 'title' => $note['title'], 'body' => $note['body'],
                    'priority' => $note['priority'], 'read_at' => null, 'created_at' => null, 'url' => self::targetUrl($note['target'], self::organizationOf($scope['context_id'])),
                ];
            }
        }
        foreach (HelpdeskNotifier::inbox($scope['user_id'], 'customer', 50) as $row) {
            $rows[] = ['id' => (int) $row['id'], 'category' => self::category($row['event_type'])] + $row;
        }
        return $rows;
    }

    public static function category(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'finance.') => 'financeiro',
            $eventType === 'broadcast' => 'comunicado',
            default => 'suporte',
        };
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? 'Suporte';
    }

    public static function unreadCount(array $feed): int
    {
        return count(array_filter($feed, static fn (array $row): bool => $row['read_at'] === null));
    }

    /** Marca como lida e devolve o destino. Só o dono abre; null quando não é dele. */
    public static function open(int $userId, int $id): ?string
    {
        $rows = CmsQuery::all("SELECT * FROM notifications WHERE id=? AND user_id=? AND audience='customer'", [$id, $userId]);
        if ($rows === []) {
            return null;
        }
        CmsQuery::run('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=?', [$id]);
        return self::destination($rows[0]);
    }

    public static function markAllRead(array $scope): void
    {
        if (!self::supported()) {
            HelpdeskNotifier::markAllRead($scope['user_id'], 'customer');
            return;
        }
        CmsQuery::run(
            "UPDATE notifications SET read_at=NOW() WHERE user_id=? AND audience='customer' AND read_at IS NULL AND (financial_context_id=? OR financial_context_id IS NULL)",
            [$scope['user_id'], $scope['context_id']]
        );
    }

    /** Endereço na web. Avisos da empresa levam ao endereço dela: a rota revalida o acesso e troca a área. */
    public static function destination(array $row): string
    {
        $target = self::target($row);
        if ($target !== null) {
            return self::targetUrl($target, self::organizationOf((int) $row['financial_context_id']));
        }
        $link = (string) $row['link'];
        if (str_starts_with($link, 'https://')) {
            return $link;
        }
        if ($link === '' || $link === self::NOTES_PAGE || !str_starts_with($link, '/') || str_starts_with($link, '//') || str_contains($link, '\\')) {
            return AppUrl::section('notificacoes');
        }
        // O CMS pode apontar só a tela, como /pagar: vale para a área em que o usuário está.
        $slug = (string) strtok(substr($link, 1), '/?#');
        return AppUrl::hasSlug($slug) && !str_contains(substr($link, 1), '/') ? AppUrl::base() . $link : $link;
    }

    /**
     * Contrato da notificação para os apps iOS e Android: o mesmo conteúdo que uma API e um
     * envio de push usarão. `target` diz a tela nativa; `url` é o caminho equivalente na web.
     */
    public static function payload(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'category' => self::category($row['event_type']), 'event' => $row['event_type'],
            'priority' => $row['priority'], 'title' => $row['title'], 'body' => $row['body'],
            'context_id' => isset($row['financial_context_id']) ? (int) $row['financial_context_id'] : null,
            'target' => self::target($row), 'url' => self::destination($row),
            'read' => $row['read_at'] !== null, 'created_at' => $row['created_at'],
        ];
    }

    private static function target(array $row): ?array
    {
        try {
            $target = json_decode((string) ($row['target_json'] ?? ''), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($target) && isset($target['screen']) ? $target : null;
    }

    private static function targetUrl(array $target, ?array $organization): string
    {
        $query = (array) ($target['params'] ?? []);
        $url = $organization === null ? AppUrl::personal((string) $target['screen'], $query) : AppUrl::section((string) $target['screen'], $organization, $query);
        return ($target['entity'] ?? '') === 'finance_entry' ? $url . '#lancamento-' . (int) $target['id'] : $url;
    }

    private static function organizationOf(int $contextId): ?array
    {
        $rows = CmsQuery::all('SELECT o.id,o.name FROM financial_contexts c INNER JOIN organizations o ON o.id=c.organization_id WHERE c.id=?', [$contextId]);
        return $rows[0] ?? null;
    }
}
