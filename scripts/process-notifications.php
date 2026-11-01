<?php
declare(strict_types=1);

/**
 * Processa a fila de avisos externos. Só envia se o canal estiver ativo no CMS
 * e com credenciais no ambiente: php scripts/process-notifications.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

echo json_encode(\Moves\Services\ExternalNotifications::process()) . PHP_EOL;
