<?php
// Segurança da empresa, rotas antigas e título do Dashboard. Rodar contra o servidor temporário do banco de teste.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$o = new H(); $o->login('pj-dono@example.invalid', 'PjMissao#2026');
$s = $o->req('GET', "/e/$slug/seguranca");
$check($s['code'] === 200 && !str_contains($s['body'], 'module-pending') && str_contains($s['body'], 'Autenticação em dois fatores'), 'Segurança abre com dados reais, sem "Em breve"');
$check(str_contains($s['body'], 'pj-leitor@example.invalid') && str_contains($s['body'], 'Mudanças de acesso recentes'), 'Dono vê a equipe e as mudanças de acesso');
$check(!preg_match('/\d+%/', strip_tags($s['body'])), 'Sem nota percentual de segurança');
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$vs = $v->req('GET', "/e/$slug/seguranca");
$check($vs['code'] === 200 && str_contains($vs['body'], 'Sua conta') && !str_contains($vs['body'], 'pj-dono@example.invalid'), 'Visualizador vê só a própria conta');
foreach (['empresarial' => '', 'configuracoes' => '/empresa', 'assistente' => '', 'contatos' => '/clientes'] as $old => $target) {
    $r = $o->req('GET', "/e/$slug/$old");
    $check($r['code'] === 302 && $r['location'] === "/e/$slug$target", "/$old leva a /e/$slug$target");
}
$check($o->req('GET', '/p/assistente')['code'] === 200, 'Na PF, /p/assistente continua abrindo');
$d = $o->req('GET', "/e/$slug");
$check(str_contains($d['body'], '<title>Dashboard | Realizze'), 'Aba do navegador diz Dashboard na empresa');
$check(str_contains($o->req('GET', '/p')['body'], '<title>Visão Geral | Realizze'), 'Na PF continua Visão Geral');
$check(substr_count($d['body'], 'class="nav-soon"') === 3, 'Menu marca "Em breve" só em DRE, Obrigações e Ordens de serviço');
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
