<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Thumb;

if (!extension_loaded('gd') || !function_exists('imagejpeg')) {
    fwrite(STDERR, "FALHOU - GD com suporte JPEG é obrigatório.\n");
    exit(1);
}

$root = sys_get_temp_dir() . '/realizze-thumb-real-' . bin2hex(random_bytes(8));
$uploads = $root . '/uploads';
$cache = $root . '/cache';

function cleanTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            cleanTestDirectory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

try {
    if (!mkdir($uploads, 0700, true) || !mkdir($cache, 0700, true)) {
        throw new RuntimeException('Falha ao criar diretórios temporários.');
    }

    $image = imagecreatetruecolor(120, 80);
    if ($image === false) {
        throw new RuntimeException('Falha ao criar imagem de teste.');
    }
    $color = imagecolorallocate($image, 70, 120, 190);
    imagefilledrectangle($image, 0, 0, 119, 79, $color);
    $original = $uploads . '/amostra.jpg';
    if (!imagejpeg($image, $original, 90)) {
        imagedestroy($image);
        throw new RuntimeException('Falha ao gravar JPEG original.');
    }
    imagedestroy($image);

    $thumb = new Thumb($uploads, $cache, 75, 5, false);
    $result = $thumb->make('amostra.jpg', 60, 40);
    if (!is_string($result) || $result === '') {
        throw new RuntimeException('Cropper não retornou caminho de imagem.');
    }

    // A biblioteca pode retornar caminho absoluto ou relativo ao cache.
    $candidates = [$result, $cache . '/' . ltrim($result, '/')];
    $generated = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $generated = $candidate;
            break;
        }
    }
    if ($generated === null) {
        throw new RuntimeException('Imagem gerada não encontrada. Retorno: ' . $result);
    }

    $dimensions = getimagesize($generated);
    if ($dimensions === false || $dimensions[0] !== 60 || $dimensions[1] !== 40) {
        throw new RuntimeException('Dimensões inesperadas: ' . json_encode($dimensions));
    }

    echo "OK - JPEG original criado\n";
    echo "OK - Thumb gerou arquivo real\n";
    echo "OK - Imagem resultante 60x40\n";
    echo "TESTE THUMB REAL: APROVADO\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALHOU - ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    cleanTestDirectory($root);
}
