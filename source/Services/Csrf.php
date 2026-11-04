<?php
declare(strict_types=1);

namespace Moves\Services;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Sessão não iniciada.');
        }

        $stored = $_SESSION['csrf_token'] ?? null;
        // Rejeitar tokens de sessão corrompidos ou fracos, sem expor TypeError.
        if (!is_string($stored) || preg_match('/\\A[a-f0-9]{64}\\z/D', $stored) !== 1) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(mixed $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !is_string($token)) {
            return false;
        }

        $stored = $_SESSION['csrf_token'] ?? null;
        return is_string($stored)
            && preg_match('/\\A[a-f0-9]{64}\\z/D', $stored) === 1
            && hash_equals($stored, $token);
    }
}
