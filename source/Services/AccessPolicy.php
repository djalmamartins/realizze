<?php
declare(strict_types=1);

namespace Moves\Services;

/**
 * Política de acesso inicial: nega por padrão ambientes administrativos.
 * Não interpreta permissões enviadas pelo navegador.
 */
final class AccessPolicy
{
    public static function canAccess(string $environment, ?array $user): bool
    {
        if ($environment === 'web') {
            return true;
        }
        if ($user === null || !isset($user['id'])) {
            return false;
        }
        // A área do cliente usa a identidade validada por SessionAuth::current().
        // CMS, ERP e Send exigirão papéis persistidos no banco em etapa própria.
        return $environment === 'app';
    }

    public static function currentCanAccess(string $environment): bool
    {
        if ($environment === 'web') {
            return true;
        }
        return self::canAccess($environment, SessionAuth::current());
    }

    /**
     * Protege uma rota privada. Retorna false após definir a resposta de bloqueio.
     * O chamador deve encerrar a execução da rota quando receber false.
     */
    public static function requireAccess(string $environment): bool
    {
        header('Cache-Control: no-store');
        if (self::currentCanAccess($environment)) {
            return true;
        }
        if ($environment === 'app' && SessionAuth::current() === null) {
            header('Location: /entrar', true, 302);
        } else {
            http_response_code(403);
            echo 'Acesso negado.';
        }
        return false;
    }
}
