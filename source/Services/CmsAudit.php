<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;

/** Trilha administrativa. Nunca registrar senhas, tokens ou dados financeiros de clientes. */
final class CmsAudit
{
    private const SECRET_KEYS = '/pass|token|secret|document|cpf|cnpj/i';

    public static function record(?int $actorId, string $event, string $type = '', ?string $resourceId = null, array $changes = []): void
    {
        foreach (array_keys($changes) as $key) {
            if (preg_match(self::SECRET_KEYS, (string) $key) === 1) {
                unset($changes[$key]);
            }
        }
        Connection::get()->prepare(
            'INSERT INTO cms_audit_logs (actor_user_id, event_type, resource_type, resource_id, changes_json, ip_address) VALUES (?,?,?,?,?,?)'
        )->execute([
            $actorId, $event, $type, $resourceId,
            $changes === [] ? null : json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
        ]);
    }

    public static function listing(array $filters, int $page, int $perPage = 30): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(l.event_type LIKE ? OR l.resource_id = ? OR u.email LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', $filters['q'], '%' . $filters['q'] . '%');
        }
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'l.resource_type = ?';
            $args[] = $filters['type'];
        }
        if (($filters['resource_id'] ?? '') !== '') {
            $where[] = 'l.resource_id = ?';
            $args[] = $filters['resource_id'];
        }
        return CmsQuery::page(
            'FROM cms_audit_logs l LEFT JOIN users u ON u.id = l.actor_user_id WHERE ' . implode(' AND ', $where),
            'l.*, u.email AS actor_email', 'l.id DESC', $args, $page, $perPage
        );
    }
}
