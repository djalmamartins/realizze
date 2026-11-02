<?php
declare(strict_types=1);

// Cadastro público da conta pessoal. Rode em banco temporário, como www-data.
require dirname(__DIR__) . '/source/Boot/app.php';
require dirname(__DIR__) . '/source/Boot/database.php';

use Moves\App\Register;
use Moves\Services\Authentication;
use Moves\Services\Csrf;
use Moves\Services\EmailVerification;
use Moves\Services\RegistrationFlow;
use Moves\Services\VerificationMailer;
use MovesCode\Model\Connection;

ob_start();

class SignupFakeMailer extends VerificationMailer
{
    public array $links = [];
    public array $notices = [];
    public bool $fail = false;

    public function send(string $email, string $firstName, string $token): void
    {
        if ($this->fail) {
            throw new RuntimeException('Falha SMTP simulada.');
        }
        $this->links[] = ['email' => $email, 'token' => $token];
    }

    public function sendAlreadyRegistered(string $email, string $firstName): void
    {
        $this->notices[] = $email;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pdo = Connection::get();
$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK' : 'FALHOU') . ' - ' . $message . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$mailer = new SignupFakeMailer();
$register = new Register(new RegistrationFlow(), $mailer);
$password = 'Cadastro-Teste-123456';
$prefix = 'signup-' . bin2hex(random_bytes(5));
$email = $prefix . '@example.invalid';
$submit = static function (array $fields = []) use ($register, $password, $email): ?string {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.' . random_int(1, 254);
    $_POST = array_merge([
        'csrf_token' => Csrf::token(),
        'name' => 'Ana Maria de Souza',
        'email' => $email,
        'phone' => '(31) 98888-7777',
        'password' => $password,
        'password_confirmation' => $password,
        'terms' => '1',
        'privacy' => '1',
    ], $fields);
    try {
        $register->submit();
        return null;
    } catch (InvalidArgumentException $error) {
        return $error->getMessage();
    }
};
$user = static function (string $address) use ($pdo): array|false {
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $statement->execute([$address]);
    return $statement->fetch(PDO::FETCH_ASSOC);
};

try {
    $check($submit(['csrf_token' => 'invalido']) !== null && !$user($email), 'CSRF inválido não cria conta');
    $check($submit(['name' => 'Ana']) !== null && !$user($email), 'Nome sem sobrenome não cria conta');
    $check($submit(['password' => 'curta', 'password_confirmation' => 'curta']) !== null && !$user($email), 'Senha curta não cria conta');
    $check($submit(['password_confirmation' => 'Outra-Senha-123456']) !== null && !$user($email), 'Senhas diferentes não criam conta');
    $check($submit(['phone' => '123']) !== null && !$user($email), 'Celular inválido não cria conta');
    unset($_POST['terms']);
    $check($submit(['terms' => null]) === null ? false : !$user($email), 'Sem aceite: base não recebe conta');
    $check($submit(['website' => 'http://spam.example']) === null && !$user($email) && $mailer->links === [], 'Robô recebe sucesso, sem conta nem e-mail');

    $check($submit(['email' => strtoupper($email)]) === null, 'Dados válidos aceitos');
    $created = $user($email);
    $check($created && $created['status'] === 'registered', 'Conta criada aguardando confirmação');
    $check($created && $created['email'] === $email, 'E-mail guardado em minúsculas');
    $check($created && $created['first_name'] === 'Ana' && $created['last_name'] === 'Maria de Souza', 'Nome separado em nome e sobrenome');
    $check($created && $created['phone'] === '+5531988887777', 'Celular normalizado');
    $check($created && $created['password'] !== $password && password_verify($password, $created['password']), 'Senha guardada só como hash');
    $id = (int) $created['id'];
    $legal = (int) $pdo->query("SELECT COUNT(DISTINCT document_type) FROM user_legal_acceptances WHERE user_id = {$id}")->fetchColumn();
    $check($legal === 2, 'Aceites de termos e privacidade registrados');
    $orgs = (int) $pdo->query("SELECT COUNT(*) FROM organization_members WHERE user_id = {$id}")->fetchColumn();
    $check($orgs === 0, 'Nenhuma empresa criada: a conta é só pessoal');
    $check(count($mailer->links) === 1 && $mailer->links[0]['email'] === $email, 'Link de confirmação enviado');
    $check((new Authentication())->attempt($email, $password) === null, 'Login bloqueado antes de confirmar');

    $firstHash = $created['password'];
    $check($submit(['password' => 'Tentativa-Nova-123456', 'password_confirmation' => 'Tentativa-Nova-123456']) === null, 'Repetir e-mail pendente tem a mesma resposta');
    $check($user($email)['password'] === $firstHash, 'Repetir o cadastro não troca a senha');
    $check(count($mailer->links) === 2, 'Repetir e-mail pendente envia novo link');
    $verification = new EmailVerification();
    $check($verification->confirm($mailer->links[0]['token']) === false, 'Link antigo deixa de valer');

    $_POST = ['email' => $email];
    $register->resend();
    $check(count($mailer->links) === 3, 'Reenvio manda novo link');
    $_POST = ['email' => 'ninguem-' . $email];
    $register->resend();
    $check(count($mailer->links) === 3, 'Reenvio para e-mail inexistente não envia nada');

    $check($verification->confirm($mailer->links[2]['token']) === true, 'Link mais recente ativa a conta');
    $active = $user($email);
    $check($active['status'] === 'active' && $active['email_verified_at'] !== null, 'Conta ativa com data de confirmação');
    $check($verification->confirm($mailer->links[2]['token']) === false, 'Link não pode ser reutilizado');
    $check((new Authentication())->attempt($email, $password) !== null, 'Login funciona depois de confirmar');

    $check($submit() === null && $mailer->notices === [$email], 'E-mail ativo tem a mesma resposta e recebe só um aviso');
    $check(count($mailer->links) === 3, 'Conta ativa não recebe novo link');
    $_POST = ['email' => $email];
    $register->resend();
    $check(count($mailer->links) === 3, 'Conta ativa não recebe reenvio');

    $mailer->fail = true;
    $failing = $prefix . '-falha@example.invalid';
    $check($submit(['email' => $failing]) === null, 'Falha no envio não vira erro para a pessoa');
    $check($user($failing)['status'] === 'registered', 'Falha no envio mantém a conta pendente');
    $mailer->fail = false;
    $_POST = ['email' => $failing];
    $register->resend();
    $check(end($mailer->links)['email'] === $failing, 'Depois da falha, o reenvio resolve');

    $limited = $prefix . '-limite@example.invalid';
    $last = null;
    for ($i = 0; $i < 11; $i++) {
        $last = $submit(['email' => $limited]);
    }
    $check(is_string($last) && str_contains($last, 'Muitas tentativas'), 'Limite por e-mail barra repetição');
} finally {
    $statement = $pdo->prepare('DELETE FROM users WHERE email LIKE ?');
    $statement->execute([$prefix . '%']);
}
ob_end_flush();
if ($failed > 0) {
    exit(1);
}
echo "Todos os testes passaram.\n";
