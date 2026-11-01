<?php
declare(strict_types=1);

/** Publica os assets do CMS com MovesCode Compress: php scripts/publish-cms-assets.php */
use MovesCode\Compress\CSS;
use MovesCode\Compress\JS;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$source = $root . '/container/cms/realizze/v1/assets';
$target = $root . '/public/assets/realizze-cms/v1';

foreach ([['css/cms.css', 'css/cms.min.css', CSS::class], ['js/cms.js', 'js/cms.min.js', JS::class]] as [$input, $output, $compressor]) {
    if (!is_file($source . '/' . $input)) {
        throw new RuntimeException('Asset fonte não encontrado: ' . $input);
    }
    $directory = dirname($target . '/' . $output);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Destino indisponível: ' . $output);
    }
    if ((new $compressor($source . '/' . $input))->minify($target . '/' . $output) === '') {
        throw new RuntimeException('Falha ao publicar: ' . $output);
    }
    echo 'Publicado: /assets/realizze-cms/v1/' . $output . PHP_EOL;
}
