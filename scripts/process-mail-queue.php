<?php
declare(strict_types=1);

/**
 * Envia os e-mails da fila (convites de equipe e o que usa Email::queue).
 * Agendar a cada minuto: php scripts/process-mail-queue.php [limite de 1 a 100]
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

$limit = (int) ($argv[1] ?? 20);
echo json_encode((new \Moves\Services\MailQueue(\MovesCode\Model\Connection::get()))->work($limit)) . PHP_EOL;
