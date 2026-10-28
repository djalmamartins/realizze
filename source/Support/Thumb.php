<?php
declare(strict_types=1);

namespace Moves\Support;

use InvalidArgumentException;
use MovesCode\Cropper\Cropper;

final class Thumb
{
    private Cropper $cropper;
    private string $uploads;

    public function __construct(
        string $uploads,
        string $cachePath,
        int $quality = 75,
        int $compressor = 5,
        bool $webP = false
    ) {
        if ($uploads === '' || $cachePath === '') {
            throw new InvalidArgumentException('Diretórios de upload e cache são obrigatórios.');
        }
        $this->uploads = rtrim($uploads, '/');
        $this->cropper = new Cropper($cachePath, $quality, $compressor, $webP);
    }

    public function make(string $image, int $width, ?int $height = null): ?string
    {
        if ($width <= 0 || ($height !== null && $height <= 0)) {
            throw new InvalidArgumentException('Dimensões inválidas.');
        }
        if ($image === '' || str_contains($image, "\0") || str_contains($image, '\\')
            || str_starts_with($image, '/') || preg_match('~(^|/)\.\.(/|$)~', $image)) {
            throw new InvalidArgumentException('Caminho de imagem inválido.');
        }
        return $this->cropper->make($this->uploads . '/' . $image, $width, $height);
    }

    public function flush(?string $image = null): void
    {
        if ($image === null) {
            $this->cropper->flush();
            return;
        }
        if ($image === '' || str_contains($image, "\0") || str_contains($image, '\\')
            || str_starts_with($image, '/') || preg_match('~(^|/)\.\.(/|$)~', $image)) {
            throw new InvalidArgumentException('Caminho de imagem inválido.');
        }
        $this->cropper->flush($this->uploads . '/' . $image);
    }

    public function cropper(): Cropper { return $this->cropper; }
}
