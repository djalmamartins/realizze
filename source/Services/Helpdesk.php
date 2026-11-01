<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\CmsView;
use MovesCode\Model\Connection;
use RuntimeException;

/**
 * Central de Atendimento. Identidade e empresa vêm do servidor;
 * cliente só enxerga mensagens públicas de chamados autorizados.
 */
final class Helpdesk
{
    /** Máquina de estados da equipe. */
    private const STAFF_TRANSITIONS = [
        'open' => ['waiting_agent', 'in_progress', 'waiting_customer', 'resolved'],
        'waiting_agent' => ['in_progress', 'waiting_customer', 'resolved'],
        'in_progress' => ['waiting_agent', 'waiting_customer', 'resolved'],
        'waiting_customer' => ['waiting_agent', 'in_progress', 'resolved'],
        'resolved' => ['in_progress', 'closed'],
        'closed' => ['in_progress'],
    ];
    private const MAX_TICKETS_PER_HOUR = 10;
    private const MAX_REPLIES_PER_10_MIN = 20;

    // ---- Autorização ----
    /** Dono do chamado, dono da empresa ou pessoa com autorização explícita. Ser membro não basta. */
    public static function customerCanAccess(int $userId, array $ticket): bool
    {
        if ((int) $ticket['requester_user_id'] === $userId) {
            return true;
        }
        if ($ticket['organization_id'] === null) {
            return false;
        }
        return (int) CmsQuery::value(
            "SELECT COUNT(*) FROM organization_members m JOIN organizations o ON o.id=m.organization_id
             LEFT JOIN helpdesk_organization_access h ON h.organization_id=m.organization_id AND h.user_id=m.user_id
             WHERE m.organization_id=? AND m.user_id=? AND m.status='active' AND (o.owner_user_id=m.user_id OR h.user_id IS NOT NULL)",
            [$ticket['organization_id'], $userId]
        ) > 0;
    }

    public static function setOrganizationAccess(int $organizationId, int $userId, bool $granted, int $actorId): void
    {
        if ($granted) {
            if (CmsQuery::value("SELECT COUNT(*) FROM organization_members WHERE organization_id=? AND user_id=? AND status='active'", [$organizationId, $userId]) == 0) {
                throw new InvalidArgumentException('A pessoa precisa ser membro ativo da empresa.');
            }
            CmsQuery::run('INSERT IGNORE INTO helpdesk_organization_access (organization_id,user_id,granted_by) VALUES (?,?,?)', [$organizationId, $userId, $actorId]);
        } else {
            CmsQuery::run('DELETE FROM helpdesk_organization_access WHERE organization_id=? AND user_id=?', [$organizationId, $userId]);
        }
        CmsAudit::record($actorId, $granted ? 'helpdesk.org_access_granted' : 'helpdesk.org_access_revoked', 'company', (string) $organizationId, ['user' => $userId]);
    }

    // ---- Abertura pelo App ----
    /** @return array{id:int,protocol:string} */
    public static function open(int $userId, ?int $organizationId, array $input, array $files = []): array
    {
        $subject = CmsContent::text($input['subject'] ?? '', 190, 'Assunto');
        $body = self::body($input['body'] ?? '');
        if (mb_strlen($subject) < 3) {
            throw new InvalidArgumentException('Informe o assunto.');
        }
        $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || CmsQuery::value('SELECT COUNT(*) FROM helpdesk_categories WHERE id=? AND is_active=1', [$categoryId]) == 0) {
            throw new InvalidArgumentException('Selecione uma categoria.');
        }
        $priority = is_string($input['priority'] ?? null) && isset(CmsView::PRIORITIES[$input['priority']]) ? $input['priority'] : 'normal';
        $type = ($input['support_type'] ?? '') === 'billing' ? 'billing' : 'technical';
        $key = is_string($input['idempotency_key'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $input['idempotency_key']) === 1
            ? $input['idempotency_key'] : bin2hex(random_bytes(16));
        $existing = CmsQuery::one('SELECT id,protocol FROM helpdesk_tickets WHERE requester_user_id=? AND idempotency_key=?', [$userId, $key]);
        if ($existing !== null) {
            return ['id' => (int) $existing['id'], 'protocol' => (string) $existing['protocol']];
        }
        if (!(new AuthRateLimiter())->hit('helpdesk-open', (string) $userId, self::MAX_TICKETS_PER_HOUR, 3600)) {
            throw new InvalidArgumentException('Muitos chamados em pouco tempo. Aguarde alguns minutos.');
        }
        $now = date('Y-m-d H:i:s');
        $sla = CmsQuery::one('SELECT first_response_minutes, resolution_minutes FROM helpdesk_sla_targets WHERE priority=?', [$priority]) ?? [];
        $due = static fn (mixed $minutes): ?string => $minutes === null ? null : date('Y-m-d H:i:s', strtotime($now) + (int) $minutes * 60);
        $ticket = CmsQuery::transaction(static function () use ($userId, $organizationId, $categoryId, $subject, $type, $input, $priority, $key, $now, $body, $sla, $due): array {
            CmsQuery::run(
                "INSERT INTO helpdesk_tickets (requester_user_id,organization_id,category_id,subject,support_type,module_hint,suggested_priority,priority,status,idempotency_key,first_response_due_at,resolution_due_at,last_activity_at,created_at)
                 VALUES (?,?,?,?,?,?,?,?,'open',?,?,?,?,?)",
                [$userId, $organizationId, $categoryId, $subject, $type, CmsContent::text($input['module_hint'] ?? '', 60, 'Módulo'), $priority, $priority, $key, $due($sla['first_response_minutes'] ?? null), $due($sla['resolution_minutes'] ?? null), $now, $now]
            );
            $id = (int) Connection::get()->lastInsertId();
            $protocol = 'RZ-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            CmsQuery::run('UPDATE helpdesk_tickets SET protocol=? WHERE id=?', [$protocol, $id]);
            $messageId = self::insertMessage($id, $userId, 'customer', 'public', $body, $now);
            self::event($id, 'created', null, 'open', true, $userId, $now);
            return ['id' => $id, 'protocol' => $protocol, 'message_id' => $messageId];
        });
        try {
            self::storeAttachments($ticket['id'], $ticket['message_id'], $userId, $files);
        } finally {
            HelpdeskNotifier::ticketCreated($ticket['id']);
        }
        return ['id' => $ticket['id'], 'protocol' => $ticket['protocol']];
    }

    // ---- Consultas do cliente ----
    public static function customerTickets(int $userId, array $filters, int $page, int $perPage = 15): array
    {
        $where = "(t.requester_user_id=? OR EXISTS (
            SELECT 1 FROM organization_members m JOIN organizations o ON o.id=m.organization_id
            LEFT JOIN helpdesk_organization_access h ON h.organization_id=m.organization_id AND h.user_id=m.user_id
            WHERE m.organization_id=t.organization_id AND m.user_id=? AND m.status='active' AND (o.owner_user_id=m.user_id OR h.user_id IS NOT NULL)))";
        $args = [$userId, $userId];
        if (isset(CmsView::TICKET_STATUS[$filters['status'] ?? ''])) {
            $where .= ' AND t.status=?';
            $args[] = $filters['status'];
        }
        return CmsQuery::page(
            'FROM helpdesk_tickets t JOIN helpdesk_categories c ON c.id=t.category_id LEFT JOIN organizations o2 ON o2.id=t.organization_id WHERE ' . $where,
            't.id,t.protocol,t.subject,t.status,t.priority,t.last_activity_at,t.created_at,c.name AS category_name,o2.name AS organization_name',
            't.last_activity_at DESC, t.id DESC', $args, $page, $perPage
        );
    }

    /** Busca autorizada: para quem não pode ver, responde como inexistente (evita IDOR). */
    public static function customerTicket(int $userId, string $protocol): ?array
    {
        $ticket = self::byProtocol($protocol);
        return $ticket !== null && self::customerCanAccess($userId, $ticket) ? $ticket : null;
    }

    public static function byProtocol(string $protocol): ?array
    {
        return CmsQuery::one(self::TICKET_SQL . ' WHERE t.protocol=?', [$protocol]);
    }

    public static function ticket(int $id): ?array
    {
        return CmsQuery::one(self::TICKET_SQL . ' WHERE t.id=?', [$id]);
    }

    private const TICKET_SQL = "SELECT t.*, c.name AS category_name, o.name AS organization_name,
            CONCAT(u.first_name,' ',u.last_name) AS requester_name, u.email AS requester_email,
            CONCAT(a.first_name,' ',a.last_name) AS assignee_name,
            r.rating, r.comment AS rating_comment
        FROM helpdesk_tickets t JOIN helpdesk_categories c ON c.id=t.category_id JOIN users u ON u.id=t.requester_user_id
        LEFT JOIN organizations o ON o.id=t.organization_id LEFT JOIN users a ON a.id=t.assignee_user_id LEFT JOIN helpdesk_ratings r ON r.ticket_id=t.id";

    /**
     * Linha do tempo. Para o cliente, as notas internas e os eventos privados
     * são filtrados na consulta, nunca na tela.
     */
    public static function timeline(int $ticketId, bool $staff): array
    {
        $messages = CmsQuery::all(
            "SELECT m.id,m.author_type,m.visibility,m.body,m.created_at,CONCAT(u.first_name,' ',u.last_name) AS author_name
             FROM helpdesk_messages m LEFT JOIN users u ON u.id=m.author_user_id WHERE m.ticket_id=?" . ($staff ? '' : " AND m.visibility='public'"),
            [$ticketId]
        );
        $files = [];
        foreach (CmsQuery::all(
            "SELECT a.id,a.message_id,a.original_name,a.mime_type,a.size_bytes FROM helpdesk_attachments a JOIN helpdesk_messages m ON m.id=a.message_id
             WHERE a.ticket_id=? AND a.deleted_at IS NULL" . ($staff ? '' : " AND m.visibility='public'"),
            [$ticketId]
        ) as $file) {
            $files[(int) $file['message_id']][] = $file;
        }
        $items = [];
        foreach ($messages as $message) {
            $message['kind'] = 'message';
            $message['attachments'] = $files[(int) $message['id']] ?? [];
            if (!$staff && $message['author_type'] === 'staff') {
                $message['author_name'] = 'Equipe Realizze';
            }
            $items[] = $message;
        }
        foreach (CmsQuery::all(
            "SELECT e.id,e.event_type,e.from_value,e.to_value,e.is_public,e.created_at,CONCAT(u.first_name,' ',u.last_name) AS actor_name
             FROM helpdesk_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.ticket_id=?" . ($staff ? '' : ' AND e.is_public=1'),
            [$ticketId]
        ) as $event) {
            $event['kind'] = 'event';
            $items[] = $event;
        }
        usort($items, static fn (array $a, array $b): int => [$a['created_at'], $a['kind'] === 'event' ? 0 : 1, $a['id']] <=> [$b['created_at'], $b['kind'] === 'event' ? 0 : 1, $b['id']]);
        return $items;
    }

    // ---- Ações do cliente ----
    public static function customerReply(int $userId, array $ticket, string $body, array $files = []): void
    {
        if ($ticket['status'] === 'closed') {
            throw new InvalidArgumentException('Este chamado está fechado.');
        }
        if (!(new AuthRateLimiter())->hit('helpdesk-reply', (string) $userId, self::MAX_REPLIES_PER_10_MIN, 600)) {
            throw new InvalidArgumentException('Muitas mensagens em pouco tempo. Aguarde alguns minutos.');
        }
        $body = self::body($body);
        $id = (int) $ticket['id'];
        $now = date('Y-m-d H:i:s');
        $reopened = $ticket['status'] === 'resolved';
        $messageId = CmsQuery::transaction(static function () use ($id, $userId, $body, $ticket, $now, $reopened): int {
            $messageId = self::insertMessage($id, $userId, 'customer', 'public', $body, $now);
            if ($reopened) {
                self::applyStatus($ticket, 'waiting_agent', $userId, $now, true);
            } elseif ($ticket['status'] === 'waiting_customer') {
                self::applyStatus($ticket, 'waiting_agent', $userId, $now, false);
            } else {
                CmsQuery::run('UPDATE helpdesk_tickets SET last_activity_at=? WHERE id=?', [$now, $id]);
            }
            return $messageId;
        });
        try {
            self::storeAttachments($id, $messageId, $userId, $files);
        } finally {
            $reopened ? HelpdeskNotifier::ticketReopened($id, $messageId) : HelpdeskNotifier::customerReplied($id, $messageId);
        }
    }

    public static function customerResolve(int $userId, array $ticket): void
    {
        if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        CmsQuery::transaction(static fn () => self::applyStatus($ticket, 'resolved', $userId, $now, false));
        HelpdeskNotifier::ticketResolved((int) $ticket['id'], 'customer');
    }

    public static function customerConfirm(int $userId, array $ticket): void
    {
        if ($ticket['status'] !== 'resolved') {
            throw new InvalidArgumentException('Somente chamados resolvidos podem ser encerrados.');
        }
        $now = date('Y-m-d H:i:s');
        CmsQuery::transaction(static fn () => self::applyStatus($ticket, 'closed', $userId, $now, false));
    }

    public static function customerReopen(int $userId, array $ticket): void
    {
        if ($ticket['status'] !== 'resolved') {
            throw new InvalidArgumentException('Somente chamados resolvidos podem ser reabertos.');
        }
        $now = date('Y-m-d H:i:s');
        CmsQuery::transaction(static fn () => self::applyStatus($ticket, 'waiting_agent', $userId, $now, true));
        HelpdeskNotifier::ticketReopened((int) $ticket['id'], (int) $ticket['reopened_count'] + 1);
    }

    public static function rate(int $userId, array $ticket, array $input): void
    {
        if (!in_array($ticket['status'], ['resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Avalie depois que o chamado for resolvido.');
        }
        $rating = is_string($input['rating'] ?? null) && isset(CmsView::RATINGS[$input['rating']]) ? $input['rating'] : null;
        if ($rating === null) {
            throw new InvalidArgumentException('Selecione uma avaliação.');
        }
        try {
            CmsQuery::run('INSERT INTO helpdesk_ratings (ticket_id,rating,comment,rated_by,created_at) VALUES (?,?,?,?,NOW())', [
                $ticket['id'], $rating, CmsContent::text($input['comment'] ?? '', 500, 'Comentário'), $userId,
            ]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new InvalidArgumentException('Este chamado já foi avaliado.');
            }
            throw $error;
        }
    }

    // ---- CMS ----
    public static function staffTickets(array $filters, int $page, int $perPage = 20): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = "(t.protocol LIKE ? OR t.subject LIKE ? OR u.email LIKE ?)";
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        foreach (['status' => CmsView::TICKET_STATUS, 'priority' => CmsView::PRIORITIES] as $key => $allowed) {
            if (isset($allowed[$filters[$key] ?? ''])) {
                $where[] = "t.{$key}=?";
                $args[] = $filters[$key];
            }
        }
        if ((int) ($filters['category'] ?? 0) > 0) {
            $where[] = 't.category_id=?';
            $args[] = (int) $filters['category'];
        }
        if (($filters['assignee'] ?? '') === 'none') {
            $where[] = 't.assignee_user_id IS NULL';
        } elseif ((int) ($filters['assignee'] ?? 0) > 0) {
            $where[] = 't.assignee_user_id=?';
            $args[] = (int) $filters['assignee'];
        }
        if (($filters['tag'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM helpdesk_ticket_tags g WHERE g.ticket_id=t.id AND g.tag=?)';
            $args[] = mb_strtolower($filters['tag']);
        }
        if (($filters['queue'] ?? '') === '1') {
            $where[] = "t.status IN ('open','waiting_agent','in_progress')";
        }
        if (($filters['overdue'] ?? '') === '1') {
            $where[] = self::OVERDUE_SQL;
        }
        $order = ($filters['queue'] ?? '') === '1'
            ? "FIELD(t.priority,'urgent','high','normal','low'), t.last_activity_at ASC"
            : 't.last_activity_at DESC, t.id DESC';
        return CmsQuery::page(
            'FROM helpdesk_tickets t JOIN helpdesk_categories c ON c.id=t.category_id JOIN users u ON u.id=t.requester_user_id LEFT JOIN users a ON a.id=t.assignee_user_id WHERE ' . implode(' AND ', $where),
            "t.id,t.protocol,t.subject,t.status,t.priority,t.last_activity_at,t.created_at,t.first_response_at,t.first_response_due_at,t.resolution_due_at,c.name AS category_name,u.email AS requester_email,CONCAT(a.first_name,' ',a.last_name) AS assignee_name",
            $order, $args, $page, $perPage
        );
    }

    public static function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(CmsView::TICKET_STATUS), 0);
        foreach (CmsQuery::all('SELECT status, COUNT(*) AS total FROM helpdesk_tickets GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    /** Situação do prazo para a equipe. Sem meta configurada, não há prazo a mostrar. */
    public static function slaLabel(array $ticket): string
    {
        if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
            return 'Finalizado';
        }
        $due = $ticket['first_response_at'] === null && $ticket['first_response_due_at'] !== null
            ? $ticket['first_response_due_at'] : $ticket['resolution_due_at'];
        if ($due === null) {
            return 'Sem meta';
        }
        return strtotime($due) < time() ? 'Vencido' : 'Até ' . CmsView::date($due);
    }

    /** Lote passa pela mesma máquina de estados; chamados com transição inválida ficam de fora. */
    public static function bulkStatus(array $ids, string $status, array $admin): array
    {
        $changed = 0;
        $skipped = 0;
        foreach (array_unique(array_slice($ids, 0, 100)) as $id) {
            $ticket = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false ? null : self::ticket((int) $id);
            try {
                if ($ticket === null) {
                    throw new InvalidArgumentException('Chamado inválido.');
                }
                self::changeStatus($ticket, $status, $admin);
                $changed++;
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }
        return [$changed, $skipped];
    }

    /** Abertura pela equipe em nome do cliente. A primeira mensagem é pública. */
    public static function staffOpen(array $input, array $admin): array
    {
        $user = CmsQuery::one("SELECT id FROM users WHERE email=? AND status='active'", [CmsContent::text($input['requester_email'] ?? '', 255, 'Cliente')]);
        if ($user === null) {
            throw new InvalidArgumentException('Não há cliente ativo com este e-mail.');
        }
        $ticket = self::open((int) $user['id'], null, $input);
        CmsQuery::run('UPDATE helpdesk_tickets SET opened_by_user_id=COALESCE(opened_by_user_id,?) WHERE id=?', [$admin['id'], $ticket['id']]);
        CmsAudit::record((int) $admin['id'], 'ticket.created_by_staff', 'ticket', (string) $ticket['id']);
        return $ticket;
    }

    public static function saveTriage(array $ticket, array $input, array $admin): void
    {
        $id = (int) $ticket['id'];
        $team = CmsContent::text($input['team'] ?? '', 60, 'Equipe');
        $tags = array_values(array_unique(array_filter(array_map(
            static fn (string $tag): string => mb_strtolower(mb_substr(trim($tag), 0, 40)),
            explode(',', is_string($input['tags'] ?? null) ? $input['tags'] : '')
        ), 'strlen')));
        if (count($tags) > 10) {
            throw new InvalidArgumentException('Use no máximo 10 tags.');
        }
        CmsQuery::transaction(static function () use ($id, $team, $tags, $admin): void {
            CmsQuery::run('UPDATE helpdesk_tickets SET team=? WHERE id=?', [$team, $id]);
            CmsQuery::run('DELETE FROM helpdesk_ticket_tags WHERE ticket_id=?', [$id]);
            foreach ($tags as $tag) {
                CmsQuery::run('INSERT INTO helpdesk_ticket_tags (ticket_id,tag) VALUES (?,?)', [$id, $tag]);
            }
            CmsAudit::record((int) $admin['id'], 'ticket.triage_updated', 'ticket', (string) $id, ['team' => $team, 'tags' => $tags]);
        });
    }

    public static function tags(int $ticketId): array
    {
        return array_column(CmsQuery::all('SELECT tag FROM helpdesk_ticket_tags WHERE ticket_id=? ORDER BY tag', [$ticketId]), 'tag');
    }

    public static function addTime(array $ticket, mixed $minutes, string $note, array $admin): void
    {
        $value = filter_var($minutes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);
        if ($value === false) {
            throw new InvalidArgumentException('Informe de 1 a 1.440 minutos.');
        }
        CmsQuery::run('INSERT INTO helpdesk_time_entries (ticket_id,user_id,minutes,note,created_at) VALUES (?,?,?,?,NOW())', [$ticket['id'], $admin['id'], $value, CmsContent::text($note, 190, 'Observação')]);
        CmsAudit::record((int) $admin['id'], 'ticket.time_added', 'ticket', (string) $ticket['id'], ['minutes' => $value]);
    }

    public static function timeEntries(int $ticketId): array
    {
        return CmsQuery::all("SELECT e.minutes,e.note,e.created_at,CONCAT(u.first_name,' ',u.last_name) AS author FROM helpdesk_time_entries e LEFT JOIN users u ON u.id=e.user_id WHERE e.ticket_id=? ORDER BY e.id DESC", [$ticketId]);
    }

    public static function quickReplies(): array
    {
        return CmsQuery::all('SELECT id,title,body FROM helpdesk_quick_replies ORDER BY title');
    }

    public static function saveQuickReply(?int $id, array $input, int $actorId): void
    {
        $title = CmsContent::text($input['title'] ?? '', 100, 'Título');
        $body = self::body($input['body'] ?? '');
        if ($title === '' || CmsQuery::value('SELECT COUNT(*) FROM helpdesk_quick_replies WHERE title=? AND id<>?', [$title, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Título vazio ou repetido.');
        }
        if ($id === null) {
            CmsQuery::run('INSERT INTO helpdesk_quick_replies (title,body,created_by) VALUES (?,?,?)', [$title, $body, $actorId]);
        } else {
            CmsQuery::run('UPDATE helpdesk_quick_replies SET title=?,body=? WHERE id=?', [$title, $body, $id]);
        }
        CmsAudit::record($actorId, 'ticket.quick_reply_saved', 'quick_reply', $id === null ? null : (string) $id, ['title' => $title]);
    }

    public static function deleteQuickReply(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM helpdesk_quick_replies WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'ticket.quick_reply_deleted', 'quick_reply', (string) $id);
    }

    private const OVERDUE_SQL = "((t.first_response_at IS NULL AND t.first_response_due_at < NOW()) OR (t.resolved_at IS NULL AND t.resolution_due_at < NOW() AND t.status NOT IN ('resolved','closed')))";

    public static function staffReply(array $ticket, array $admin, string $body, bool $internal, array $files = []): void
    {
        $body = self::body($body);
        $id = (int) $ticket['id'];
        $actor = (int) $admin['id'];
        if ($ticket['status'] === 'closed' && !$internal) {
            throw new InvalidArgumentException('Reabra o chamado antes de responder ao cliente.');
        }
        $now = date('Y-m-d H:i:s');
        $messageId = CmsQuery::transaction(static function () use ($id, $actor, $body, $internal, $ticket, $now): int {
            $messageId = self::insertMessage($id, $actor, 'staff', $internal ? 'internal' : 'public', $body, $now);
            if (!$internal) {
                CmsQuery::run('UPDATE helpdesk_tickets SET first_response_at=COALESCE(first_response_at,?) WHERE id=?', [$now, $id]);
                if (in_array($ticket['status'], ['open', 'waiting_agent', 'in_progress'], true)) {
                    self::applyStatus($ticket, 'waiting_customer', $actor, $now, false);
                } else {
                    CmsQuery::run('UPDATE helpdesk_tickets SET last_activity_at=? WHERE id=?', [$now, $id]);
                }
            }
            CmsAudit::record($actor, $internal ? 'ticket.internal_note' : 'ticket.replied', 'ticket', (string) $id);
            return $messageId;
        });
        try {
            self::storeAttachments($id, $messageId, $actor, $files);
        } finally {
            if (!$internal) {
                HelpdeskNotifier::staffReplied($id, $messageId);
            }
        }
    }

    public static function changeStatus(array $ticket, string $status, array $admin): void
    {
        if (!isset(CmsView::TICKET_STATUS[$status]) || !in_array($status, self::STAFF_TRANSITIONS[$ticket['status']] ?? [], true)) {
            throw new InvalidArgumentException('Transição de situação não permitida.');
        }
        $actor = (int) $admin['id'];
        $reopen = in_array($ticket['status'], ['resolved', 'closed'], true) && $status === 'in_progress';
        $now = date('Y-m-d H:i:s');
        CmsQuery::transaction(static function () use ($ticket, $status, $actor, $now, $reopen): void {
            self::applyStatus($ticket, $status, $actor, $now, $reopen);
            CmsAudit::record($actor, $reopen ? 'ticket.reopened' : 'ticket.status_changed', 'ticket', (string) $ticket['id'], ['from' => $ticket['status'], 'to' => $status]);
        });
        if ($status === 'resolved') {
            HelpdeskNotifier::ticketResolved((int) $ticket['id'], 'staff');
        } elseif ($reopen) {
            HelpdeskNotifier::ticketReopened((int) $ticket['id'], (int) $ticket['reopened_count'] + 1);
        }
    }

    public static function allowedStatuses(string $status): array
    {
        return self::STAFF_TRANSITIONS[$status] ?? [];
    }

    public static function changePriority(array $ticket, string $priority, array $admin): void
    {
        if (!isset(CmsView::PRIORITIES[$priority])) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }
        if ($priority === $ticket['priority']) {
            return;
        }
        $id = (int) $ticket['id'];
        CmsQuery::run('UPDATE helpdesk_tickets SET priority=? WHERE id=?', [$priority, $id]);
        self::event($id, 'priority', $ticket['priority'], $priority, false, (int) $admin['id'], date('Y-m-d H:i:s'));
        CmsAudit::record((int) $admin['id'], 'ticket.priority_changed', 'ticket', (string) $id, ['from' => $ticket['priority'], 'to' => $priority]);
        if ($priority === 'urgent') {
            HelpdeskNotifier::ticketUrgent($id);
        }
    }

    /** Atribuição ou transferência: destino precisa poder atender chamados. */
    public static function assign(array $ticket, ?int $assigneeId, array $admin): void
    {
        $id = (int) $ticket['id'];
        $current = $ticket['assignee_user_id'] === null ? null : (int) $ticket['assignee_user_id'];
        if ($assigneeId === $current) {
            return;
        }
        if ($assigneeId !== null && !CmsAuth::can(CmsAuth::forUser($assigneeId), 'tickets.reply')) {
            throw new InvalidArgumentException('Esta pessoa não tem permissão para atender chamados.');
        }
        $now = date('Y-m-d H:i:s');
        $type = $current === null ? 'assigned' : ($assigneeId === null ? 'unassigned' : 'transferred');
        CmsQuery::transaction(static function () use ($id, $assigneeId, $current, $admin, $now, $type): void {
            CmsQuery::run('UPDATE helpdesk_tickets SET assignee_user_id=?, last_activity_at=? WHERE id=?', [$assigneeId, $now, $id]);
            self::event($id, $type, self::userName($current), self::userName($assigneeId), false, (int) $admin['id'], $now);
            CmsAudit::record((int) $admin['id'], 'ticket.' . $type, 'ticket', (string) $id, ['from' => $current, 'to' => $assigneeId]);
        });
        if ($assigneeId !== null && $assigneeId !== (int) $admin['id']) {
            HelpdeskNotifier::ticketAssigned($id, $assigneeId);
        }
    }

    private static function userName(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $name = CmsQuery::value("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [$id]);
        return $name === false ? null : mb_substr((string) $name, 0, 120);
    }

    public static function agents(): array
    {
        $agents = [];
        foreach (CmsAdmins::listing() as $admin) {
            if ($admin['status'] === 'active' && CmsAuth::can(CmsAuth::forUser((int) $admin['user_id']), 'tickets.reply')) {
                $agents[] = $admin;
            }
        }
        return $agents;
    }

    // ---- Categorias e metas ----
    public static function categories(bool $onlyActive = false): array
    {
        return CmsQuery::all(
            'SELECT c.*, (SELECT COUNT(*) FROM helpdesk_tickets t WHERE t.category_id=c.id) AS tickets_count FROM helpdesk_categories c'
            . ($onlyActive ? ' WHERE c.is_active=1' : '') . ' ORDER BY c.sort_order, c.name'
        );
    }

    public static function saveCategory(?int $id, array $input, int $actorId): void
    {
        $name = CmsContent::text($input['name'] ?? '', 100, 'Nome');
        if ($name === '' || CmsQuery::value('SELECT COUNT(*) FROM helpdesk_categories WHERE name=? AND id<>?', [$name, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Nome de categoria vazio ou repetido.');
        }
        $active = ($input['is_active'] ?? '0') === '1' ? 1 : 0;
        $order = max(0, (int) ($input['sort_order'] ?? 0));
        if ($id === null) {
            CmsQuery::run('INSERT INTO helpdesk_categories (name,is_active,sort_order) VALUES (?,?,?)', [$name, $active, $order]);
        } else {
            CmsQuery::run('UPDATE helpdesk_categories SET name=?,is_active=?,sort_order=? WHERE id=?', [$name, $active, $order, $id]);
        }
        CmsAudit::record($actorId, 'ticket.category_saved', 'ticket_category', $id === null ? null : (string) $id, ['name' => $name, 'active' => $active]);
    }

    public static function slaTargets(): array
    {
        return CmsQuery::all("SELECT * FROM helpdesk_sla_targets ORDER BY FIELD(priority,'urgent','high','normal','low')");
    }

    public static function saveSla(array $input, int $actorId): void
    {
        foreach (array_keys(CmsView::PRIORITIES) as $priority) {
            $values = [];
            foreach (['first', 'resolution'] as $kind) {
                $raw = $input[$kind][$priority] ?? '';
                $value = $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 525600]]);
                if ($value === false) {
                    throw new InvalidArgumentException('Use minutos inteiros nas metas de atendimento.');
                }
                $values[] = $value;
            }
            CmsQuery::run('UPDATE helpdesk_sla_targets SET first_response_minutes=?,resolution_minutes=?,updated_by=? WHERE priority=?', [$values[0], $values[1], $actorId, $priority]);
        }
        CmsAudit::record($actorId, 'ticket.sla_updated', 'settings');
    }

    public static function report(): array
    {
        $avg = static fn (string $column): mixed => CmsQuery::value("SELECT AVG(TIMESTAMPDIFF(MINUTE,created_at,{$column})) FROM helpdesk_tickets WHERE {$column} IS NOT NULL");
        return [
            'total' => (int) CmsQuery::value('SELECT COUNT(*) FROM helpdesk_tickets'),
            'first_response_avg' => $avg('first_response_at'),
            'resolution_avg' => $avg('resolved_at'),
            'overdue' => (int) CmsQuery::value('SELECT COUNT(*) FROM helpdesk_tickets t WHERE ' . self::OVERDUE_SQL),
            'reopened' => (int) CmsQuery::value('SELECT COUNT(*) FROM helpdesk_tickets WHERE reopened_count>0'),
            'by_status' => CmsQuery::all('SELECT status, COUNT(*) AS total FROM helpdesk_tickets GROUP BY status ORDER BY total DESC'),
            'by_category' => CmsQuery::all('SELECT c.name, COUNT(*) AS total FROM helpdesk_tickets t JOIN helpdesk_categories c ON c.id=t.category_id GROUP BY c.id,c.name ORDER BY total DESC LIMIT 20'),
            'time_total' => (int) CmsQuery::value('SELECT COALESCE(SUM(minutes),0) FROM helpdesk_time_entries'),
            'by_agent' => CmsQuery::all(
                "SELECT CONCAT(u.first_name,' ',u.last_name) AS name, COUNT(*) AS total, SUM(t.status IN ('resolved','closed')) AS resolved
                 FROM helpdesk_tickets t JOIN users u ON u.id=t.assignee_user_id GROUP BY u.id,u.first_name,u.last_name ORDER BY total DESC LIMIT 20"
            ),
            'ratings' => CmsQuery::all('SELECT rating, COUNT(*) AS total FROM helpdesk_ratings GROUP BY rating'),
        ];
    }

    // ---- Anexos ----
    /** Política: imagens, PDF e texto; extensão, MIME real, tamanho e quantidade validados. */
    private const ATTACHMENT_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
    private const ATTACHMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt'];
    private const ATTACHMENT_MAX_BYTES = 10485760;
    private const ATTACHMENT_MAX_FILES = 5;

    private static function attachmentRoot(): string
    {
        $root = BASE_PATH . '/storage/helpdesk';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Armazenamento de anexos indisponível.');
        }
        return $root;
    }

    /** Normaliza $_FILES[campo] com múltiplos arquivos. */
    public static function uploadedFiles(string $field): array
    {
        $raw = $_FILES[$field] ?? null;
        if (!is_array($raw) || !is_array($raw['name'] ?? null)) {
            return [];
        }
        $files = [];
        foreach ($raw['name'] as $index => $name) {
            if (($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = ['name' => $name, 'type' => '', 'tmp_name' => $raw['tmp_name'][$index], 'error' => $raw['error'][$index], 'size' => $raw['size'][$index]];
        }
        return $files;
    }

    private static function storeAttachments(int $ticketId, int $messageId, int $userId, array $files): void
    {
        if ($files === []) {
            return;
        }
        if (count($files) > self::ATTACHMENT_MAX_FILES) {
            throw new InvalidArgumentException('Envie no máximo ' . self::ATTACHMENT_MAX_FILES . ' arquivos por mensagem. A mensagem foi registrada sem anexos.');
        }
        foreach ($files as $file) {
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > self::ATTACHMENT_MAX_BYTES) {
                throw new InvalidArgumentException('Anexo inválido ou acima de 10 MB. A mensagem foi registrada sem este anexo.');
            }
            $original = mb_substr(basename((string) $file['name']), 0, 190);
            $name = pathinfo($original, PATHINFO_FILENAME);
            try {
                $sender = new \MovesCode\Storage\Send(self::attachmentRoot(), 'files', self::ATTACHMENT_MIMES, self::ATTACHMENT_EXTENSIONS);
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
                $path = $sender->upload($file, $name);
            } catch (RuntimeException $error) {
                throw new InvalidArgumentException('Formato não permitido. Use imagem, PDF ou texto. A mensagem foi registrada sem este anexo.');
            }
            CmsQuery::run(
                'INSERT INTO helpdesk_attachments (ticket_id,message_id,uploaded_by,stored_path,original_name,mime_type,size_bytes,created_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [$ticketId, $messageId, $userId, substr($path, strlen(self::attachmentRoot()) + 1), $original, $mime, (int) filesize($path)]
            );
        }
    }

    /** Anexo sem verificação de permissão: os chamadores decidem por cliente ou equipe. */
    public static function attachment(int $id): ?array
    {
        return CmsQuery::one(
            'SELECT a.*, m.visibility FROM helpdesk_attachments a JOIN helpdesk_messages m ON m.id=a.message_id WHERE a.id=? AND a.deleted_at IS NULL',
            [$id]
        );
    }

    public static function customerCanDownload(int $userId, array $attachment): bool
    {
        if ($attachment['visibility'] !== 'public') {
            return false;
        }
        $ticket = self::ticket((int) $attachment['ticket_id']);
        return $ticket !== null && self::customerCanAccess($userId, $ticket);
    }

    /** Envia o arquivo sem URL pública; sempre como download e sem execução inline. */
    public static function streamAttachment(array $attachment): void
    {
        $root = realpath(self::attachmentRoot());
        $path = realpath(self::attachmentRoot() . '/' . $attachment['stored_path']);
        if ($root === false || $path === false || !str_starts_with($path, $root . '/') || !is_file($path)) {
            http_response_code(404);
            return;
        }
        $name = str_replace(['"', '\\', "\r", "\n"], '', $attachment['original_name']);
        header('Content-Type: ' . (in_array($attachment['mime_type'], self::ATTACHMENT_MIMES, true) ? $attachment['mime_type'] : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . (int) filesize($path));
        readfile($path);
    }

    public static function deleteAttachment(int $id, int $actorId): void
    {
        $attachment = self::attachment($id);
        if ($attachment === null) {
            throw new InvalidArgumentException('Anexo não encontrado.');
        }
        CmsQuery::run('UPDATE helpdesk_attachments SET deleted_at=NOW(), deleted_by=? WHERE id=?', [$actorId, $id]);
        $path = realpath(self::attachmentRoot() . '/' . $attachment['stored_path']);
        if ($path !== false && str_starts_with($path, (string) realpath(self::attachmentRoot()) . '/')) {
            @unlink($path);
        }
        CmsAudit::record($actorId, 'ticket.attachment_deleted', 'ticket', (string) $attachment['ticket_id'], ['attachment' => $id]);
    }

    // ---- Internos ----
    private static function body(mixed $body): string
    {
        $body = trim(str_replace("\0", '', is_string($body) ? $body : ''));
        $length = mb_strlen($body);
        if ($length < 2 || $length > 10000) {
            throw new InvalidArgumentException('Escreva a mensagem (até 10.000 caracteres).');
        }
        return $body;
    }

    private static function insertMessage(int $ticketId, int $userId, string $authorType, string $visibility, string $body, string $now): int
    {
        CmsQuery::run('INSERT INTO helpdesk_messages (ticket_id,author_user_id,author_type,visibility,body,created_at) VALUES (?,?,?,?,?,?)', [$ticketId, $userId, $authorType, $visibility, $body, $now]);
        return (int) Connection::get()->lastInsertId();
    }

    private static function event(int $ticketId, string $type, ?string $from, ?string $to, bool $public, ?int $actorId, string $now): void
    {
        CmsQuery::run('INSERT INTO helpdesk_events (ticket_id,event_type,from_value,to_value,is_public,actor_user_id,created_at) VALUES (?,?,?,?,?,?,?)', [$ticketId, $type, $from, $to, $public ? 1 : 0, $actorId, $now]);
    }

    private static function applyStatus(array $ticket, string $to, ?int $actorId, string $now, bool $reopen): void
    {
        $id = (int) $ticket['id'];
        $set = 'status=?, last_activity_at=?';
        $args = [$to, $now];
        if ($to === 'resolved') {
            $set .= ', resolved_at=?';
            $args[] = $now;
        } elseif ($to === 'closed') {
            $set .= ', closed_at=?';
            $args[] = $now;
        }
        if ($reopen) {
            $set .= ', reopened_count=reopened_count+1, resolved_at=NULL, closed_at=NULL';
        }
        $args[] = $id;
        $args[] = $ticket['status'];
        // Condição pelo estado anterior impede corrida entre duas alterações simultâneas.
        if (CmsQuery::run("UPDATE helpdesk_tickets SET {$set} WHERE id=? AND status=?", $args) === 0) {
            throw new InvalidArgumentException('O chamado foi alterado por outra pessoa. Atualize a página.');
        }
        self::event($id, $reopen ? 'reopened' : 'status', $ticket['status'], $to, true, $actorId, $now);
    }
}
