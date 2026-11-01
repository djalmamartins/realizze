<?php
declare(strict_types=1);

/** Catálogo de bancos, logos, fallback e vínculo com contas. Banco de desenvolvimento, com limpeza ao final. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\FinancialInstitutions;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$seed = json_decode((string) file_get_contents(dirname(__DIR__) . '/database/seeds/financial_institutions.json'), true, 512, JSON_THROW_ON_ERROR);
$rows = $seed['institutions'];
$check(count($rows) > 400, 'Fonte com ' . count($rows) . ' instituições');
$check(count(array_unique(array_column($rows, 'ispb'))) === count($rows), 'ISPB sem duplicidade na fonte');
$check(count(array_unique(array_column($rows, 'slug'))) === count($rows), 'Slugs únicos');
$compes = array_filter(array_column($rows, 'compe'));
$check(count(array_unique($compes)) === count($compes), 'COMPE sem duplicidade quando existe');
$logos = array_filter(array_column($rows, 'logo'));
$check(count(array_unique($logos)) === count($logos), 'Cada logo associado a uma só instituição');

$total = (int) $pdo->query('SELECT COUNT(*) FROM financial_institutions')->fetchColumn();
$check($total >= count($rows), "Catálogo importado ({$total} registros)");
$dup = (int) $pdo->query('SELECT COUNT(*) FROM (SELECT ispb FROM financial_institutions WHERE ispb IS NOT NULL GROUP BY ispb HAVING COUNT(*) > 1) x')->fetchColumn();
$check($dup === 0, 'Sem duplicidade de ISPB no banco');
$bb = $pdo->query("SELECT * FROM financial_institutions WHERE ispb = '00000000'")->fetch(PDO::FETCH_ASSOC);
$check($bb !== false && $bb['compe_code'] === '001' && $bb['logo_file'] === 'banco-do-brasil-s-a', 'Banco do Brasil com COMPE 001 e logo');
$pdo->exec("INSERT INTO financial_institutions (official_name, trade_name, slug, institution_type) VALUES ('Teste sem código', 'Teste sem código', 'teste-sem-codigo-" . bin2hex(random_bytes(3)) . "', 'outra')");
$noCode = (int) $pdo->lastInsertId();
$check(FinancialInstitutions::find($noCode)['compe_code'] === null, 'Instituição sem COMPE é aceita');

// Logos: só aprovados, só com a configuração ligada, sem caminho do usuário.
$check(FinancialInstitutions::approvedLogoPath('banco-do-brasil-s-a') !== null, 'Logo aprovado encontrado');
$check(FinancialInstitutions::approvedLogoPath('duepay') === null, 'Logo em revisão não é entregue');
foreach (['../manifest', '_revisar/artta', 'manifest', 'BANCO', 'inexistente'] as $bad) {
    $check(FinancialInstitutions::approvedLogoPath($bad) === null, "Nome recusado: {$bad}");
}
putenv('REALIZZE_BANK_LOGOS_ENABLED=0');
$check(FinancialInstitutions::logoUrl('banco-do-brasil-s-a') === null, 'Configuração desligada usa o fallback');
putenv('REALIZZE_BANK_LOGOS_ENABLED=1');
$check(FinancialInstitutions::logoUrl('banco-do-brasil-s-a') === '/minha-conta/bancos/logo/banco-do-brasil-s-a', 'Configuração ligada gera a URL segura');
$check(FinancialInstitutions::logoUrl(null) === null, 'Sem logo, fallback');
$check(FinancialInstitutions::initials('Banco do Brasil') === 'BB' && FinancialInstitutions::initials('Caixa') === 'C', 'Iniciais do fallback');
foreach (glob(FinancialInstitutions::logoDirectory() . '/*.svg') ?: [] as $svg) {
    $content = (string) file_get_contents($svg);
    if (preg_match('/<script|\son[a-z]+\s*=|javascript:|<foreignObject/i', $content)) {
        throw new RuntimeException('FALHOU: SVG com conteúdo ativo: ' . basename($svg));
    }
}
echo "OK - Nenhum SVG aprovado com script, handler ou foreignObject\n";

// Vínculo com contas e cartões.
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'B', ?, 'x', 'active')")->execute(['bank-' . bin2hex(random_bytes(6)) . '@example.invalid']);
$user = (int) $pdo->lastInsertId();
try {
    $finance = new Finance(FinanceContext::forUser($user));
    $id = $finance->createAccount(['name' => 'Cartão BB', 'type' => 'credit_card', 'institution_id' => (string) $bb['id'], 'credit_limit' => '1.000,00']);
    $account = array_values(array_filter($finance->accountsWithBalance(), static fn (array $a): bool => (int) $a['id'] === $id))[0];
    $check((int) $account['institution_id'] === (int) $bb['id'] && $account['institution'] === 'Banco do Brasil' && $account['institution_logo'] === 'banco-do-brasil-s-a', 'Cartão ligado ao banco mostra nome e logo');
    $free = $finance->createAccount(['name' => 'Carteira', 'type' => 'wallet', 'institution' => 'Cooperativa local']);
    $check($free > 0, 'Conta sem banco do catálogo continua aceitando texto livre');
    try {
        $finance->createAccount(['name' => 'X', 'type' => 'checking', 'institution_id' => '999999999']);
        $check(false, 'Instituição inexistente aceita');
    } catch (InvalidArgumentException) {
        echo "OK - Instituição inexistente recusada\n";
    }
} finally {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);
    $pdo->prepare('DELETE FROM financial_institutions WHERE id = ?')->execute([$noCode]);
}
echo "Todos os testes do catálogo passaram.\n";
