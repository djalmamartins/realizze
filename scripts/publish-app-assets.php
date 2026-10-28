<?php
declare(strict_types=1);

/**
 * Publica os assets do App usando o componente oficial MovesCode Compress.
 *
 * Execução manual/deploy:
 *   php scripts/publish-app-assets.php
 *
 * Os arquivos-fonte continuam isolados em container/app/.../assets.
 * Nenhum arquivo do tema é publicado diretamente.
 */
use MovesCode\Compress\CSS;
use MovesCode\Compress\JS;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$source = $root . '/container/app/realizze/v1/assets';
$target = $root . '/public/assets/realizze/v1/app';

$files = [
    ['css/dashboard.css', 'css/dashboard.min.css', CSS::class],
    ['js/dashboard.js', 'js/dashboard.min.js', JS::class],
];

foreach ($files as [$input, $output, $compressor]) {
    $sourceFile = $source . '/' . $input;
    $targetFile = $target . '/' . $output;

    if (!is_file($sourceFile) || !is_readable($sourceFile)) {
        throw new RuntimeException('Asset fonte não encontrado: ' . $input);
    }

    $result = (new $compressor($sourceFile))->minify($targetFile);
    if ($result === '' || !is_file($targetFile)) {
        throw new RuntimeException('Falha ao publicar o asset: ' . $output);
    }
    echo 'Publicado: /assets/realizze/v1/app/' . $output . PHP_EOL;
}
