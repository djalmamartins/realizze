<?php
declare(strict_types=1);

/**
 * Processador CLI de recorrências financeiras.
 *
 * Execução manual: php scripts/finance-recurring-due.php
 * Agendamento (somente após validar a migration 011 e testar em homologação):
 * 15 2 * * * cd /caminho/realizze && /usr/bin/php scripts/finance-recurring-due.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\RecurringFinance;
use MovesCode\Model\Connection;

date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = Connection::get();
    $table = $pdo->query("SHOW TABLES LIKE 'finance_recurring_occurrences'");
    if ($table->fetchColumn() === false) {
        throw new RuntimeException('Migration 011 não aplicada; processamento interrompido.');
    }

    $contexts = $pdo->query("SELECT DISTINCT financial_context_id FROM finance_recurring_items WHERE status='active' AND auto_create=1 ORDER BY financial_context_id");
    $processed = 0;
    $errors = 0;
    foreach ($contexts->fetchAll(PDO::FETCH_COLUMN) as $contextId) {
        try {
            $count = (new RecurringFinance((int) $contextId))->generateDue(null, 100);
            $processed += $count;
            echo 'Contexto ' . (int) $contextId . ': ' . $count . " vencimentos processados\n";
        } catch (Throwable $exception) {
            $errors++;
            error_log('Recorrências do contexto ' . (int) $contextId . ': ' . $exception->getMessage());
        }
    }
    echo 'Total processado: ' . $processed . "; erros: " . $errors . "\n";
    exit($errors ? 1 : 0);
} catch (Throwable $exception) {
    error_log('Falha no processamento de recorrências: ' . $exception->getMessage());
    exit(1);
}
