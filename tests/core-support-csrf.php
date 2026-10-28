<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$markup = csrf_input();
$token = \Moves\Services\Csrf::token();
$checks = [
    'CSRF HTML com token' => str_contains($markup, 'name="csrf"') && str_contains($markup, $token),
    'CSRF válido' => csrf_verify($token),
    'CSRF inválido' => !csrf_verify('token-invalido'),
    'CSRF ausente' => !csrf_verify(''),
];
foreach ($checks as $name => $passed) {
    echo ($passed ? 'OK' : 'FALHOU') . ' - ' . $name . PHP_EOL;
    if (!$passed) { exit(1); }
}
echo 'TESTE CSRF HELPERS: APROVADO' . PHP_EOL;
