<?php
declare(strict_types=1);

namespace Moves\Services;

use Moves\Support\CmsView;

/**
 * Notificações do Helpdesk. Internas são gravadas na hora; externas entram numa fila
 * idempotente. Toda operação usa chave de deduplicação: repetir não duplica.
 * Falha de notificação nunca desfaz nem bloqueia o atendimento.
 */
final class HelpdeskNotifier
{
    private static function run(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $error) {
            error_log('Realizze helpdesk: notificação não registrada.');
        }
    }

    public static function ticketCreated(int $id): void
    {
        self::run(static function () use ($id): void {
            $t = Helpdesk::ticket($id);
            self::staff($t, 'ticket.created', 'Novo chamado ' . $t['protocol'], 'created');
            self::telegram($t, 'created', 'Novo chamado Realizze');
            self::email($t, 'created', 'Recebemos seu chamado ' . $t['protocol'], 'Recebemos seu chamado e a equipe vai analisá-lo. Acompanhe pelo aplicativo.');
            if ($t['priority'] === 'urgent') {
                self::telegram($t, 'urgent', 'Chamado urgente Realizze');
            }
        });
    }

    public static function customerReplied(int $id, int $messageId): void
    {
        self::run(static function () use ($id, $messageId): void {
            $t = Helpdesk::ticket($id);
            $key = 'reply:' . $messageId;
            if ($t['assignee_user_id'] !== null) {
                self::push((int) $t['assignee_user_id'], 'staff', 'ticket.customer_reply', $t, 'Nova resposta em ' . $t['protocol'], $key);
            } else {
                self::staff($t, 'ticket.customer_reply', 'Nova resposta em ' . $t['protocol'], $key);
            }
        });
    }

    public static function staffReplied(int $id, int $messageId): void
    {
        self::run(static function () use ($id, $messageId): void {
            $t = Helpdesk::ticket($id);
            self::push((int) $t['requester_user_id'], 'customer', 'ticket.staff_reply', $t, 'O suporte respondeu ao chamado ' . $t['protocol'], 'reply:' . $messageId);
            self::email($t, 'reply:' . $messageId, 'Nova resposta no chamado ' . $t['protocol'], 'A equipe respondeu ao seu chamado. Abra o aplicativo para ler.');
        });
    }

    public static function ticketAssigned(int $id, int $assigneeId): void
    {
        self::run(static function () use ($id, $assigneeId): void {
            $t = Helpdesk::ticket($id);
            self::push($assigneeId, 'staff', 'ticket.assigned', $t, 'Chamado ' . $t['protocol'] . ' atribuído a você', 'assigned:' . $assigneeId . ':' . $t['last_activity_at']);
        });
    }

    public static function ticketUrgent(int $id): void
    {
        self::run(static function () use ($id): void {
            $t = Helpdesk::ticket($id);
            self::staff($t, 'ticket.urgent', 'Chamado urgente ' . $t['protocol'], 'urgent', true);
            self::telegram($t, 'urgent', 'Chamado urgente Realizze');
        });
    }

    public static function ticketResolved(int $id, string $by): void
    {
        self::run(static function () use ($id, $by): void {
            $t = Helpdesk::ticket($id);
            $key = 'resolved:' . $t['resolved_at'];
            if ($by === 'staff') {
                self::push((int) $t['requester_user_id'], 'customer', 'ticket.resolved', $t, 'Chamado ' . $t['protocol'] . ' resolvido', $key);
                self::email($t, $key, 'Chamado ' . $t['protocol'] . ' resolvido', 'Seu chamado foi marcado como resolvido. Se precisar, reabra pelo aplicativo.');
            } elseif ($t['assignee_user_id'] !== null) {
                self::push((int) $t['assignee_user_id'], 'staff', 'ticket.resolved', $t, 'Cliente encerrou ' . $t['protocol'], $key);
            }
        });
    }

    public static function ticketReopened(int $id, int $sequence): void
    {
        self::run(static function () use ($id, $sequence): void {
            $t = Helpdesk::ticket($id);
            $key = 'reopened:' . $t['reopened_count'] . ':' . $sequence;
            self::staff($t, 'ticket.reopened', 'Chamado ' . $t['protocol'] . ' reaberto', $key);
            self::push((int) $t['requester_user_id'], 'customer', 'ticket.reopened', $t, 'Chamado ' . $t['protocol'] . ' reaberto', $key);
            self::email($t, $key, 'Chamado ' . $t['protocol'] . ' reaberto', 'Seu chamado foi reaberto e voltou para a fila de atendimento.');
        });
    }

    /** Equipe: quem tem tickets.view; com a opção de urgentes, só eles avisam a todos. */
    private static function staff(array $t, string $type, string $title, string $key, bool $urgent = false): void
    {
        if (CmsSettings::enabled('notify_internal_urgent_only') && !$urgent && $t['priority'] !== 'urgent') {
            return;
        }
        foreach (CmsAdmins::listing() as $admin) {
            if ($admin['status'] === 'active' && CmsAuth::can(CmsAuth::forUser((int) $admin['user_id']), 'tickets.view')) {
                self::push((int) $admin['user_id'], 'staff', $type, $t, $title, $key);
            }
        }
    }

    private static function push(int $userId, string $audience, string $type, array $t, string $title, string $key): void
    {
        $link = $audience === 'staff' ? '/chamados/' . $t['id'] : '/suporte/' . $t['protocol'];
        CmsQuery::run(
            'INSERT IGNORE INTO notifications (user_id,audience,event_type,ticket_id,title,link,dedupe_key,created_at) VALUES (?,?,?,?,?,?,?,NOW())',
            [$userId, $audience, $type, $t['id'], mb_substr($title, 0, 190), $link, 'ticket:' . $t['id'] . ':' . $key]
        );
    }

    /** Texto do Telegram: somente protocolo, assunto, prioridade e situação. */
    public static function telegramText(array $t, string $heading): string
    {
        return $heading . "\nProtocolo: " . $t['protocol'] . "\nAssunto: " . self::maskSensitive($t['subject'])
            . "\nPrioridade: " . (CmsView::PRIORITIES[$t['priority']] ?? $t['priority'])
            . "\nSituação: " . (CmsView::TICKET_STATUS[$t['status']] ?? $t['status']) . "\nAcesse o CMS para atender.";
    }

    /** Assunto é texto livre: mascara sequências que pareçam CPF, CNPJ ou cartão. */
    public static function maskSensitive(string $text): string
    {
        return (string) preg_replace('/\d[\d.\-\/ ]{9,}\d/', '[dados ocultos]', $text);
    }

    private static function telegram(array $t, string $key, string $heading): void
    {
        ExternalNotifications::enqueue('telegram', 'ticket:' . $t['id'] . ':' . $key, (int) $t['id'], ['text' => self::telegramText($t, $heading)]);
    }

    private static function email(array $t, string $key, string $subject, string $text): void
    {
        ExternalNotifications::enqueue('email', 'ticket:' . $t['id'] . ':' . $key, (int) $t['id'], [
            'to' => $t['requester_email'], 'name' => $t['requester_name'], 'subject' => $subject, 'text' => $text . "\n\nProtocolo: " . $t['protocol'],
        ]);
    }

    // ---- Caixa de entrada ----
    // Avisos financeiros pertencem a uma área e só aparecem por Notifications, que respeita o acesso a ela.
    public static function inbox(int $userId, string $audience, int $limit = 30): array
    {
        return CmsQuery::all("SELECT * FROM notifications WHERE user_id=? AND audience=? AND event_type NOT LIKE 'finance.%' AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT " . max(1, min($limit, 100)), [$userId, $audience]);
    }

    public static function unread(int $userId, string $audience): int
    {
        return (int) CmsQuery::value("SELECT COUNT(*) FROM notifications WHERE user_id=? AND audience=? AND event_type NOT LIKE 'finance.%' AND read_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())", [$userId, $audience]);
    }

    public static function markAllRead(int $userId, string $audience): void
    {
        CmsQuery::run("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND audience=? AND event_type NOT LIKE 'finance.%' AND read_at IS NULL", [$userId, $audience]);
    }

    public static function markTicketRead(int $userId, string $audience, int $ticketId): void
    {
        CmsQuery::run('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND audience=? AND ticket_id=? AND read_at IS NULL', [$userId, $audience, $ticketId]);
    }
}
