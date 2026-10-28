<?php
require_once dirname(__DIR__) . '/source/Boot/app.php';

$checks = [
    'email válido' => is_email('teste@example.com'),
    'email inválido' => !is_email('inválido'),
    'slug unicode' => str_slug('Gestão de Condomínios') === 'gestao-de-condominios',
    'studly' => str_studly_case('meu-condominio') === 'MeuCondominio',
    'camel' => str_camel_case('meu-condominio') === 'meuCondominio',
    'limite palavras' => str_limit_words('um dois três', 2) === 'um dois...',
    'limite caracteres' => str_limit_chars('Olá mundo', 3) === 'Olá...',
    'preço BR' => str_price(1234.5) === '1.234,50',
    'URL base' => url() === rtrim((string) (getenv('APP_URL') ?: 'https://www.realizze.lab'), '/'),
    'URL relativa' => url('/sobre') === url() . '/sobre',
    'senha' => passwd_verify('segredo', password_hash('segredo', PASSWORD_DEFAULT)),
];

try {
    url('https://example.com');
    $checks['URL externa bloqueada'] = false;
} catch (InvalidArgumentException) {
    $checks['URL externa bloqueada'] = true;
}

foreach ($checks as $name => $passed) {
    echo ($passed ? 'OK' : 'FALHOU') . ' - ' . $name . PHP_EOL;
    if (!$passed) {
        exit(1);
    }
}

echo 'TESTE HELPERS: APROVADO' . PHP_EOL;
