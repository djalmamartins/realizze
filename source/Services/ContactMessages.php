<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;

/** Mensagens do formulário público. Texto puro, sempre escapado na exibição. */
final class ContactMessages
{
    public const STATUS = ['new' => 'Nova', 'answered' => 'Respondida'];

    public static function create(array $input): int
    {
        $name = CmsContent::text($input['name'] ?? '', 120, 'Nome');
        $email = CmsContent::text($input['email'] ?? '', 190, 'E-mail');
        $phone = CmsContent::text($input['phone'] ?? '', 30, 'Telefone');
        $subject = CmsContent::text($input['subject'] ?? '', 150, 'Assunto');
        $message = CmsContent::text($input['message'] ?? '', 4000, 'Mensagem');
        if ($name === '' || $subject === '' || mb_strlen($message) < 5) {
            throw new InvalidArgumentException('Preencha nome, assunto e mensagem.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        if ($phone !== '' && preg_match('/^[0-9 ()+.\-]{8,30}$/', $phone) !== 1) {
            throw new InvalidArgumentException('Informe um telefone válido.');
        }
        CmsQuery::run(
            'INSERT INTO contact_messages (name,phone,email,subject,message,whatsapp_ok,created_at) VALUES (?,?,?,?,?,?,NOW())',
            [$name, $phone, $email, $subject, $message, ($input['whatsapp_ok'] ?? '') === '1' ? 1 : 0]
        );
        $id = (int) Connection::get()->lastInsertId();
        try {
            foreach (CmsAdmins::listing() as $admin) {
                if ($admin['status'] === 'active' && CmsAuth::can(CmsAuth::forUser((int) $admin['user_id']), 'tickets.view')) {
                    CmsQuery::run(
                        "INSERT IGNORE INTO notifications (user_id,audience,event_type,title,link,dedupe_key,created_at) VALUES (?,'staff','contact.new',?,'/mensagens',?,NOW())",
                        [$admin['user_id'], mb_substr('Nova mensagem do site: ' . $subject, 0, 190), 'contact:' . $id]
                    );
                }
            }
        } catch (\Throwable $error) {
            error_log('Realizze site: aviso de mensagem não registrado.');
        }
        return $id;
    }

    public static function listing(array $filters, int $page): array
    {
        $where = '1=1';
        $args = [];
        if (isset(self::STATUS[$filters['status'] ?? ''])) {
            $where .= ' AND status=?';
            $args[] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ? OR subject LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        return CmsQuery::page('FROM contact_messages WHERE ' . $where, '*', 'id DESC', $args, $page);
    }

    public static function counts(): array
    {
        $counts = ['new' => 0, 'answered' => 0];
        foreach (CmsQuery::all('SELECT status, COUNT(*) AS total FROM contact_messages GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function setStatus(int $id, bool $answered, int $actorId): void
    {
        if (CmsQuery::value('SELECT COUNT(*) FROM contact_messages WHERE id=?', [$id]) == 0) {
            throw new InvalidArgumentException('Mensagem não encontrada.');
        }
        CmsQuery::run('UPDATE contact_messages SET status=?, handled_by=?, handled_at=NOW() WHERE id=?', [$answered ? 'answered' : 'new', $actorId, $id]);
        CmsAudit::record($actorId, $answered ? 'contact.answered' : 'contact.reopened', 'contact', (string) $id);
    }
}
