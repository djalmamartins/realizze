<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\HtmlSanitizer;
use MovesCode\Model\Connection;

/** Comunicações para usuários: imediatas ou agendadas, sem duplicar destinatários. */
final class CmsBroadcasts
{
    public const AUDIENCES = ['all' => 'Todos os usuários', 'customers' => 'Somente clientes', 'staff' => 'Somente a equipe'];
    public const CHANNELS = ['internal' => 'Somente no sistema', 'email' => 'Sistema e e-mail'];
    public const PRIORITIES = ['info' => 'Informação', 'warning' => 'Atenção', 'critical' => 'Crítica'];
    public const STATUS = ['scheduled' => 'Agendada', 'sent' => 'Enviada', 'canceled' => 'Cancelada'];

    public static function listing(): array
    {
        return CmsQuery::all("SELECT b.*, CONCAT(u.first_name,' ',u.last_name) AS author,
            (SELECT COUNT(*) FROM notifications n WHERE n.broadcast_id=b.id AND n.read_at IS NOT NULL) AS read_count
            FROM cms_broadcasts b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.id DESC LIMIT 100");
    }

    public static function create(array $input, int $actorId): int
    {
        $title = CmsContent::text($input['title'] ?? '', 190, 'Título');
        $body = CmsContent::text($input['body'] ?? '', 2000, 'Mensagem');
        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Informe o título e a mensagem.');
        }
        $link = CmsContent::text($input['link'] ?? '', 255, 'Link');
        if ($link !== '' && (!HtmlSanitizer::safeUrl($link) || str_starts_with($link, 'mailto:'))) {
            throw new InvalidArgumentException('O link precisa começar com / ou https://.');
        }
        $scheduled = self::dateTime($input['scheduled_at'] ?? '') ?? date('Y-m-d H:i:s');
        $expires = self::dateTime($input['expires_at'] ?? '');
        if ($expires !== null && $expires <= $scheduled) {
            throw new InvalidArgumentException('A expiração precisa ser depois do envio.');
        }
        $pick = static fn (array $allowed, string $key, string $default): string => is_string($input[$key] ?? null) && isset($allowed[$input[$key]]) ? $input[$key] : $default;
        CmsQuery::run(
            'INSERT INTO cms_broadcasts (title,body,audience,channel,priority,link,scheduled_at,expires_at,created_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [$title, $body, $pick(self::AUDIENCES, 'audience', 'all'), $pick(self::CHANNELS, 'channel', 'internal'), $pick(self::PRIORITIES, 'priority', 'info'), $link, $scheduled, $expires, $actorId]
        );
        $id = (int) Connection::get()->lastInsertId();
        CmsAudit::record($actorId, 'broadcast.created', 'broadcast', (string) $id, ['title' => $title]);
        self::dispatchDue();
        return $id;
    }

    public static function cancel(int $id, int $actorId): void
    {
        if (CmsQuery::run("UPDATE cms_broadcasts SET status='canceled' WHERE id=? AND status='scheduled'", [$id]) === 0) {
            throw new InvalidArgumentException('Só comunicações agendadas podem ser canceladas.');
        }
        CmsAudit::record($actorId, 'broadcast.canceled', 'broadcast', (string) $id);
    }

    /** Entrega o que venceu. A troca de situação reivindica a linha: dois processos não enviam a mesma. */
    public static function dispatchDue(): int
    {
        $sent = 0;
        foreach (CmsQuery::all("SELECT * FROM cms_broadcasts WHERE status='scheduled' AND scheduled_at <= NOW() ORDER BY id LIMIT 20") as $b) {
            if (CmsQuery::run("UPDATE cms_broadcasts SET status='sent', sent_at=NOW() WHERE id=? AND status='scheduled'", [$b['id']]) === 0) {
                continue;
            }
            $count = 0;
            $key = 'broadcast:' . $b['id'];
            if ($b['audience'] !== 'staff') {
                $customerLink = $b['link'] !== '' ? $b['link'] : '/suporte/notificacoes';
                foreach (CmsQuery::all("SELECT id,first_name,email FROM users WHERE status='active'") as $user) {
                    $count += self::deliver($b, (int) $user['id'], 'customer', $customerLink, $key);
                    if ($b['channel'] === 'email') {
                        ExternalNotifications::enqueue('email', $key . ':' . $user['id'], null, ['to' => $user['email'], 'name' => $user['first_name'], 'subject' => $b['title'], 'text' => $b['body']]);
                    }
                }
            }
            if ($b['audience'] !== 'customers') {
                foreach (CmsQuery::all("SELECT user_id FROM cms_admins WHERE status='active'") as $admin) {
                    $count += self::deliver($b, (int) $admin['user_id'], 'staff', $b['link'] !== '' ? $b['link'] : '/notificacoes', $key);
                }
            }
            CmsQuery::run('UPDATE cms_broadcasts SET recipients=? WHERE id=?', [$count, $b['id']]);
            $sent++;
        }
        return $sent;
    }

    private static function deliver(array $b, int $userId, string $audience, string $link, string $key): int
    {
        return CmsQuery::run(
            'INSERT IGNORE INTO notifications (user_id,audience,event_type,broadcast_id,title,body,priority,link,dedupe_key,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
            [$userId, $audience, 'broadcast', $b['id'], $b['title'], $b['body'], $b['priority'], $link, $key, $b['expires_at']]
        );
    }

    private static function dateTime(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        if ($date === false) {
            throw new InvalidArgumentException('Data inválida.');
        }
        return $date->format('Y-m-d H:i:s');
    }
}
