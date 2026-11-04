<?php
// Bancos e contas da empresa por HTTP: cadastrar cartão com datas, editar, arquivar e permissão. Servidor temporário do banco de teste.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$o = new H(); $o->login('pj-dono@example.invalid', 'PjMissao#2026');
$page = $o->req('GET', "/e/$slug/cartoes"); $csrf = $o->csrf($page['body']);
$name = 'Cartão HTTP ' . bin2hex(random_bytes(2));
$o->req('POST', '/minha-conta/financeiro/conta', ['csrf_token' => $csrf, 'back' => 'contas', 'name' => $name, 'type' => 'credit_card', 'credit_limit' => '2.000,00', 'closing_day' => '3', 'due_day' => '10']);
$page = $o->req('GET', "/e/$slug/cartoes");
$check(str_contains($page['body'], 'Conta cadastrada') && str_contains($page['body'], 'Fecha dia 3 · vence dia 10'), 'Cartão é cadastrado com fechamento e vencimento');
// A janela de edição da conta é a última aberta antes do campo com o nome dela.
$at = strpos($page['body'], 'name="name" maxlength="120" required value="' . htmlspecialchars($name) . '"');
preg_match_all('/id="account-edit-(\d+)"/', substr($page['body'], 0, $at === false ? 0 : $at), $m);
$id = $at === false ? '' : (string) end($m[1]);
$check($id !== '', 'Cada conta tem sua janela de edição');
$o->req('POST', '/minha-conta/financeiro/conta-editar', ['csrf_token' => $csrf, 'back' => 'contas', 'id' => $id, 'name' => $name . ' editado', 'credit_limit' => '2.500,00', 'closing_day' => '4', 'due_day' => '11']);
$page = $o->req('GET', "/e/$slug/cartoes");
$check(str_contains($page['body'], 'Conta atualizada') && str_contains($page['body'], 'Fecha dia 4 · vence dia 11'), 'Edição salva as datas do cartão');
$check(str_contains($o->req('GET', "/e/$slug/faturas")['body'], htmlspecialchars($name . ' editado')), 'Cartão aparece em Cartões e faturas');
$v = new H(); $v->login('pj-leitor@example.invalid', 'PjMissao#2026');
$vp = $v->req('GET', "/e/$slug/cartoes");
$check($v->req('POST', '/minha-conta/financeiro/conta-arquivar', ['csrf_token' => $v->csrf($vp['body']), 'back' => 'contas', 'id' => $id])['code'] === 403, 'Visualizador não arquiva');
$o->req('POST', '/minha-conta/financeiro/conta-arquivar', ['csrf_token' => $csrf, 'back' => 'contas', 'id' => $id]);
$page = $o->req('GET', "/e/$slug/cartoes");
$check(str_contains($page['body'], 'Conta arquivada') && !str_contains($page['body'], htmlspecialchars($name . ' editado')), 'Dono arquiva o cartão sem saldo');
$check($o->req('GET', '/e/missao-beta-me/cartoes')['code'] === 200 && !str_contains($o->req('GET', '/e/missao-beta-me/cartoes')['body'], 'Cartão HTTP'), 'Outra empresa não mostra o cartão');
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
