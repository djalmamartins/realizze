<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/** Paginação e leituras parametrizadas compartilhadas pelos serviços do CMS. */
final class CmsQuery
{
    public static function page(string $from, string $select, string $order, array $args, int $page, int $perPage = 20): array
    {
        $count = Connection::get()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $stmt = Connection::get()->prepare(
            'SELECT ' . $select . ' ' . $from . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $stmt->execute($args);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function all(string $sql, array $args = []): array
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function value(string $sql, array $args = []): mixed
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchColumn();
    }

    public static function run(string $sql, array $args = []): int
    {
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($args);
        return $stmt->rowCount();
    }

    public static function transaction(callable $work): mixed
    {
        $pdo = Connection::get();
        if ($pdo->inTransaction()) {
            return $work();
        }
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
