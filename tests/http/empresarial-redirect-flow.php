<?php
// PF-09: /p/empresarial (prévia antiga dos módulos PJ) leva ao cadastro real de empresa no painel PF.
// Rodar contra o servidor temporário do banco de teste. Usuário: REALIZZE_TEST_EMAIL/REALIZZE_TEST_PASSWORD.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };

$anon = new H();
$a = $anon->req('GET', '/p/empresarial');
$check($a['code'] === 302 && $a['location'] === '/entrar', 'Sem sessão, /p/empresarial pede login');

$u = new H();
$check($u->login(getenv('REALIZZE_TEST_EMAIL') ?: 'pj-dono@example.invalid', getenv('REALIZZE_TEST_PASSWORD') ?: 'PjMissao#2026'), 'Login do usuário de teste');
$r = $u->req('GET', '/p/empresarial');
$check($r['code'] === 302 && $r['location'] === '/p#cadastrar-empresa', '/p/empresarial leva a /p#cadastrar-empresa');
$check(!str_contains($r['body'], 'Prévia sem operações'), 'A prévia antiga não é mais exibida');
$old = $u->req('GET', '/minha-conta?pagina=empresarial');
$check($old['code'] === 302 && $old['location'] === '/p/empresarial', 'Endereço antigo /minha-conta?pagina=empresarial segue o mesmo caminho');
$p = $u->req('GET', '/p');
$check($p['code'] === 200 && str_contains($p['body'], 'id="company-dialog"') && str_contains($p['body'], 'action="/minha-conta/financeiro/empresa"'), 'O painel PF traz o cadastro real de empresa');
$check(!str_contains($p['body'], '/p/empresarial'), 'O menu PF não aponta para a prévia antiga');

$js = (string) @file_get_contents(dirname(__DIR__, 2) . '/container/app/realizze/v1/assets/js/dashboard.js');
$check(str_contains($js, "hash === '#cadastrar-empresa'") && str_contains($js, 'companyDialog.showModal()'), 'O JS abre o cadastro quando chega por #cadastrar-empresa');

echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
