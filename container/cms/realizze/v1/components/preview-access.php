<?php
declare(strict_types=1);

/**
 * Gate da prévia CMS. Somente IPs clientes explícitos; nunca X-Forwarded-For.
 * Não substitui autenticação administrativa em produção.
 */
return static function (string $appEnv, string $flag, string $clientIp, string $extraAllowedCsv = ''): bool {
    if ($appEnv !== 'local' || $flag !== '1') {
        return false;
    }
    $extraIps = array_filter(
        array_map('trim', explode(',', $extraAllowedCsv)),
        static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false
    );
    $allowedIps = array_merge(['127.0.0.1', '::1'], $extraIps);
    return in_array($clientIp, $allowedIps, true);
};
