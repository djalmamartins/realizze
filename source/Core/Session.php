<?php
declare(strict_types=1);

namespace Moves\Core;

use Moves\Support\Message;
use RuntimeException;

final class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('Sessões PHP estão desabilitadas.');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (headers_sent($file, $line)) {
                throw new RuntimeException("Não é possível iniciar sessão após saída em {$file}:{$line}.");
            }
            if (!session_start()) {
                throw new RuntimeException('Falha ao iniciar sessão.');
            }
        }
    }

    public function __get(string $name): mixed
    {
        return $_SESSION[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function all(): object
    {
        return (object) $_SESSION;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    public function unset(string $key): self
    {
        unset($_SESSION[$key]);
        return $this;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function regenerate(): self
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Falha ao regenerar identificador da sessão.');
        }
        return $this;
    }

    public function destroy(): self
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        if (!session_destroy()) {
            throw new RuntimeException('Falha ao destruir sessão.');
        }
        return $this;
    }

    public function flash(): ?Message
    {
        return Message::consumeFlash();
    }

    /**
     * Gera um token criptograficamente seguro e o armazena na sessão.
     * Retorna o token para uso em formulários.
     */
    public function csrf(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function verifyCsrf(string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }
}
