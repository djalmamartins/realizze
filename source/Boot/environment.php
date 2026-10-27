<?php

/**
 * Realizze 2.0
 * Carregamento das variáveis de ambiente.
 */

$envFile = dirname(__DIR__, 2) . '/.env';

if (!is_file($envFile)) {
    throw new RuntimeException(
        'Arquivo .env não encontrado: ' . $envFile
    );
}

$variables = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($variables === false) {
    throw new RuntimeException(
        'Não foi possível interpretar o arquivo .env.'
    );
}

foreach ($variables as $key => $value) {
    if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        continue;
    }

    if (getenv($key) !== false) {
        continue;
    }

    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
