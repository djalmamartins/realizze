<?php
declare(strict_types=1);

require dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Core\Session;

function assertCore004(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    $GLOBALS['results'][] = 'OK - ' . $label;
}

$session = new Session();
$session->set('core004_destroy', 'teste');
assertCore004($session->has('core004_destroy'), 'Dado presente antes do encerramento');
assertCore004(session_status() === PHP_SESSION_ACTIVE, 'Sessão ativa antes do encerramento');

$session->destroy();
assertCore004(session_status() === PHP_SESSION_NONE, 'Sessão encerrada');
assertCore004($_SESSION === [], 'Dados da sessão limpos');
assertCore004(!isset($_SESSION['core004_destroy']), 'Dado removido após encerramento');

foreach ($GLOBALS['results'] as $result) {
    echo $result, PHP_EOL;
}
echo "TESTE CORE-004 DESTROY: APROVADO\n";
