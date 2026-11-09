<?php
declare(strict_types=1);

/**
 * Situação das migrations no banco do .env (ou das variáveis DB_*). SÓ LEITURA: consulta information_schema.
 * Para cada arquivo em database/migrations, confere se as tabelas, colunas e restrições que ele cria existem.
 * Uso: php scripts/migrations-status.php [--pendentes]
 * Saída: aplicada, pendente ou parcial (parte existe: conferir antes de rodar o arquivo).
 */

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

$pdo = \MovesCode\Model\Connection::get();
$onlyPending = in_array('--pendentes', $argv, true);
$exists = static function (string $sql, array $args) use ($pdo): bool {
    $q = $pdo->prepare($sql);
    $q->execute($args);
    return (int) $q->fetchColumn() > 0;
};
$table = static fn (string $t): bool => $exists('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
$column = static fn (string $t, string $c): bool => $exists('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]);
$constraint = static fn (string $name): bool => $exists('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_name = ?', [$name]);
$index = static fn (string $t, string $name): bool => $exists('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$t, $name]);

echo 'Banco: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
$files = array_values(array_filter($files, static fn (string $f): bool => !str_ends_with($f, '.down.sql')));
sort($files, SORT_NATURAL);
$numbers = array_count_values(array_map(static fn (string $f): string => explode('_', basename($f))[0], $files));
$summary = ['aplicada' => 0, 'pendente' => 0, 'parcial' => 0, 'sem verificação' => 0];
foreach ($files as $file) {
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;
    $checks = [];
    preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $m);
    foreach ($m[1] as $t) $checks[] = ["tabela $t", $table($t)];
    preg_match_all('/ALTER\s+TABLE\s+`?(\w+)`?(.*?);/is', $sql, $alters, PREG_SET_ORDER);
    foreach ($alters as [, $t, $body]) {
        preg_match_all('/ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $body, $cols);
        foreach ($cols[1] as $c) $checks[] = ["coluna $t.$c", $column($t, $c)];
        preg_match_all('/ADD\s+CONSTRAINT\s+`?(\w+)`?\s+CHECK\s*\((.*?)\)\s*(?:,|$)/is', $body, $cons, PREG_SET_ORDER);
        foreach ($cons as [, $c, $clause]) {
            // Restrição recriada com o mesmo nome (ex.: 016): só vale se a regra nova estiver no banco.
            preg_match_all("/'([^']+)'/", $clause, $literals);
            $ok = $constraint($c);
            $q = $pdo->prepare('SELECT check_clause FROM information_schema.check_constraints WHERE constraint_schema = DATABASE() AND constraint_name = ?');
            $q->execute([$c]);
            $current = str_replace("\\", "", (string) $q->fetchColumn());
            // Só palavras simples: expressões regulares voltam do banco com outro escape.
            foreach ($literals[1] as $literal) if (preg_match('/^[\w-]+$/', $literal)) $ok = $ok && str_contains($current, "'$literal'");
            $checks[] = ["restrição $c", $ok];
        }
        preg_match_all('/ADD\s+CONSTRAINT\s+`?(\w+)`?\s+(?:FOREIGN|UNIQUE)/i', $body, $other);
        foreach ($other[1] as $c) $checks[] = ["restrição $c", $constraint($c)];
        preg_match_all('/ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?(\w+)`?/i', $body, $idx);
        foreach ($idx[1] as $i) $checks[] = ["índice $t.$i", $index($t, $i)];
    }
    if ($checks === []) {
        $state = 'sem verificação';
    } else {
        $done = count(array_filter($checks, static fn (array $c): bool => $c[1]));
        $state = $done === count($checks) ? 'aplicada' : ($done === 0 ? 'pendente' : 'parcial');
    }
    $summary[$state]++;
    if ($onlyPending && $state === 'aplicada') continue;
    $name = basename($file);
    $dup = $numbers[explode('_', $name)[0]] > 1 ? ' [número repetido]' : '';
    $missing = $state === 'parcial' ? ' faltam: ' . implode(', ', array_map(static fn (array $c): string => $c[0], array_filter($checks, static fn (array $c): bool => !$c[1]))) : '';
    printf("%-16s %s%s%s\n", $state, $name, $dup, $missing);
}
echo 'Resumo: ' . implode(', ', array_map(static fn (string $k, int $v): string => "$v $k", array_keys($summary), $summary)) . PHP_EOL;
echo "'sem verificação' = o arquivo só altera dados ou restrições sem nome; confira manualmente." . PHP_EOL;
