<?php
declare(strict_types=1);

/**
 * Gate da prévia CMS. Somente IPs clientes explícitos; nunca X-Forwarded-For.
 * A allowlist adicional aceita apenas endereços loopback ou de rede privada,
 * mesmo quando alguém configurar um IP público por engano.
 * Não substitui autenticação administrativa em produção.
 */
return static function (string $appEnv, string $flag, string $clientIp, string $extraAllowedCsv = ''): bool {
    if ($appEnv !== 'local' || $flag !== '1') {
        return false;
    }
    $isLocalAddress = static function (string $ip): bool {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            return $first === 127 || $first === 10
                || ($first === 172 && $second >= 16 && $second <= 31)
                || ($first === 192 && $second === 168);
        }
        // IPv6 ULA (fc00::/7) e loopback. IP público, link-local e IPv4 mapeado são negados.
        return strlen($packed) === 16
            && ($ip === '::1' || (ord($packed[0]) & 0xfe) === 0xfc);
    };
    $extraIps = array_filter(array_map('trim', explode(',', $extraAllowedCsv)), $isLocalAddress);
    $allowedIps = array_merge(['127.0.0.1', '::1'], $extraIps);
    return in_array($clientIp, $allowedIps, true);
};
