<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Services\AccessPolicy;

function checkPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU - ' . $message);
    }
    echo 'OK - ' . $message . PHP_EOL;
}

$user = ['id' => 1, 'first_name' => 'Teste'];
checkPolicy(AccessPolicy::canAccess('web', null), 'Web pública');
checkPolicy(!AccessPolicy::canAccess('app', null), 'App bloqueia anônimo');
checkPolicy(AccessPolicy::canAccess('app', $user), 'App permite identidade validada');
foreach (['cms', 'erp', 'send', 'unknown', ''] as $environment) {
    checkPolicy(!AccessPolicy::canAccess($environment, $user), 'Bloqueio por padrão: ' . ($environment ?: 'vazio'));
}
checkPolicy(!AccessPolicy::currentCanAccess('app'), 'Sem sessão autenticada não há acesso ao App');
echo 'TESTE CORE-009: APROVADO' . PHP_EOL;
