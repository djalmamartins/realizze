<?php
// Pagar com fornecedor por HTTP: cadastrar fornecedor, lançar despesa vinculada, filtrar e ver no detalhe. Servidor temporário do banco de teste.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = $argv[1] ?? 'cueca-verde-354';
$o = new H(); $o->login('pj-dono@example.invalid', 'PjMissao#2026');
$list = $o->req('GET', "/e/$slug/fornecedores"); $csrf = $o->csrf($list['body']);
$name = 'Fornecedor HTTP ' . bin2hex(random_bytes(2));
$o->req('POST', '/minha-conta/contatos/salvar', ['csrf_token' => $csrf, 'back' => 'fornecedores', 'person_type' => 'company', 'legal_name' => $name, 'is_supplier' => '1']);
$pagar = $o->req('GET', "/e/$slug/pagar");
preg_match('/<option value="(\d+)">' . preg_quote($name, '/') . '<\/option>/', $pagar['body'], $m);
$supplier = $m[1] ?? '';
$check($supplier !== '' && str_contains($pagar['body'], 'name="supplier_id"'), 'Modal de despesa oferece o fornecedor');
preg_match('/<select name="account_id" required>.*?<option value="(\d+)"/s', $pagar['body'], $a);
$o->req('POST', '/minha-conta/financeiro/lancamento', ['csrf_token' => $csrf, 'kind' => 'expense', 'source' => 'entry', 'mes' => date('Y-m'), 'back' => 'pagar', 'description' => 'Compra HTTP', 'amount' => '123,45', 'due_date' => date('Y-m-d'), 'account_id' => $a[1] ?? '', 'supplier_id' => $supplier, 'idempotency_key' => bin2hex(random_bytes(16))]);
$filtered = $o->req('GET', "/e/$slug/pagar?fornecedor=$supplier");
$check(str_contains($filtered['body'], 'Compra HTTP') && str_contains($filtered['body'], 'Fornecedor: ' . $name), 'Despesa aparece filtrada pelo fornecedor, com chip do filtro');
$check(substr_count($filtered['body'], '<tr id="lancamento-') === 1, 'Filtro mostra só a despesa do fornecedor');
$detail = $o->req('GET', "/e/$slug/fornecedores/$supplier");
$check($detail['code'] === 200 && str_contains($detail['body'], 'Contas a pagar (1)') && str_contains($detail['body'], 'R$ 123,45'), 'Detalhe do fornecedor mostra a despesa e o total');
$check($o->req('GET', "/e/missao-alfa-ltda/fornecedores/$supplier")['code'] === 404, 'Fornecedor não abre em outra empresa');
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
