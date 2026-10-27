<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Core\View;
use MovesCode\View\Engine;

$root = dirname(__DIR__) . '/container/web/realizze/v1';
$view = new View($root);
if (!$view->engine() instanceof Engine) {
    throw new RuntimeException('Engine incorreto.');
}
echo "OK - Engine existente reutilizado\n";
if ($view->path('components', $root . '/components') !== $view) {
    throw new RuntimeException('path() deve permitir encadeamento.');
}
echo "OK - Pasta de componentes registrada\n";
$html = $view->render('pages/home');
if (!is_string($html) || trim($html) === '') {
    throw new RuntimeException('Renderização vazia.');
}
echo "OK - Página inicial renderizada\n";
try {
    new View($root . '/inexistente');
    throw new RuntimeException('Caminho inexistente aceito.');
} catch (InvalidArgumentException $e) {
    echo "OK - Diretório inválido rejeitado\n";
}
echo "TESTE CORE-005: APROVADO\n";
