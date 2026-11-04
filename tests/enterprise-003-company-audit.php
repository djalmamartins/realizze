<?php
declare(strict_types=1);

/** Dados da empresa, papéis da equipe no MySQL (migration 016) e auditoria. Exige banco de teste. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\OrganizationAudit;
use Moves\Services\Organizations;
use Moves\Services\OrganizationTeam as Team;
use MovesCode\Model\Connection;

$pdo = Connection::get();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_contains($database, 'test')) {
    throw new RuntimeException("Recusado: {$database} não é um banco de teste.");
}
$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK' : 'FALHOU') . " - {$message}\n";
    if (!$ok) $failed++;
};
$refused = static function (callable $call): bool {
    try { $call(); return false; } catch (InvalidArgumentException) { return true; }
};
$user = static function (string $name) use ($pdo): int {
    $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES (?, 'Teste', ?, 'x', 'active')")
        ->execute([$name, strtolower($name) . '-' . bin2hex(random_bytes(5)) . '@example.invalid']);
    return (int) $pdo->lastInsertId();
};
$owner = $user('Dona');
$admin = $user('Ana');
$viewer = $user('Vito');
$alfa = Organizations::create($owner, 'Teste Alfa', '11222333000181');
$beta = Organizations::create($owner, 'Teste Beta', '');
try {
    // 016: o catálogo de papéis cabe na restrição do MySQL.
    Team::ensureRoles($alfa);
    $roles = $pdo->prepare('SELECT role_key FROM organization_roles WHERE organization_id = ? ORDER BY role_key');
    $roles->execute([$alfa]);
    $check(array_diff(array_merge(['owner'], array_keys(Team::ROLES)), $roles->fetchAll(PDO::FETCH_COLUMN)) === [], 'Todos os papéis do catálogo são criados no MySQL');
    foreach ([[$admin, 'admin'], [$viewer, 'viewer']] as [$member, $role]) {
        $email = $pdo->query('SELECT email FROM users WHERE id = ' . $member)->fetchColumn();
        Team::accept(Team::invite($alfa, $owner, (string) $email, $role), $member, (string) $email);
    }
    $check(Team::can($admin, $alfa, 'team.manage') && !Team::can($viewer, $alfa, 'team.manage'), 'Convites com papéis novos funcionam');

    $check($refused(static fn () => Organizations::update($alfa, $viewer, ['name' => 'X'])), 'Visualizador não altera os dados da empresa');
    $check($refused(static fn () => Organizations::update($beta, $admin, ['name' => 'X'])), 'Administrador de uma empresa não altera outra');
    $check($refused(static fn () => Organizations::update($alfa, $owner, ['name' => ''])), 'Razão social é obrigatória');
    $check($refused(static fn () => Organizations::update($alfa, $owner, ['name' => 'A', 'cnpj' => '11222333000182'])), 'CNPJ com dígito errado é recusado');
    $check($refused(static fn () => Organizations::update($alfa, $owner, ['name' => 'A', 'email' => 'sem-arroba'])), 'E-mail inválido é recusado');
    $check($refused(static fn () => Organizations::update($alfa, $owner, ['name' => 'A', 'phone' => '1234'])), 'Telefone sem DDD é recusado');

    $slug = $pdo->query("SELECT slug FROM organizations WHERE id = {$alfa}")->fetchColumn();
    $changes = Organizations::update($alfa, $admin, ['name' => 'Teste Alfa Ltda', 'trade_name' => 'Alfa', 'cnpj' => '11.222.333/0001-81', 'email' => 'Fin@Alfa.example', 'phone' => '(31) 98888-7777']);
    $row = $pdo->query("SELECT name, trade_name, document_value, email, phone, slug FROM organizations WHERE id = {$alfa}")->fetch(PDO::FETCH_ASSOC);
    $check($row['name'] === 'Teste Alfa Ltda' && $row['trade_name'] === 'Alfa' && $row['email'] === 'fin@alfa.example' && $row['phone'] === '31988887777', 'Administrador salva os dados, normalizados');
    $check(!isset($changes['document_value']) && isset($changes['name'], $changes['email']), 'Só os campos alterados entram no registro');
    $check($row['slug'] === $slug, 'O endereço da empresa não muda com o nome');
    $check(Organizations::update($alfa, $owner, ['name' => 'Teste Alfa Ltda', 'trade_name' => 'Alfa', 'cnpj' => '11222333000181', 'email' => 'fin@alfa.example', 'phone' => '31988887777']) === [], 'Mesmo conteúdo não gera alteração');
    Organizations::update($alfa, $owner, ['name' => 'Teste Alfa Ltda', 'trade_name' => 'Alfa', 'email' => 'fin@alfa.example', 'phone' => '31988887777']);
    $doc = $pdo->query("SELECT document_type, document_value FROM organizations WHERE id = {$alfa}")->fetch(PDO::FETCH_ASSOC);
    $check($doc['document_type'] === null && $doc['document_value'] === null, 'Apagar o CNPJ limpa tipo e valor juntos');

    $log = OrganizationAudit::listing($alfa);
    $check($log['total'] === 2 && $log['rows'][0]['event_type'] === 'company.updated' && (int) $log['rows'][1]['actor_user_id'] === $admin, 'Cada alteração fica registrada com a pessoa');
    $check($log['rows'][1]['changes']['email'] === [null, 'fin@alfa.example'], 'O registro guarda valor anterior e novo');
    $check(OrganizationAudit::listing($beta)['total'] === 0, 'O registro de uma empresa não aparece em outra');
    OrganizationAudit::record($alfa, $owner, 'evento.inventado', 'x');
    $check(OrganizationAudit::listing($alfa)['total'] === 2, 'Evento fora do catálogo não é gravado');
    OrganizationAudit::record($alfa, $owner, 'team.removed', 'member', $viewer);
    $check(OrganizationAudit::listing($alfa, ['tipo' => 'team.removed'])['total'] === 1 && OrganizationAudit::listing($alfa, ['pessoa' => (string) $admin])['total'] === 1, 'Filtros por ação e por pessoa');
    $check(OrganizationAudit::listing($alfa, ['de' => '2000-01-01', 'ate' => '2000-01-31'])['total'] === 0, 'Filtro por período');
    $check(count(OrganizationAudit::actors($alfa)) === 2, 'Lista de pessoas do filtro só tem quem agiu na empresa');
} finally {
    foreach ([$alfa, $beta] as $org) {
        $pdo->prepare('DELETE FROM organization_audit_logs WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organization_invitations WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM financial_contexts WHERE organization_id = ?')->execute([$org]);
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$org]);
    }
    $pdo->prepare('DELETE FROM users WHERE id IN (?, ?, ?)')->execute([$owner, $admin, $viewer]);
}
echo $failed === 0 ? "ENT-003: EMPRESA E AUDITORIA APROVADAS.\n" : "ENT-003: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
