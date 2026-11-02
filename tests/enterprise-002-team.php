<?php
declare(strict_types=1);

/** ENT-004: equipe, papéis e convites em SQLite em memória; não toca no MySQL real. */
require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\OrganizationTeam as Team;
use MovesCode\Model\Connection;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Connection::configure($pdo);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, email TEXT);
CREATE TABLE organizations (id INTEGER PRIMARY KEY, name TEXT, status TEXT);
CREATE TABLE organization_roles (id INTEGER PRIMARY KEY, organization_id INTEGER, role_key TEXT, name TEXT, is_system INTEGER);
CREATE TABLE organization_role_permissions (id INTEGER PRIMARY KEY, role_id INTEGER, permission_key TEXT);
CREATE TABLE organization_members (id INTEGER PRIMARY KEY, organization_id INTEGER, user_id INTEGER, role_id INTEGER, status TEXT, joined_at TEXT, removed_at TEXT);
CREATE TABLE organization_invitations (id INTEGER PRIMARY KEY, organization_id INTEGER, role_id INTEGER, invited_by_user_id INTEGER, email TEXT, email_hash TEXT, token_hash TEXT, status TEXT, expires_at TEXT, accepted_by_user_id INTEGER, accepted_at TEXT, declined_at TEXT, revoked_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO users VALUES (1,'Dona','A','dona@example.invalid'),(2,'Bia','B','bia@example.invalid'),(3,'Caio','C','caio@example.invalid'),(4,'Davi','D','davi@example.invalid');
INSERT INTO organizations VALUES (10,'Empresa A','active'),(20,'Empresa B','active');
INSERT INTO organization_roles VALUES (100,10,'owner','Proprietário',1),(200,20,'owner','Proprietário',1);
INSERT INTO organization_members (organization_id,user_id,role_id,status,joined_at) VALUES (10,1,100,'active','2026-01-01'),(20,4,200,'active','2026-01-01');
SQL);

$failed = 0;
$check = static function (bool $ok, string $label) use (&$failed): void { echo ($ok ? 'OK' : 'FALHOU') . ' - ' . $label . PHP_EOL; if (!$ok) { $failed++; } };
$refused = static function (callable $call): bool { try { $call(); return false; } catch (InvalidArgumentException) { return true; } };

$check(Team::can(1, 10, 'team.manage'), 'Proprietário pode tudo, mesmo sem linhas de permissão');
$check(!Team::can(1, 20, 'finance.view'), 'Proprietário de uma empresa não acessa outra');
$check(!Team::can(2, 10, 'organization.view'), 'Quem não é membro não acessa');

$check($refused(static fn () => Team::invite(10, 2, 'x@example.invalid', 'viewer')), 'Quem não é membro não convida');
$check($refused(static fn () => Team::invite(10, 1, 'x@example.invalid', 'owner')), 'Papel de proprietário não é concedido por convite');
$check($refused(static fn () => Team::invite(10, 1, 'invalido', 'viewer')), 'E-mail inválido é recusado');
$check($refused(static fn () => Team::invite(10, 1, 'dona@example.invalid', 'viewer')), 'Quem já é membro não é convidado');

$token = Team::invite(10, 1, 'Bia@Example.invalid', 'finance');
$stored = $pdo->query('SELECT token_hash, email_hash FROM organization_invitations')->fetch(PDO::FETCH_ASSOC);
$check($stored['token_hash'] === hash('sha256', $token) && !str_contains(implode('', $stored), $token), 'Banco guarda só o hash do token');
$check(count(Team::pendingInvitations(10)) === 1 && Team::pendingInvitations(20) === [], 'Convite pendente só aparece na própria empresa');
$check($refused(static fn () => Team::accept($token, 3, 'caio@example.invalid')), 'Convite não vale para outra conta');

$second = Team::invite(10, 1, 'bia@example.invalid', 'viewer');
$check(Team::invitationByToken($token) === null && count(Team::pendingInvitations(10)) === 1, 'Novo convite invalida o anterior');
$third = Team::resend(10, 1, (int) $pdo->query("SELECT id FROM organization_invitations WHERE status='pending'")->fetchColumn());
$check(Team::invitationByToken($second) === null && Team::invitationByToken($third['token']) !== null, 'Reenvio troca o token');
$token = Team::invite(10, 1, 'bia@example.invalid', 'finance');

$expiredToken = Team::invite(10, 1, 'caio@example.invalid', 'viewer');
$pdo->exec("UPDATE organization_invitations SET expires_at='2000-01-01 00:00:00' WHERE email='caio@example.invalid'");
$check($refused(static fn () => Team::accept($expiredToken, 3, 'caio@example.invalid')), 'Convite expirado é recusado');

$check(Team::accept($token, 2, 'bia@example.invalid') === 10, 'Convidado com o e-mail certo entra');
$check($refused(static fn () => Team::accept($token, 2, 'bia@example.invalid')), 'Token não pode ser usado duas vezes');
$check(Team::can(2, 10, 'finance.manage') && !Team::can(2, 10, 'team.manage'), 'Financeiro lança mas não gerencia equipe');
$check(!Team::can(2, 20, 'finance.view'), 'Papel em uma empresa não vale em outra');
$check($refused(static fn () => Team::invite(10, 2, 'y@example.invalid', 'viewer')), 'Sem team.manage não convida');
$check($refused(static fn () => Team::changeRole(10, 2, 2, 'admin')), 'Ninguém eleva o próprio acesso');

Team::changeRole(10, 1, 2, 'admin');
$check(Team::can(2, 10, 'team.manage'), 'Proprietário promove a administrador');
$check($refused(static fn () => Team::changeRole(10, 2, 1, 'viewer')), 'Administrador não altera o proprietário');
$check($refused(static fn () => Team::remove(10, 2, 2)), 'Ninguém remove a si mesmo');

Team::setActive(10, 1, 2, false);
$check(!Team::can(2, 10, 'finance.view'), 'Suspenso perde o acesso');
Team::setActive(10, 1, 2, true);
$check(Team::can(2, 10, 'finance.view'), 'Reativado recupera o acesso');
Team::remove(10, 1, 2);
$check(!Team::can(2, 10, 'finance.view') && count(Team::members(10)) === 1, 'Removido perde o acesso e sai da lista');
$again = Team::invite(10, 1, 'bia@example.invalid', 'viewer');
Team::accept($again, 2, 'bia@example.invalid');
$check(Team::can(2, 10, 'finance.view') && !Team::can(2, 10, 'finance.manage'), 'Removido volta só com novo convite e o papel novo');
$declined = Team::invite(10, 1, 'caio@example.invalid', 'viewer');
Team::decline($declined, 'caio@example.invalid');
$check(Team::invitationByToken($declined) === null, 'Convite recusado deixa de valer');
$revoked = Team::invite(10, 1, 'caio@example.invalid', 'viewer');
$check($refused(static fn () => Team::revoke(20, 4, (int) $pdo->query("SELECT id FROM organization_invitations WHERE status='pending' AND email='caio@example.invalid'")->fetchColumn())), 'Outra empresa não cancela o convite');
Team::revoke(10, 1, (int) $pdo->query("SELECT id FROM organization_invitations WHERE status='pending' AND email='caio@example.invalid'")->fetchColumn());
$check(Team::invitationByToken($revoked) === null, 'Convite cancelado deixa de valer');

echo $failed === 0 ? 'ENT-004: EQUIPE E CONVITES APROVADOS.' . PHP_EOL : "ENT-004: {$failed} FALHA(S)." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
