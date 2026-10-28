<?php
declare(strict_types=1);

namespace Moves\Support;

use MovesCode\Storage\File;
use MovesCode\Storage\Image;
use MovesCode\Storage\Media;
use RuntimeException;
use Throwable;

/**
 * Uploads locais isolados por diretório; não exponha o diretório raiz à web.
 */
final class Upload
{
    private Message $message;
    private string $baseDir;
    private int $maxBytes;

    public function __construct(string $baseDir, int $maxBytes = 10485760)
    {
        if ($maxBytes < 1) {
            throw new RuntimeException('Limite de upload inválido.');
        }
        if (!is_dir($baseDir) || is_link($baseDir)) {
            throw new RuntimeException('Diretório de upload inexistente ou inválido.');
        }
        $root = realpath($baseDir);
        if ($root === false || !is_writable($root)) {
            throw new RuntimeException('Diretório de upload indisponível.');
        }
        $this->baseDir = $root;
        $this->maxBytes = $maxBytes;
        $this->message = new Message();
    }

    public function message(): Message
    {
        return $this->message;
    }

    public function image(array $image, string $name, int $width = 2000): ?string
    {
        if ($width < 1) {
            $this->message->error('Largura de imagem inválida.');
            return null;
        }
        return $this->upload($image, fn() => (new Image($this->baseDir, 'images'))->upload($image, $name, $width));
    }

    public function file(array $file, string $name): ?string
    {
        return $this->upload($file, fn() => (new File($this->baseDir, 'files'))->upload($file, $name));
    }

    public function media(array $media, string $name): ?string
    {
        return $this->upload($media, fn() => (new Media($this->baseDir, 'media'))->upload($media, $name));
    }

    public function remove(string $filePath): void
    {
        if ($filePath === '' || str_contains($filePath, "\0")) {
            return;
        }
        $candidate = str_starts_with($filePath, $this->baseDir . DIRECTORY_SEPARATOR)
            ? $filePath
            : $this->baseDir . DIRECTORY_SEPARATOR . ltrim($filePath, '/\\');
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || is_link($candidate)) {
            return;
        }
        $prefix = $this->baseDir . DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $prefix)) {
            $this->message->error('Arquivo fora do diretório permitido.');
            return;
        }
        if (!unlink($real)) {
            $this->message->error('Não foi possível excluir o arquivo.');
        }
    }

    private function upload(array $file, callable $store): ?string
    {
        $size = $file['size'] ?? null;
        $tmp = $file['tmp_name'] ?? null;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_int($size) || $size < 1 || $size > $this->maxBytes
            || !is_string($tmp) || !is_file($tmp)
            || filesize($tmp) > $this->maxBytes) {
            $this->message->error('Arquivo inválido ou acima do limite permitido.');
            return null;
        }
        try {
            $stored = $store();
            $root = $this->baseDir . DIRECTORY_SEPARATOR;
            if (!str_starts_with($stored, $root)) {
                throw new RuntimeException('Destino inesperado.');
            }
            return substr($stored, strlen($root));
        } catch (Throwable $exception) {
            error_log('Realizze upload: armazenamento recusado.');
            $this->message->error('Não foi possível enviar o arquivo.');
            return null;
        }
    }
}
