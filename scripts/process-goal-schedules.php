<?php
declare(strict_types=1);

/**
 * Executa os aportes programados das metas. Não gera despesa e não altera o patrimônio.
 *
 * Execução manual: php scripts/process-goal-schedules.php
 * Agendamento (somente após aplicar a migration 012 e testar em homologação):
 * 30 2 * * * cd /caminho/realizze && /usr/bin/php scripts/process-goal-schedules.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\GoalWallets;

date_default_timezone_set('America/Sao_Paulo');

if (!GoalWallets::ready()) {
    fwrite(STDERR, "Migration 012 não aplicada; processamento interrompido.\n");
    exit(1);
}
$totals = GoalWallets::processDue();
echo "Aportes feitos: {$totals['done']}. Sem saldo disponível: {$totals['skipped']}.\n";
