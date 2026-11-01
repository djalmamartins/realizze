<?php
declare(strict_types=1);

/**
 * Importa/atualiza o catálogo de instituições financeiras a partir de
 * database/seeds/financial_institutions.json. Idempotente: usa o ISPB como
 * chave e nunca apaga registros (instituições ausentes da fonte ficam como estão).
 *
 *   php scripts/import-financial-institutions.php
 */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use MovesCode\Model\Connection;

$file = dirname(__DIR__) . '/database/seeds/financial_institutions.json';
$data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$rows = $data['institutions'] ?? [];
if (!is_array($rows) || $rows === []) {
    fwrite(STDERR, "Arquivo sem instituições.\n");
    exit(1);
}
$pdo = Connection::get();
$find = $pdo->prepare('SELECT id FROM financial_institutions WHERE ispb = ?');
$insert = $pdo->prepare(
    'INSERT INTO financial_institutions (compe_code, ispb, official_name, trade_name, slug, institution_type, logo_file, status, source, source_updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$update = $pdo->prepare(
    'UPDATE financial_institutions SET compe_code = ?, official_name = ?, trade_name = ?, institution_type = ?, logo_file = ?, status = ?, source = ?, source_updated_at = ? WHERE id = ?'
);
$created = $updated = 0;
$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $ispb = (string) ($row['ispb'] ?? '');
        if (!preg_match('/^\d{8}$/', $ispb) || !preg_match('/^[a-z0-9-]{1,160}$/', (string) ($row['slug'] ?? ''))) {
            throw new RuntimeException('Registro inválido: ' . json_encode($row, JSON_UNESCAPED_UNICODE));
        }
        $compe = isset($row['compe']) && preg_match('/^\d{3}$/', (string) $row['compe']) ? (string) $row['compe'] : null;
        $logo = isset($row['logo']) && preg_match('/^[a-z0-9-]+$/', (string) $row['logo']) ? (string) $row['logo'] : null;
        $status = ($row['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $common = [$compe, (string) $row['official_name'], (string) $row['trade_name'], (string) ($row['type'] ?? 'outra'), $logo, $status, (string) ($data['source'] ?? ''), $row['source_updated_at'] ?: null];
        $find->execute([$ispb]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $insert->execute([$compe, $ispb, $common[1], $common[2], (string) $row['slug'], $common[3], $logo, $status, $common[6], $common[7]]);
            $created++;
        } else {
            $update->execute([...$common, (int) $id]);
            $updated++;
        }
    }
    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, 'Importação cancelada: ' . $exception->getMessage() . "\n");
    exit(1);
}
echo "Instituições criadas: {$created}; atualizadas: {$updated}.\n";
