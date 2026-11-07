<?php
declare(strict_types=1);

require __DIR__ . '/client.php';

$email = getenv('REALIZZE_TEST_EMAIL') ?: 'pj-dono@example.invalid';
$password = getenv('REALIZZE_TEST_PASSWORD') ?: 'PjMissao#2026';
$slug = getenv('REALIZZE_TEST_COMPANY') ?: 'cueca-verde';
$client = new H();

if (!$client->login($email, $password)) {
    fwrite(STDERR, "FAIL login de teste\n");
    exit(1);
}

$personal = [
    '', 'receber', 'pagar', 'movimentacoes', 'cartoes', 'faturas', 'relatorios',
    'planejamento', 'metas', 'categorias', 'recorrencias', 'sair-das-dividas',
    'configuracoes', 'notificacoes', 'integracoes', 'perfil',
];
$enterprise = [
    '', 'receber', 'pagar', 'movimentacoes', 'recorrencias', 'fluxo-de-caixa',
    'cartoes', 'faturas', 'conciliacao', 'clientes', 'fornecedores', 'categorias',
    'centros-de-custo', 'relatorios', 'dre', 'planejamento', 'metas', 'obrigacoes',
    'ordens-de-servico', 'equipe', 'permissoes', 'empresa', 'seguranca', 'auditoria',
    'notificacoes', 'integracoes', 'perfil',
];

$routes = [];
foreach ($personal as $section) $routes[] = '/p' . ($section === '' ? '' : '/' . $section);
foreach ($enterprise as $section) $routes[] = '/e/' . rawurlencode($slug) . ($section === '' ? '' : '/' . $section);

$failed = [];
foreach ($routes as $route) {
    $response = $client->req('GET', $route);
    $body = strtolower((string) $response['body']);
    $healthy = $response['code'] === 200
        && !str_contains($body, 'fatal error')
        && !str_contains($body, 'ops/500')
        && !str_contains($body, 'esta página não está funcionando');
    printf("%s %3d %s\n", $healthy ? 'OK  ' : 'FAIL', $response['code'], $route);
    if (!$healthy) $failed[] = $route;
}

printf("TOTAL %d | OK %d | FALHAS %d\n", count($routes), count($routes) - count($failed), count($failed));
exit($failed === [] ? 0 : 1);
