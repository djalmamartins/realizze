<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$public = $root . '/public/assets/realizze/v1';

$expected = [
    'css/style.min.css',
    'css/responsive-fixes.min.css',
    'js/main.min.js',
    'images/favicon.svg',
    'images/new-logo-realizze.svg',
];

foreach ($expected as $asset) {
    $path = $public . '/' . $asset;
    if (!is_file($path) || filesize($path) === 0) {
        throw new RuntimeException('Asset institucional ausente ou vazio: ' . $asset);
    }
}

$home = file_get_contents($root . '/container/web/realizze/v1/pages/home.php');
$head = file_get_contents($root . '/container/web/realizze/v1/components/head.php');
$scripts = file_get_contents($root . '/container/web/realizze/v1/components/scripts.php');
$logo = file_get_contents($public . '/images/new-logo-realizze.svg');

if (!str_contains($home, 'class="rz-hero"')) {
    throw new RuntimeException('A página inicial não contém o hero aprovado.');
}
if (!str_contains($home, '/assets/realizze/v1/images/new-logo-realizze.svg')) {
    throw new RuntimeException('A página inicial não referencia a logo oficial.');
}
if (!str_contains($head, 'style.min.css') || !str_contains($head, 'responsive-fixes.min.css')) {
    throw new RuntimeException('O head não referencia os estilos publicados.');
}
if (!str_contains($scripts, 'main.min.js')) {
    throw new RuntimeException('O layout não referencia o JavaScript publicado.');
}
if (!str_contains($logo, '<svg')) {
    throw new RuntimeException('A logo oficial publicada não é um SVG válido.');
}

echo "WEB assets: layout e publicação verificados.\n";
