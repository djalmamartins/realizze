<?php
declare(strict_types=1);

namespace Moves\Services;

use Throwable;

/** Log técnico agrupado por ocorrência. Só guarda mensagem, origem e caminho, sem dados da requisição. */
final class SystemLog
{
    private static bool $writing = false;

    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) !== 0) {
                self::record($severity & (E_WARNING | E_USER_WARNING | E_USER_ERROR) ? 'warning' : 'notice', $message, $file . ':' . $line);
            }
            return false;
        });
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) !== 0) {
                self::record('error', (string) $error['message'], $error['file'] . ':' . $error['line']);
            }
        });
    }

    public static function exception(?Throwable $error): void
    {
        if ($error !== null) {
            self::record('error', get_class($error) . ': ' . $error->getMessage(), $error->getFile() . ':' . $error->getLine());
        }
    }

    public static function record(string $level, string $message, string $source): void
    {
        if (self::$writing) {
            return;
        }
        self::$writing = true;
        try {
            $root = defined('BASE_PATH') ? BASE_PATH . '/' : '';
            $source = mb_substr(str_replace($root, '', $source), 0, 255);
            $message = self::clean($message);
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            CmsQuery::run(
                "INSERT INTO cms_system_logs (fingerprint,level,message,source,path,first_seen_at,last_seen_at) VALUES (?,?,?,?,?,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE occurrences=occurrences+1, last_seen_at=NOW(), status='open', path=VALUES(path)",
                [sha1($level . '|' . $message . '|' . $source), $level, $message, $source, mb_substr($path, 0, 190)]
            );
        } catch (Throwable) {
            // O log técnico nunca pode derrubar a requisição.
        } finally {
            self::$writing = false;
        }
    }

    /** Remove o que pode ser segredo ou dado pessoal antes de gravar. */
    public static function clean(string $message): string
    {
        $message = (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[e-mail]', $message);
        $message = (string) preg_replace('/\b\d{11,14}\b/', '[documento]', $message);
        $message = (string) preg_replace('/bot\d+:[\w-]+/', '[token]', $message);
        $message = (string) preg_replace('/(password|senha|token|secret)\s*[=:]\s*\S+/i', '$1=[oculto]', $message);
        return mb_substr($message, 0, 500);
    }

    public static function listing(array $filters, int $page): array
    {
        $where = '1=1';
        $args = [];
        if (in_array($filters['level'] ?? '', ['error', 'warning', 'notice'], true)) {
            $where .= ' AND level=?';
            $args[] = $filters['level'];
        }
        if (in_array($filters['status'] ?? '', ['open', 'resolved'], true)) {
            $where .= ' AND status=?';
            $args[] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (message LIKE ? OR source LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        return CmsQuery::page('FROM cms_system_logs WHERE ' . $where, '*', 'last_seen_at DESC, id DESC', $args, $page, 30);
    }

    public static function resolve(array $ids, int $actorId): int
    {
        $count = 0;
        foreach (array_slice(array_unique(array_map('intval', $ids)), 0, 100) as $id) {
            $count += CmsQuery::run("UPDATE cms_system_logs SET status='resolved' WHERE id=? AND status='open'", [$id]);
        }
        CmsAudit::record($actorId, 'system_log.resolved', 'system_log', null, ['count' => $count]);
        return $count;
    }
}
