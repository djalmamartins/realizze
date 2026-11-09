<?php
// ENT-012 (HTTP): cliente com CNPJ alfanumérico é salvo pela interface, aparece mascarado e é encontrado pela busca.
// Escreve no banco de teste.
declare(strict_types=1);
require __DIR__ . '/client.php';
$ok = 0; $fail = 0;
$check = function (bool $c, string $m) use (&$ok, &$fail) { echo ($c ? 'OK' : 'FALHOU') . " - $m\n"; $c ? $ok++ : $fail++; };
$dv = static function (string $base): string {
    foreach ([12, 13] as $size) { $sum = 0; $w = $size - 7; for ($i = 0; $i < $size; $i++) { $sum += (ord($base[$i]) - 48) * $w--; if ($w < 2) $w = 9; } $r = $sum % 11; $base .= (string) ($r < 2 ? 0 : 11 - $r); }
    return $base;
};
$chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$base = ''; for ($i = 0; $i < 12; $i++) $base .= $chars[random_int(0, 35)];
$cnpj = $dv($base);
$masked = substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12);
$o = new H();
if (!$o->login('pj-dono@example.invalid', 'PjMissao#2026')) { fwrite(STDERR, "login falhou\n"); exit(1); }
$get = static function (H $h, string $url): string { $r = $h->req('GET', $url); return ($r['code'] === 302 && $r['location'] ? $h->req('GET', $r['location']) : $r)['body']; };
$base = '/e/missao-alfa-ltda/clientes';
$name = 'Cliente Alfanumérico ' . bin2hex(random_bytes(2));
$r = $o->req('POST', '/minha-conta/contatos/salvar', ['csrf_token' => $o->csrf($get($o, $base)), 'back' => 'clientes', 'person_type' => 'company', 'legal_name' => $name, 'document' => strtolower($masked), 'is_customer' => '1']);
$list = $get($o, $base . '?busca=' . urlencode($name));
$check($r['code'] === 303 && str_contains($list, $name) && !str_contains($list, 'CNPJ inválido'), "CNPJ alfanumérico $masked é aceito (digitado em minúsculas)");
$check(str_contains($list, substr($cnpj, 0, 2) . '.***.***/****-' . substr($cnpj, -2)), 'Lista mostra o documento mascarado');
$found = $get($o, $base . '?busca=' . urlencode($masked));
$check(str_contains($found, $name), 'Busca pelo CNPJ com máscara encontra o cliente');
$wrong = substr($cnpj, 0, 13) . (string) (((int) substr($cnpj, -1) + 1) % 10);
$o->req('POST', '/minha-conta/contatos/salvar', ['csrf_token' => $o->csrf($get($o, $base)), 'back' => 'clientes', 'person_type' => 'company', 'legal_name' => $name . ' errado', 'document' => $wrong, 'is_customer' => '1']);
$check(!str_contains($get($o, $base . '?busca=' . urlencode($name . ' errado')), $name . ' errado</'), 'Dígito verificador errado é recusado');
echo "$ok OK, $fail falha(s)\n";
exit($fail ? 1 : 0);
