<?php
declare(strict_types=1);

/**
 * Concede acesso ao CMS a um usuário ativo já cadastrado. Sem senha aqui: usa a do site.
 *   php scripts/cms-grant-admin.php usuario@exemplo.com [superadmin|admin|editor|billing|support]
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\CmsAudit;
use Moves\Services\CmsQuery;

$email = $argv[1] ?? '';
$roleKey = $argv[2] ?? 'superadmin';
$user = CmsQuery::one("SELECT id FROM users WHERE email=? AND status='active'", [$email]);
$role = CmsQuery::one('SELECT id FROM cms_roles WHERE role_key=?', [$roleKey]);
if ($user === null || $role === null) {
    fwrite(STDERR, "Usuário ativo ou papel não encontrado.\n");
    exit(1);
}
CmsQuery::run(
    "INSERT INTO cms_admins (user_id,role_id,status) VALUES (?,?,'active') ON DUPLICATE KEY UPDATE role_id=VALUES(role_id), status='active'",
    [$user['id'], $role['id']]
);
CmsAudit::record(null, 'admin.granted_cli', 'admin', (string) $user['id'], ['role' => $roleKey]);
echo "Acesso concedido: {$roleKey}.\n";
