<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\MailQueue;

$limit = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT) : 10;
if ($limit === false || $limit < 1 || $limit > 100) {
    fwrite(STDERR, "Uso: php bin/mail-queue-worker.php [1..100]\n");
    exit(2);
}
$stats = (new MailQueue($pdo))->work((int) $limit);
echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
