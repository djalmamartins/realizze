<?php
// ENT-012: CNPJ alfanumérico (IN RFB nº 2.229/2024, a partir de julho de 2026) e compatibilidade com o numérico.
// Exemplo oficial divulgado pela Receita/Serpro: 00.000.000/E08G-12 (E=21, G=23; somas 175 e 229).
declare(strict_types=1);
require_once dirname(__DIR__) . '/source/Boot/app.php';
use Moves\Services\Organizations as O;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$fail): void { echo ($ok ? 'OK' : 'FALHOU') . " - $label\n"; if (!$ok) $fail++; };
$check(O::validCnpj('11222333000181') && !O::validCnpj('11222333000180'), 'Numérico continua validando o dígito verificador');
$check(O::normalizeCnpj(' 00.000.000/e08g-12 ') === '00000000E08G12', 'Máscara e espaços saem; letras ficam em maiúsculas');
$check(O::validCnpj(O::normalizeCnpj('00.000.000/E08G-12')), 'Exemplo alfanumérico oficial é válido');
$check(!O::validCnpj('00000000E08G13') && !O::validCnpj('00000000E08G21'), 'Dígito verificador errado é recusado');
$check(!O::validCnpj('00000000E08GA2') && !O::validCnpj('0000000E08G12'), 'Verificadores com letra ou tamanho errado são recusados');
$check(!O::validCnpj('AAAAAAAAAAAA00') && !O::validCnpj('00000000000000'), 'Sequência repetida é recusada');
$check(!O::validCnpj('00000000e08g12'), 'Minúsculas só valem depois de normalizar');
$check(O::formatCnpj('00000000E08G12') === '00.000.000/E08G-12' && O::formatCnpj('11222333000181') === '11.222.333/0001-81', 'Formatação nos dois formatos');
// Regressão: antes, a letra era descartada por preg_replace('/\D+/') e um CNPJ alfanumérico válido virava "inválido".
$check(strlen(O::normalizeCnpj('12.ABC.345/01DE-35')) === 14, 'Normalização não descarta letras');
echo $fail === 0 ? "ENT-012: CNPJ ALFANUMÉRICO APROVADO.\n" : "ENT-012: $fail falha(s).\n";
exit($fail === 0 ? 0 : 1);
