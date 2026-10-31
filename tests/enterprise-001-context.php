<?php
declare(strict_types=1);

/**
 * ENT-003: teste isolado em SQLite em memória; não toca no MySQL real.
 * Requer Composer e extensões PDO/SQLite.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\Csrf;
use Moves\Services\OrganizationAccess;
use Moves\Services\OrganizationContext;
use Moves\Services\SessionAuth;
use MovesCode\Model\Connection;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('O teste exige pdo_sqlite.');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Connection::configure($pdo);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, email TEXT, auth_version INTEGER, status TEXT);
CREATE TABLE organizations (id INTEGER PRIMARY KEY, name TEXT, status TEXT);
CREATE TABLE organization_members (id INTEGER PRIMARY KEY, organization_id INTEGER, user_id INTEGER, role_id INTEGER, status TEXT);
CREATE TABLE organization_roles (id INTEGER PRIMARY KEY, organization_id INTEGER, role_key TEXT, name TEXT);
CREATE TABLE organization_role_permissions (role_id INTEGER, permission_key TEXT);
INSERT INTO users VALUES (1, 'Usuário A', 'a@example.invalid', 1, 'active'), (2, 'Usuário B', 'b@example.invalid', 1, 'active');
INSERT INTO organizations VALUES (10, 'Empresa A', 'active'), (20, 'Empresa B', 'active'), (30, 'Revogada', 'active'), (40, 'Sem permissão', 'active');
INSERT INTO organization_roles VALUES (100, 10, 'admin', 'Administrador'), (200, 20, 'admin', 'Administrador'), (300, 30, 'admin', 'Administrador'), (400, 40, 'viewer', 'Consulta');
INSERT INTO organization_members VALUES (1, 10, 1, 100, 'active'), (2, 20, 2, 200, 'active'), (3, 30, 1, 300, 'inactive'), (4, 40, 1, 400, 'active');
INSERT INTO organization_role_permissions VALUES (100, 'organization.view'), (200, 'organization.view'), (300, 'organization.view'), (400, 'other.permission');
SQL);

if (!session_start()) {
    throw new RuntimeException('Sessão de teste indisponível.');
}
$results = [];
$check = static function (bool $condition, string $label) use (&$results): void {
    $results[] = [$condition, $label];
};
SessionAuth::signIn(1, 1);
$token = Csrf::token();
$check(OrganizationAccess::authorizedOrganization(10)['name'] === 'Empresa A', 'Vínculo autorizado');
$check(OrganizationAccess::authorizedOrganization(20) === null, 'Bloqueio entre empresas');
$check(OrganizationAccess::authorizedOrganization(30) === null, 'Vínculo inativo bloqueado');
$check(OrganizationAccess::authorizedOrganization(40) === null, 'Sem permissão organization.view');
$check(count(OrganizationAccess::accessibleForCurrentUser()) === 1, 'Lista limitada à empresa autorizada');

putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=0');
$check(!OrganizationContext::select(10, $token), 'Feature flag desativada bloqueia seleção');
putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=1');
$check(!OrganizationContext::select(10, 'token-invalido'), 'CSRF inválido bloqueia seleção');
$check(!OrganizationContext::select(20, $token), 'Não seleciona empresa de outro usuário');
$check(OrganizationContext::select(10, $token), 'Seleção autorizada por POST/CSRF');
$check((int) (OrganizationContext::current()['id'] ?? 0) === 10, 'Contexto de sessão revalidado');
// ENT-SEC-056: regressões de revogação e troca de papéis em memória, sem MySQL real.
$pdo->exec("UPDATE organization_members SET status = 'inactive' WHERE organization_id = 10 AND user_id = 1");
$check(OrganizationContext::current() === null, 'Vínculo revogado limpa contexto imediatamente');
$pdo->exec("UPDATE organization_members SET status = 'active' WHERE organization_id = 10 AND user_id = 1");
$check(OrganizationContext::select(10, $token), 'Vínculo restaurado exige nova seleção');
$pdo->exec("UPDATE organization_members SET role_id = 200 WHERE organization_id = 10 AND user_id = 1");
$check(OrganizationContext::current() === null, 'Papel de outra empresa não concede acesso');
$pdo->exec("UPDATE organization_members SET role_id = 100 WHERE organization_id = 10 AND user_id = 1");
$check(OrganizationContext::select(10, $token), 'Papel correto exige nova seleção');
$pdo->exec("UPDATE organizations SET status = 'inactive' WHERE id = 10");
$check(OrganizationContext::current() === null, 'Empresa inativa invalida contexto');
$pdo->exec("UPDATE organizations SET status = 'active' WHERE id = 10");
$check(OrganizationContext::select(10, $token), 'Empresa reativada exige nova seleção');
$_SESSION['realizze_organization_context'] = [
    'organization_id' => 20,
    'user_id' => 1,
    'auth_version' => 1,
];
$check(OrganizationContext::current() === null, 'Contexto forjado de empresa alheia é rejeitado');
$check(OrganizationContext::select(10, $token), 'Seleção legítima após tentativa forjada');
putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=0');
$check(OrganizationContext::current() === null, 'Desligar feature flag elimina contexto PJ');
putenv('REALIZZE_ENTERPRISE_CONTEXT_ENABLED=1');
$check(OrganizationContext::select(10, $token), 'Reativação exige seleção autorizada');

$pdo->exec("DELETE FROM organization_role_permissions WHERE role_id = 100 AND permission_key = 'organization.view'");
$check(OrganizationContext::current() === null, 'Revogação de permissão invalida contexto');
$pdo->exec("INSERT INTO organization_role_permissions VALUES (100, 'organization.view')");
$check(OrganizationContext::select(10, $token), 'Permissão restaurada permite nova seleção');
$check(!OrganizationContext::selectPersonal('csrf-invalido'), 'CSRF inválido não troca para PF');
$check((int) (OrganizationContext::current()['id'] ?? 0) === 10, 'Contexto preservado após CSRF inválido');
$check(OrganizationContext::selectPersonal($token), 'Retorno autorizado ao contexto PF');
$check(OrganizationContext::current() === null, 'PF sem contexto PJ');
$check(OrganizationContext::select(10, $token), 'Empresa A selecionada antes da troca de usuário');
SessionAuth::signIn(2, 1);
$check(OrganizationContext::current() === null, 'Novo login elimina contexto do usuário anterior');
$tokenB = Csrf::token();
$check(OrganizationContext::select(20, $tokenB), 'Usuário B acessa somente a própria empresa');
$pdo->exec('UPDATE users SET auth_version = 2 WHERE id = 2');
$check(OrganizationContext::current() === null, 'Revogação de sessão elimina contexto PJ');

$failed = 0;
foreach ($results as [$passed, $label]) {
    echo ($passed ? 'OK' : 'FALHOU') . ' - ' . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
if ($failed !== 0) {
    throw new RuntimeException("Falharam {$failed} testes de isolamento empresarial.");
}
echo 'ENT-003: isolamento PF/PJ aprovado em banco temporário.' . PHP_EOL;
