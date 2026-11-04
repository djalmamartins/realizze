<?php
// Permissões da empresa no MySQL, por HTTP (roteiro PJ-03): cada ação de escrita com um Visualizador e um Operacional.
// Usuários de docs/empresa/PROGRESSO.md; pj-operacional@example.invalid tem o papel Operacional na Alfa.
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$slug = 'missao-alfa-ltda';
$writes = [
    'lancamento' => ['kind' => 'expense', 'description' => 'X', 'amount' => '1,00', 'due_date' => '2026-11-01'],
    'lancamento-editar' => ['kind' => 'expense', 'id' => '1'], 'baixa' => ['kind' => 'expense', 'id' => '1'], 'excluir' => ['id' => '1'],
    'conta' => ['name' => 'X', 'type' => 'checking'], 'conta-editar' => ['id' => '1', 'name' => 'X'], 'conta-arquivar' => ['id' => '1'],
    'transferencia' => ['amount' => '1,00'], 'categoria' => ['name' => 'X', 'kind' => 'expense'], 'categoria-excluir' => ['id' => '1'],
    'orcamento' => ['mes' => '2026-11'], 'meta' => ['name' => 'X', 'amount' => '1,00'],
    'recebimento-baixa' => ['id' => '1'], 'recebimento-cancelar' => ['id' => '1'], 'recorrencia/pausar' => ['id' => '1'],
    'cartao-compra' => [], 'cartao-pagar-fatura' => [], 'cartao-estornar-pagamento' => ['payment_id' => '1'],
    'centro-de-custo' => ['name' => 'X'], 'conciliacao-revisar' => ['line_id' => '1', 'status' => 'ignored'],
];
foreach (['pj-leitor@example.invalid' => 'Visualizador', 'pj-operacional@example.invalid' => 'Operacional'] as $email => $role) {
    $u = new H(); $u->login($email, 'PjMissao#2026');
    $page = $u->req('GET', "/e/$slug/pagar"); $csrf = $u->csrf($page['body']);
    $check($page['code'] === 200, "$role abre Pagar");
    $refused = [];
    foreach ($writes as $path => $fields) {
        $r = $u->req('POST', '/minha-conta/financeiro/' . $path, ['csrf_token' => $csrf, 'back' => 'pagar'] + $fields);
        if ($r['code'] !== 403) $refused[] = "$path ({$r['code']})";
    }
    $check($refused === [], "$role recebe 403 em todas as ações financeiras" . ($refused ? ': ' . implode(', ', $refused) : ''));
    $check($u->req('GET', '/minha-conta/financeiro/exportar?mes=2026-11')['code'] === 403, "$role não exporta relatório");
    $u->req('POST', '/minha-conta/equipe/convidar', ['csrf_token' => $csrf, 'email' => 'x@example.invalid', 'role' => 'viewer']);
    $check(str_contains($u->req('GET', "/e/$slug/equipe")['body'], 'não tem permissão'), "$role não convida");
    $check($u->req('GET', "/e/$slug/auditoria")['code'] === 403, "$role não abre a auditoria");
    $contact = $u->req('POST', '/minha-conta/contatos/salvar', ['csrf_token' => $csrf, 'back' => 'clientes', 'person_type' => 'company', 'legal_name' => "Contato $role " . bin2hex(random_bytes(2)), 'is_customer' => '1']);
    $check($role === 'Operacional' ? $contact['code'] === 303 : $contact['code'] === 403, $role === 'Operacional' ? 'Operacional cadastra cliente (contacts.manage)' : 'Visualizador não cadastra cliente');
    $check($u->req('GET', '/e/missao-beta-me')['code'] === 404, "$role não abre a outra empresa do dono");
}
echo "$ok OK, $fail falha(s)\n"; exit($fail ? 1 : 0);
