<?php
declare(strict_types=1);

/**
 * Publica os assets do site institucional com MovesCode Compress.
 *
 * Execução manual/deploy:
 *   php scripts/publish-web-assets.php
 */
use MovesCode\Compress\CSS;
use MovesCode\Compress\JS;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$source = $root . '/container/web/realizze/v1/assets';
$target = $root . '/public/assets/realizze/v1';

$compressedFiles = [
    ['css/style.css', 'css/style.min.css', CSS::class],
    ['css/responsive-fixes.css', 'css/responsive-fixes.min.css', CSS::class],
    ['js/main.js', 'js/main.min.js', JS::class],
];

foreach ($compressedFiles as [$input, $output, $compressor]) {
    $sourceFile = $source . '/' . $input;
    $targetFile = $target . '/' . $output;

    if (!is_file($sourceFile) || !is_readable($sourceFile)) {
        throw new RuntimeException('Asset fonte não encontrado: ' . $input);
    }
    if (!is_dir(dirname($targetFile)) && !mkdir(dirname($targetFile), 0775, true) && !is_dir(dirname($targetFile))) {
        throw new RuntimeException('Não foi possível criar o destino: ' . dirname($targetFile));
    }

    $result = (new $compressor($sourceFile))->minify($targetFile);
    if ($result === '' || !is_file($targetFile)) {
        throw new RuntimeException('Falha ao publicar o asset: ' . $output);
    }
    echo 'Publicado: /assets/realizze/v1/' . $output . PHP_EOL;
}

$images = ['favicon.svg', 'new-logo-realizze.svg'];
foreach ($images as $image) {
    $sourceFile = $source . '/images/' . $image;
    $targetFile = $target . '/images/' . $image;

    if (!is_file($sourceFile) || !is_readable($sourceFile)) {
        throw new RuntimeException('Imagem fonte não encontrada: ' . $image);
    }
    if (!is_dir(dirname($targetFile)) && !mkdir(dirname($targetFile), 0775, true) && !is_dir(dirname($targetFile))) {
        throw new RuntimeException('Não foi possível criar o destino de imagens.');
    }
    if (!copy($sourceFile, $targetFile)) {
        throw new RuntimeException('Falha ao publicar a imagem: ' . $image);
    }
    echo 'Publicado: /assets/realizze/v1/images/' . $image . PHP_EOL;
}
