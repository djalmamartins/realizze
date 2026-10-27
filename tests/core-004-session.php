<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Core\Session;
use Moves\Support\Message;

function check(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $description);
    }
    $GLOBALS['core004_results'][] = 'OK - ' . $description;
}

$session = new Session(); // Antes de qualquer echo
$session->set('core004', ['key' => 'value']);
check($session->has('core004'), 'Chave presente');
check($session->get('core004')['key'] === 'value', 'Array preservado');
check($session->core004['key'] === 'value', 'Acesso mágico');
check(is_object($session->all()), 'all retorna objeto');
$session->unset('core004');
check(!$session->has('core004'), 'Remoção de chave');
$token = $session->csrf();
check(strlen($token) === 64, 'Token CSRF seguro');
check($session->verifyCsrf($token), 'CSRF válido');
check(!$session->verifyCsrf('invalido'), 'CSRF inválido');
(new Message())->success('Sessão OK')->flash();
check($session->flash()?->getText() === 'Sessão OK', 'Flash integrado ao Message');
check($session->flash() === null, 'Flash consumido uma única vez');
$before = session_id();
$session->regenerate();
check(session_id() !== $before, 'Identificador regenerado');
foreach ($GLOBALS['core004_results'] as $result) {
    echo $result, PHP_EOL;
}
echo "TESTE CORE-004: APROVADO\n";
