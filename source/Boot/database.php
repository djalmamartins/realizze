<?php

declare(strict_types=1);

use MovesCode\Model\Connection;
use Moves\Core\Config;

$host = Config::get('DB_HOST', '127.0.0.1');
$port = Config::get('DB_PORT', '3306');
$database = Config::get('DB_DATABASE', 'realizze');
$username = Config::get('DB_USERNAME', '');
$password = Config::get('DB_PASSWORD', '');

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $database
);

$pdo = new PDO(
    $dsn,
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

Connection::configure($pdo);
