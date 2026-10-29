<?php
declare(strict_types=1);

/**
 * Inicial visual do perfil, compartilhada pelo cabeçalho e pela sidebar.
 * Extrai um grafema UTF-8 completo (inclusive nomes acentuados) sem depender
 * de mbstring; dados inválidos ou ausentes usam uma inicial neutra.
 * O HTML deve ser escapado pelo componente que consome este valor.
 */
$firstName = $user['first_name'] ?? null;
if (!is_string($firstName) || trim($firstName) === '') {
    return 'R';
}
if (preg_match('/^\\X/u', trim($firstName), $matches) !== 1) {
    return 'R';
}
$initial = $matches[0];
return function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
