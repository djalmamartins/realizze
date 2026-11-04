<?php
declare(strict_types=1);

/** Testes comportamentais da porta de acesso à prévia CMS; sem banco, rede ou sessão. */
$allow = require dirname(__DIR__) . '/container/cms/realizze/v1/components/preview-access.php';
if (!is_callable($allow)) {
    throw new RuntimeException('Gate CMS não é executável.');
}
$cases = [
    ['loopback IPv4', 'local', '1', '127.0.0.1', '', true],
    ['loopback IPv6', 'local', '1', '::1', '', true],
    ['flag ausente', 'local', '0', '127.0.0.1', '', false],
    ['ambiente de produção', 'production', '1', '127.0.0.1', '', false],
    ['ambiente de staging', 'staging', '1', '127.0.0.1', '', false],
    ['IP externo não permitido', 'local', '1', '203.0.113.10', '', false],
    ['cliente desconhecido', 'local', '1', '', '', false],
    ['gateway Docker explícito', 'local', '1', '172.17.0.1', '172.17.0.1', true],
    ['outro gateway negado', 'local', '1', '172.17.0.2', '172.17.0.1', false],
    ['comparação IP exata', 'local', '1', '172.17.0.10', '172.17.0.1', false],
    ['entrada inválida ignorada', 'local', '1', '172.17.0.1', 'inválido, 172.17.0.1', true],
    ['IPv4 privado 10/8 explícito', 'local', '1', '10.0.0.3', '10.0.0.3', true],
    ['IPv4 privado 192.168/16 explícito', 'local', '1', '192.168.1.3', '192.168.1.3', true],
    ['IPv6 ULA explícito', 'local', '1', 'fd12::2', 'fd12::2', true],
    ['IP público IPv4 recusado mesmo explícito', 'local', '1', '8.8.8.8', '8.8.8.8', false],
    ['IP público IPv6 recusado mesmo explícito', 'local', '1', '2001:4860:4860::8888', '2001:4860:4860::8888', false],
    ['IPv4 link-local recusado', 'local', '1', '169.254.1.1', '169.254.1.1', false],
    ['IPv6 link-local recusado', 'local', '1', 'fe80::1', 'fe80::1', false],
    ['IPv4 mapeado em IPv6 recusado', 'local', '1', '::ffff:172.17.0.1', '::ffff:172.17.0.1', false],
    ['CIDR recusado (IP individual obrigatório)', 'local', '1', '172.17.0.1', '172.17.0.0/16', false],
];
foreach ($cases as [$name, $environment, $flag, $ip, $csv, $expected]) {
    $actual = $allow($environment, $flag, $ip, $csv);
    if ($actual !== $expected) {
        throw new RuntimeException("CMS-002: falha no caso {$name}");
    }
    echo "OK - CMS-002: {$name}" . PHP_EOL;
}
echo 'CMS-002: acesso local por IP validado; testes HTTP de proxy ainda pendentes.' . PHP_EOL;
