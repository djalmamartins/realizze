<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Core\EnvironmentRouter;

function assertRoute(bool $valid, string $label): void
{
    echo ($valid ? 'OK' : 'FALHOU') . ' - ' . $label . PHP_EOL;
    if (!$valid) { exit(1); }
}

$host = parse_url((string) (getenv('APP_URL') ?: 'https://www.realizze.lab'), PHP_URL_HOST);
$config = EnvironmentRouter::configuration();
$selected = EnvironmentRouter::resolve($host, $config, BASE_PATH);
assertRoute($selected['type'] === 'web', 'Ambiente web selecionado');
assertRoute($selected['theme'] === 'realizze' && $selected['version'] === 'v1', 'Tema e versão corretos');
assertRoute(is_file($selected['path']), 'Arquivo de rotas encontrado');
assertRoute(EnvironmentRouter::hostname(strtoupper($host) . ':443') === $host, 'Hostname normalizado');

try {
    EnvironmentRouter::resolve('host-nao-configurado.test', $config, BASE_PATH);
    assertRoute(false, 'Host desconhecido rejeitado');
} catch (RuntimeException) {
    assertRoute(true, 'Host desconhecido rejeitado');
}

try {
    EnvironmentRouter::resolve($host, ['web' => ['host' => $host, 'theme' => '../fora', 'version' => 'v1']], BASE_PATH);
    assertRoute(false, 'Traversal de tema rejeitado');
} catch (InvalidArgumentException) {
    assertRoute(true, 'Traversal de tema rejeitado');
}

try {
    EnvironmentRouter::resolve($host, ['web' => ['host' => $host, 'theme' => 'realizze', 'version' => 'v999']], BASE_PATH);
    assertRoute(false, 'Versão inexistente rejeitada');
} catch (RuntimeException) {
    assertRoute(true, 'Versão inexistente rejeitada');
}

echo 'TESTE CORE-007: APROVADO' . PHP_EOL;
