<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Core\EnvironmentRouter;

$root = dirname(__DIR__);
$types = ['web', 'app', 'cms', 'erp', 'send'];

foreach ($types as $type) {
    $path = $root . '/container/' . $type . '/realizze/v1/';
    if (!is_file($path . 'routes.php') || !is_file($path . 'default.php')) {
        throw new RuntimeException('Arquivos obrigatórios ausentes: ' . $type);
    }
    $definition = require $path . 'routes.php';
    if (($definition['type'] ?? null) !== $type
        || ($definition['name'] ?? null) !== 'realizze'
        || ($definition['version'] ?? null) !== 'v1'
        || !is_callable($definition['routes'] ?? null)) {
        throw new RuntimeException('Contrato de rotas inválido: ' . $type);
    }
    $host = $type . '.test';
    $resolved = EnvironmentRouter::resolve($host, [
        $type => ['host' => $host, 'theme' => 'realizze', 'version' => 'v1'],
    ], $root);
    if ($resolved['path'] !== $path . 'routes.php') {
        throw new RuntimeException('Isolamento de ambiente falhou: ' . $type);
    }
    echo 'OK - Ambiente ' . $type . ' isolado e contrato válido' . PHP_EOL;
}

foreach (['app', 'cms', 'erp', 'send'] as $type) {
    $routes = file_get_contents($root . '/container/' . $type . '/realizze/v1/routes.php');
    if ($routes === false || !str_contains($routes, 'http_response_code(503)')) {
        throw new RuntimeException('Ambiente não publicado sem bloqueio: ' . $type);
    }
    echo 'OK - Ambiente ' . $type . ' indisponível até ativação' . PHP_EOL;
}

echo 'TESTE CORE-008: APROVADO' . PHP_EOL;
