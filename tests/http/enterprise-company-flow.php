<?php
// Fluxo HTTP de Configurações da empresa, Equipe e Auditoria. Rodar contra o servidor temporário do banco de teste,
// com os usuários de docs/empresa/PROGRESSO.md.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$o = new H(); $o->login('pj-dono@example.invalid', 'PjMissao#2026');
$page = $o->req('GET', "/e/$slug/empresa"); $csrf = $o->csrf($page['body']);
$check(str_contains($page['body'], 'name="trade_name"'), 'Dono vê o formulário de edição');
$r = $o->req('POST', '/minha-conta/empresa/dados', ['csrf_token' => $csrf, 'name' => 'Missao Alfa Ltda', 'cnpj' => '11.222.333/0001-82']);
$after = $o->req('GET', "/e/$slug/empresa");
$check($r['code'] === 303 && str_contains($after['body'], 'CNPJ inválido') && str_contains($after['body'], '11.222.333/0001-82'), 'CNPJ inválido é recusado e o formulário volta preenchido');
$r = $o->req('POST', '/minha-conta/empresa/dados', ['csrf_token' => $csrf, 'name' => 'Missao Alfa Ltda', 'trade_name' => 'Alfa Serviços', 'cnpj' => '11.222.333/0001-81', 'email' => 'Financeiro@Alfa.example', 'phone' => '(31) 3333-4444']);
$after = $o->req('GET', "/e/$slug/empresa");
$check(str_contains($after['body'], 'Dados da empresa salvos') && str_contains($after['body'], 'value="financeiro@alfa.example"') && str_contains($after['body'], '(31) 3333-4444'), 'Dados válidos são salvos e normalizados');
$check(str_contains($after['body'], 'Nome fantasia, CNPJ, E-mail, Telefone') || str_contains($after['body'], 'Nome fantasia, E-mail, Telefone'), 'Alteração aparece em Últimas alterações');
$r = $o->req('POST', '/minha-conta/empresa/dados', ['csrf_token' => $csrf, 'name' => 'Missao Alfa Ltda', 'trade_name' => 'Alfa Serviços', 'cnpj' => '11222333000181', 'email' => 'financeiro@alfa.example', 'phone' => '3133334444']);
$after = $o->req('GET', "/e/$slug/empresa");
$check(str_contains($after['body'], 'Nada foi alterado'), 'Reenviar o mesmo conteúdo não gera alteração');
$check($o->req('GET', "/e/$slug")['code'] === 200 && $o->req('GET', "/e/$slug/empresa")['code'] === 200, 'Endereço da empresa não muda com o nome');
$r = $o->req('POST', '/minha-conta/empresa/dados', ['csrf_token' => 'x', 'name' => 'Hack']);
$check($r['code'] === 403, 'Sem CSRF válido responde 403');
// convite com a 016
$eq = $o->req('GET', "/e/$slug/equipe");
$r = $o->req('POST', '/minha-conta/equipe/convidar', ['csrf_token' => $o->csrf($eq['body']), 'email' => 'novo-' . bin2hex(random_bytes(3)) . '@example.invalid', 'role' => 'finance']);
$eq = $o->req('GET', "/e/$slug/equipe");
$check(str_contains($eq['body'], 'Convite criado') || str_contains($eq['body'], 'e-mail não foi enviado'), 'Convite com papel Financeiro é criado no MySQL');
$au = $o->req('GET', "/e/$slug/auditoria");
$check($au['code'] === 200 && str_contains($au['body'], 'Convite enviado') && str_contains($au['body'], 'Papel: Financeiro') && str_contains($au['body'], 'Dados da empresa alterados'), 'Auditoria mostra convite e alteração cadastral');
$au = $o->req('GET', "/e/$slug/auditoria?tipo=team.invited");
$check(!str_contains($au['body'], '<strong>Dados da empresa alterados'), 'Filtro por ação funciona');
$beta = $o->req('GET', '/e/missao-beta-me/auditoria');
$check(str_contains($beta['body'], 'Nenhuma ação encontrada'), 'Auditoria de outra empresa não mostra ações da Alfa');
// leitor
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$vp = $v->req('GET', "/e/$slug/empresa");
$check($vp['code'] === 200 && !str_contains($vp['body'], 'name="trade_name"') && str_contains($vp['body'], 'Alfa Serviços'), 'Visualizador consulta sem formulário');
$r = $v->req('POST', '/minha-conta/empresa/dados', ['csrf_token' => $v->csrf($vp['body']), 'name' => 'Invadida']);
$vp = $v->req('GET', "/e/$slug/empresa");
$check(str_contains($vp['body'], 'não tem permissão') && !str_contains($vp['body'], 'Invadida'), 'Visualizador não altera os dados');
$check($v->req('GET', "/e/$slug/auditoria")['code'] === 403, 'Visualizador não abre a auditoria');
$check(!str_contains($v->req('GET', "/e/$slug")['body'], '/auditoria"'), 'Menu do Visualizador não mostra Auditoria');
$check($v->req('GET', '/e/missao-beta-me/empresa')['code'] === 404, 'Quem não é membro da Beta recebe 404');
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
