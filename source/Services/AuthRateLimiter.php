<?php
declare(strict_types=1);

namespace Moves\Services;

use RuntimeException;

/**
 * Limite compartilhado entre processos PHP no mesmo host/container.
 * Para múltiplas réplicas, substituir por armazenamento centralizado.
 */
final class AuthRateLimiter
{
    public function hit(string $scope, string $identity, int $limit, int $windowSeconds): bool
    {
        if ($identity === '' || $limit < 1 || $windowSeconds < 1) {
            return false;
        }

        $directory = sys_get_temp_dir() . '/realizze-auth-limits';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o limitador.');
        }

        $path = $directory . '/' . hash('sha256', $scope . "\0" . $identity) . '.json';
        $file = fopen($path, 'c+');
        if ($file === false) {
            throw new RuntimeException('Não foi possível abrir o limitador.');
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o limitador.');
            }
            $raw = stream_get_contents($file);
            $decoded = json_decode($raw === false ? '' : $raw, true);
            $now = time();
            $events = is_array($decoded) ? array_values(array_filter(
                $decoded,
                static fn ($at): bool => is_int($at) && $at > $now - $windowSeconds && $at <= $now
            )) : [];

            $allowed = count($events) < $limit;
            if ($allowed) {
                $events[] = $now;
            }
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($events, JSON_THROW_ON_ERROR));
            fflush($file);
            flock($file, LOCK_UN);
            return $allowed;
        } finally {
            fclose($file);
        }
    }
}
