<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

ob_start();

use Moves\Services\PersonalAccess;
use Moves\Services\PersonalAccessContext;
use Moves\Services\FinanceContext;
use Moves\Services\Csrf;
use Moves\Services\SessionAuth;
use Moves\Services\TwoFactorAuth;
use Moves\Services\UserActivity;
use Moves\Services\UserProfile;
use Moves\Services\UserSessions;
use Moves\Services\RegistrationFlow;
use MovesCode\Model\Connection;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pdo = Connection::get(); $users = []; $failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void { echo ($ok ? 'OK' : 'FALHOU') . ' - ' . $message . PHP_EOL; if (!$ok) $failed++; };
$password = 'Perfil-Teste-123456';
try {
    $newEmail='profile-register-'.bin2hex(random_bytes(5)).'@example.invalid';
    $registered=(new RegistrationFlow())->registerWithPhone('Novo','Usuário',$newEmail,'(31) 97777-6666',$password,true,true);
    $users['registered']=['id'=>(int)$registered['user_id'],'email'=>$newEmail];
    $phone=$pdo->query('SELECT phone FROM users WHERE id='.(int)$registered['user_id'])->fetchColumn();
    $check($phone==='+5531977776666','Novo cadastro exige e normaliza o celular com DDD');
    try{(new RegistrationFlow())->registerWithPhone('Sem','Telefone','invalid-phone-'.bin2hex(random_bytes(3)).'@example.invalid','123',$password,true,true);$check(false,'Cadastro aceitou telefone inválido');}catch(InvalidArgumentException){$check(true,'Novo cadastro rejeita telefone inválido');}
    foreach (['owner','guest'] as $key) {
        $email = 'profile-' . $key . '-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $pdo->prepare("INSERT INTO users(first_name,last_name,email,phone,password,status) VALUES ('Teste',?,?,?,?,'active')")
            ->execute([ucfirst($key), $email, '+5531999999999', password_hash($password, PASSWORD_DEFAULT)]);
        $users[$key] = ['id' => (int) $pdo->lastInsertId(), 'email' => $email];
    }
    SessionAuth::signIn($users['owner']['id'], 1);
    $check(UserSessions::listing($users['owner']['id'])[0]['current'] === true, 'Login registra a sessão atual no servidor');
    UserProfile::update($users['owner']['id'], ['first_name'=>'Nome','last_name'=>'Atualizado','phone'=>'(31) 98888-7777','datebirth'=>'1990-05-12','timezone'=>'America/Sao_Paulo','biography'=>'Teste']);
    $profile = UserProfile::get($users['owner']['id']);
    $check($profile['first_name'] === 'Nome' && $profile['phone'] === '+5531988887777' && $profile['datebirth'] === '1990-05-12', 'Dados pessoais normalizados e persistidos');
    try { UserProfile::phone('11111111111'); $check(false, 'Celular repetido rejeitado'); } catch (InvalidArgumentException) { $check(true, 'Celular inválido rejeitado'); }

    $setup = TwoFactorAuth::begin($users['owner']['id'], $password);
    $method = new ReflectionMethod(TwoFactorAuth::class, 'hotp');
    $code = $method->invoke(null, $setup['secret'], intdiv(time(), 30));
    $recovery = TwoFactorAuth::confirm($users['owner']['id'], $code);
    $check(TwoFactorAuth::status($users['owner']['id'])['enabled'] && count($recovery) === 8, 'TOTP ativado com oito códigos de recuperação');
    $next = $method->invoke(null, $setup['secret'], intdiv(time(), 30));
    $check(TwoFactorAuth::verify($users['owner']['id'], $next), 'TOTP válido autentica');
    $check(!TwoFactorAuth::verify($users['owner']['id'], $next), 'Mesmo contador TOTP não pode ser reutilizado');
    $check(TwoFactorAuth::verify($users['owner']['id'], $recovery[0]), 'Código de recuperação autentica');
    $check(!TwoFactorAuth::verify($users['owner']['id'], $recovery[0]), 'Código de recuperação é de uso único');

    $token = PersonalAccess::invite($users['owner']['id'], $users['guest']['email'], ['dashboard.view','income.view','password.change']);
    $check(PersonalAccess::accept($users['guest']['id'], $users['guest']['email'], $token), 'Convidado aceita token válido uma única vez');
    $check(PersonalAccess::allowed($users['guest']['id'], $users['owner']['id'], 'income.view'), 'Permissão concedida é autorizada');
    $check(!PersonalAccess::allowed($users['guest']['id'], $users['owner']['id'], 'expenses.create') && !PersonalAccess::allowed($users['guest']['id'], $users['owner']['id'], 'password.change'), 'Permissões ausentes ou sensíveis são negadas');
    SessionAuth::signIn($users['guest']['id'], 1); $csrf = Csrf::token();
    $check(PersonalAccessContext::select($users['owner']['id'], $csrf) && FinanceContext::currentId() === FinanceContext::forUser($users['owner']['id']), 'Contexto delegado usa os dados PF do titular sem misturar os do convidado');
    $check(PersonalAccessContext::can('income.view') && !PersonalAccessContext::can('expenses.create'), 'Contexto aplica permissão no backend');
    $invite = PersonalAccess::listing($users['owner']['id'])[0];
    $check(PersonalAccess::revoke($users['owner']['id'], (int) $invite['id']) && !PersonalAccess::allowed($users['guest']['id'], $users['owner']['id'], 'income.view'), 'Revogação remove o acesso no backend');

    UserProfile::saveNotificationPreferences($users['owner']['id'], ['email'=>['security'=>'1'],'in_app'=>[]]);
    $prefs = UserProfile::notificationPreferences($users['owner']['id']);
    $check($prefs['security']['in_app'] && $prefs['security']['email'] && !$prefs['income']['in_app'], 'Preferências persistem e aviso crítico permanece no App');
    $activity = UserActivity::listing($users['owner']['id']);
    $check($activity['total'] >= 8 && $activity['rows'][0]['event_type'] !== '', 'Atividades sensíveis são auditadas');
} finally {
    $_SESSION = [];
    foreach (array_reverse($users) as $user) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user['id']]);
}
if ($failed > 0) throw new RuntimeException("PROFILE-001: {$failed} falha(s).");
echo "PROFILE-001: PERFIL E SEGURANÇA APROVADOS.\n";
ob_end_flush();
