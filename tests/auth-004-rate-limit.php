<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Services\AuthRateLimiter;

$limiter = new AuthRateLimiter();
$id = bin2hex(random_bytes(16));
$assert = static function (bool $condition, string $description): void {
    if (!$condition) {
        throw new RuntimeException('FALHOU - ' . $description);
    }
    echo 'OK - ' . $description . PHP_EOL;
};

$assert($limiter->hit('test', $id, 2, 60), 'Primeira tentativa permitida');
$assert($limiter->hit('test', $id, 2, 60), 'Segunda tentativa permitida');
$assert(!$limiter->hit('test', $id, 2, 60), 'Terceira tentativa bloqueada');
$assert($limiter->hit('test-other', $id, 2, 60), 'Escopos isolados');
$assert($limiter->hit('test', $id . '-other', 2, 60), 'Identidades isoladas');
$assert(!$limiter->hit('test', '', 2, 60), 'Identidade vazia bloqueada');
$assert(!$limiter->hit('test', $id, 0, 60), 'Limite inválido bloqueado');
echo 'TESTE AUTH-004: APROVADO' . PHP_EOL;
