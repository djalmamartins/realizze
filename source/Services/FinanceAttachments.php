<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Storage\Send;
use RuntimeException;

/** Arquivos dos lançamentos: ficam fora de public/ e só saem como download autenticado. */
final class FinanceAttachments
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt'];
    private const MAX_BYTES = 10485760;
    private const MAX_FILES = 5;

    private static function root(): string
    {
        $root = BASE_PATH . '/storage/finance';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Armazenamento de anexos indisponível.');
        }
        return $root;
    }

    /** @param list<array<string,mixed>> $files */
    public static function validate(array $files): void
    {
        if (count($files) > self::MAX_FILES) {
            throw new InvalidArgumentException('Envie no máximo ' . self::MAX_FILES . ' anexos por lançamento.');
        }
        foreach ($files as $file) {
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > self::MAX_BYTES) {
                throw new InvalidArgumentException('Anexo inválido ou acima de 10 MB.');
            }
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            if (!in_array($mime, self::MIMES, true) || !in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                throw new InvalidArgumentException('Formato de anexo não permitido. Use imagem, PDF ou texto.');
            }
        }
    }

    /**
     * @param array<string,mixed> $file
     * @return array{path:string,name:string,mime:string,size:int}
     */
    public static function store(array $file): array
    {
        $original = mb_substr(basename((string) $file['name']), 0, 190);
        try {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            $path = (new Send(self::root(), 'files', self::MIMES, self::EXTENSIONS))->upload($file, pathinfo($original, PATHINFO_FILENAME));
        } catch (RuntimeException) {
            throw new InvalidArgumentException('Formato de anexo não permitido. Use imagem, PDF ou texto.');
        }
        return ['path' => substr($path, strlen(self::root()) + 1), 'name' => $original, 'mime' => $mime, 'size' => (int) filesize($path)];
    }

    public static function remove(string $storedPath): void
    {
        $root = realpath(self::root());
        $path = realpath(self::root() . '/' . $storedPath);
        if ($root !== false && $path !== false && str_starts_with($path, $root . '/') && is_file($path)) {
            unlink($path);
        }
    }

    /** @param array{stored_path:string,original_name:string,mime_type:string} $attachment */
    public static function stream(array $attachment, bool $inline = false): void
    {
        $root = realpath(self::root());
        $path = realpath(self::root() . '/' . $attachment['stored_path']);
        if ($root === false || $path === false || !str_starts_with($path, $root . '/') || !is_file($path)) {
            AppError::render(404);
            return;
        }
        $name = str_replace(['"', '\\', "\r", "\n"], '', $attachment['original_name']);
        header('Content-Type: ' . (in_array($attachment['mime_type'], self::MIMES, true) ? $attachment['mime_type'] : 'application/octet-stream'));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        // O leitor de PDF do navegador não abre em sandbox; os demais formatos seguem isolados.
        if (!$inline || $attachment['mime_type'] !== 'application/pdf') {
            header('Content-Security-Policy: sandbox');
        }
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . (int) filesize($path));
        readfile($path);
    }
}
