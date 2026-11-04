<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Fotos das metas: ficam fora de public/ e só saem para o contexto dono da meta.
 * Toda foto é regravada em JPEG, o que limita o tamanho e descarta metadados como a localização.
 */
final class GoalPhotos
{
    private const MAX_BYTES = 5242880;
    private const MAX_PIXELS = 40000000;
    private const MAX_WIDTH = 1600;

    private static function root(): string
    {
        $root = BASE_PATH . '/storage/finance/goals';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Armazenamento de fotos indisponível.');
        }
        return $root;
    }

    /**
     * Grava a foto enviada e devolve o nome do arquivo; null se nenhuma foi enviada.
     *
     * @param array<string,mixed>|null $file
     */
    public static function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('Foto inválida ou acima de 5 MB.');
        }
        $tmp = (string) $file['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new InvalidArgumentException('Formato de foto não permitido. Use JPG ou PNG.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Foto muito grande. Use uma imagem de até 40 megapixels.');
        }
        $source = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($source === false) {
            throw new InvalidArgumentException('Não foi possível ler a foto. Tente outra imagem.');
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $source = self::upright($source, self::orientation($tmp));
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $width);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        if (!imagejpeg($canvas, self::root() . '/' . $name, 84)) {
            throw new RuntimeException('Não foi possível gravar a foto.');
        }
        return $name;
    }

    public static function remove(?string $name): void
    {
        if (is_string($name) && preg_match('/^[a-f0-9]{32}\.jpg$/', $name) && is_file(self::root() . '/' . $name)) {
            unlink(self::root() . '/' . $name);
        }
    }

    public static function stream(string $name): void
    {
        $path = self::root() . '/' . $name;
        if (!preg_match('/^[a-f0-9]{32}\.jpg$/', $name) || !is_file($path)) {
            AppError::render(404);
            return;
        }
        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, max-age=604800, immutable');
        header('Content-Length: ' . (int) filesize($path));
        readfile($path);
    }

    /** Orientação gravada pela câmera (EXIF); fotos de celular costumam depender dela. */
    private static function orientation(string $path): int
    {
        $data = (string) file_get_contents($path, false, null, 0, 131072);
        $offset = strpos($data, "Exif\0\0");
        if ($offset === false) {
            return 1;
        }
        $tiff = $offset + 6;
        $little = substr($data, $tiff, 2) === 'II';
        $read = static function (int $at, int $bytes) use ($data, $little): int {
            $value = unpack($bytes === 2 ? ($little ? 'v' : 'n') : ($little ? 'V' : 'N'), substr($data, $at, $bytes) . "\0\0\0\0");
            return (int) $value[1];
        };
        $ifd = $tiff + $read($tiff + 4, 4);
        if ($ifd + 2 > strlen($data)) {
            return 1;
        }
        $entries = $read($ifd, 2);
        for ($i = 0; $i < $entries; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > strlen($data)) {
                return 1;
            }
            if ($read($entry, 2) === 0x0112) {
                return $read($entry + 8, 2);
            }
        }
        return 1;
    }

    private static function upright(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
