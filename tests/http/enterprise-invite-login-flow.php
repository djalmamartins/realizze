<?php
// Convite aberto antes de entrar (roteiro PJ-13): o login leva de volta ao convite, e aceitar abre a empresa.
// Exige pj-convidado@example.invalid no banco de teste, sem vínculo com a Alfa.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
require_once dirname(__DIR__, 2) . '/source/Boot/app.php';
require_once dirname(__DIR__, 2) . '/source/Boot/database.php';
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
// Estado inicial conhecido: o convidado ainda não é membro nem tem convite pendente na Alfa.
$orgId = (int) $pdo->query("SELECT id FROM organizations WHERE slug = " . $pdo->quote($slug))->fetchColumn();
$guestId = (int) $pdo->query("SELECT id FROM users WHERE email = 'pj-convidado@example.invalid'")->fetchColumn();
$pdo->prepare('DELETE FROM organization_members WHERE organization_id = ? AND user_id = ?')->execute([$orgId, $guestId]);
$pdo->prepare("UPDATE organization_invitations SET status = 'revoked', revoked_at = NOW() WHERE organization_id = ? AND email = ? AND status = 'pending'")->execute([$orgId, 'pj-convidado@example.invalid']);
$o = new H(); $o->login('pj-dono@example.invalid', 'PjMissao#2026');
$eq = $o->req('GET', "/e/$slug/equipe");
$o->req('POST', '/minha-conta/equipe/convidar', ['csrf_token' => $o->csrf($eq['body']), 'email' => 'pj-convidado@example.invalid', 'role' => 'viewer']);
preg_match('#/convite-empresa\?token=([a-f0-9]{64})#', $o->req('GET', "/e/$slug/equipe")['body'], $m);
$check(isset($m[1]), 'Dono recebe o link do convite');
$guest = new H();
$first = $guest->req('GET', '/convite-empresa?token=' . ($m[1] ?? ''));
$check($first['code'] === 302 && $first['location'] === '/convite-empresa', 'O token sai da URL na primeira abertura');
$page = $guest->req('GET', '/convite-empresa');
$check(str_contains($page['body'], 'Você foi convidado') && str_contains($page['body'], 'você volta para este convite'), 'Sem login, a página explica que o convite será retomado');
$login = $guest->req('GET', '/entrar');
$r = $guest->req('POST', '/entrar', ['email' => 'pj-convidado@example.invalid', 'password' => 'PjMissao#2026', 'csrf_token' => $guest->csrf($login['body'])]);
$check($r['code'] === 302 && $r['location'] === '/convite-empresa', 'Depois do login, volta para o convite');
$page = $guest->req('GET', '/convite-empresa');
$check(str_contains($page['body'], 'Aceitar convite'), 'Convite pode ser aceito');
$accept = $guest->req('POST', '/convite-empresa/aceitar', ['csrf_token' => $guest->csrf($page['body'])]);
$check($accept['code'] === 303 && $accept['location'] === "/e/$slug", 'Aceitar abre a empresa');
$check($guest->req('GET', "/e/$slug")['code'] === 200, 'Convidado acessa a empresa');
$check(str_contains($o->req('GET', "/e/$slug/auditoria?tipo=team.invite_accepted")['body'], 'Convite aceito'), 'Aceite fica na auditoria');
$plain = new H();
$check($plain->login('pj-leitor@example.invalid', 'PjMissao#2026'), 'Login sem convite continua indo para /p');
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
