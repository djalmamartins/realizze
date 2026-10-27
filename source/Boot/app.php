<?php

/**
 * Realizze 2.0
 * Bootstrap principal da aplicação.
 */

// Diretório raiz do projeto
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// Carregar dependências do Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Carregar variáveis de ambiente
require_once __DIR__ . '/environment.php';

// Configurações iniciais
date_default_timezone_set(
    getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo'
);
