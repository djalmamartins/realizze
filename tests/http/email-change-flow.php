<?php
// AUTH-005: troca do e-mail de acesso (migration 022). Pedido em Meu perfil com a senha; troca só ao confirmar o link.
declare(strict_types=1);
require __DIR__ . '/client.php';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
use Moves\Services\EmailChange;
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$suffix = bin2hex(random_bytes(4));
$old = "troca-$suffix@example.invalid"; $new = "novo-$suffix@example.invalid"; $password = 'Troca#Teste2026';
$pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status, email_verified_at) VALUES ('Troca', 'Email', ?, ?, 'active', NOW())")->execute([$old, password_hash($password, PASSWORD_DEFAULT)]);
$userId = (int) $pdo->lastInsertId();
try {
    $h = new H();
    $check($h->login($old, $password), 'Pessoa entra com o e-mail atual');
    $get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
    $page = $get($h, '/p/perfil?aba=seguranca');
    $check(str_contains($page, 'action="/minha-conta/perfil/email"'), 'Segurança oferece a troca de e-mail');
    $h->req('POST', '/minha-conta/perfil/email', ['csrf_token' => $h->csrf($page), 'profile_tab' => 'seguranca', 'new_email' => $new, 'current_password' => 'errada']);
    $check(str_contains($get($h, '/p/perfil?aba=seguranca'), 'Senha incorreta'), 'Senha errada é recusada');
    $sent = [];
    $token = EmailChange::request($userId, $password, strtoupper($new), static function (string $to, string $name, string $t) use (&$sent): void { $sent[] = [$to, $t]; });
    $check(count($sent) === 1 && $sent[0][0] === $new && $sent[0][1] === $token, 'Link vai para o NOVO endereço, em minúsculas');
    $check((string) $pdo->query("SELECT email FROM users WHERE id=$userId")->fetchColumn() === $old, 'Antes de confirmar, o e-mail não muda');
    $taken = [];
    EmailChange::request($userId, $password, 'pj-dono@example.invalid', static function () use (&$taken): void { $taken[] = 1; });
    $check($taken === [], 'Endereço de outra conta não recebe link (mesma resposta na tela)');
    $token = EmailChange::request($userId, $password, $new, static function () {});
    $web = new H(); $web->host = 'www.realizze.lab';
    $show = $web->req('GET', '/confirmar-novo-email?token=' . $token);
    $check($show['code'] === 200 && str_contains($show['body'], $new) && (string) $pdo->query("SELECT email FROM users WHERE id=$userId")->fetchColumn() === $old, 'Abrir o link só mostra a confirmação (nada muda no GET)');
    $done = $web->req('POST', '/confirmar-novo-email', ['token' => $token, 'csrf_token' => $web->csrf($show['body'])]);
    $check(str_contains($done['body'], 'E-mail alterado') && (string) $pdo->query("SELECT email FROM users WHERE id=$userId")->fetchColumn() === $new, 'Confirmar troca o e-mail');
    $check($h->req('GET', '/p')['code'] === 302, 'Sessão aberta antes da troca é encerrada');
    $check((new H())->login($new, $password) && !(new H())->login($old, $password), 'Entra com o novo e-mail; o antigo não funciona mais');
    $again = $web->req('POST', '/confirmar-novo-email', ['token' => $token, 'csrf_token' => $web->csrf($web->req('GET', '/confirmar-novo-email?token=' . $token)['body'])]);
    $check(str_contains($again['body'], 'Não foi possível trocar'), 'O mesmo link não vale duas vezes');
} finally {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
