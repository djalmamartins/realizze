<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\Thumb;
use Moves\Support\Upload;

/** Biblioteca pública de imagens e PDFs. Upload real pela MovesCode Storage. */
final class CmsMedia
{
    private const MAX_BYTES = 8388608;
    private const USAGE = [
        ['cms_pages', 'image_path'], ['cms_pages', 'og_image'], ['cms_seo_routes', 'og_image'],
        ['posts', 'cover'], ['posts', 'og_image'],
    ];

    public static function root(): string
    {
        $root = BASE_PATH . '/public/uploads/cms';
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new \RuntimeException('Diretório de mídia indisponível.');
        }
        return $root;
    }

    public static function url(string $storedPath): string
    {
        return '/uploads/cms/' . ltrim($storedPath, '/');
    }

    public static function urlById(int $id): string
    {
        return self::url((string) CmsQuery::value('SELECT stored_path FROM cms_media WHERE id=?', [$id]));
    }

    public static function listing(array $filters, int $page): array
    {
        $where = '1=1';
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (original_name LIKE ? OR title LIKE ? OR alt_text LIKE ?)';
            array_push($args, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
        }
        if (($filters['kind'] ?? '') === 'image') {
            $where .= " AND mime_type LIKE 'image/%'";
        } elseif (($filters['kind'] ?? '') === 'pdf') {
            $where .= " AND mime_type = 'application/pdf'";
        }
        return CmsQuery::page('FROM cms_media WHERE ' . $where, '*', 'id DESC', $args, $page, 24);
    }

    public static function upload(array $file, array $input, int $actorId): int
    {
        $upload = new Upload(self::root(), self::MAX_BYTES);
        $original = is_string($file['name'] ?? null) ? basename($file['name']) : '';
        $name = pathinfo($original, PATHINFO_FILENAME);
        $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $mime = $tmp !== '' && is_file($tmp) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        if (str_starts_with($mime, 'image/')) {
            $stored = $upload->image($file, $name);
        } elseif ($mime === 'application/pdf') {
            $stored = (new Upload(self::root(), self::MAX_BYTES))->file($file, $name);
        } else {
            throw new InvalidArgumentException('Envie imagens JPG, PNG, GIF ou WebP, ou arquivos PDF de até 8 MB.');
        }
        if ($stored === null) {
            throw new InvalidArgumentException('Arquivo recusado: confira o formato e o tamanho.');
        }
        $path = self::root() . '/' . $stored;
        $info = str_starts_with($mime, 'image/') ? @getimagesize($path) : false;
        CmsQuery::run(
            'INSERT INTO cms_media (stored_path,original_name,mime_type,size_bytes,width,height,title,alt_text,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [$stored, mb_substr($original, 0, 255), $mime, (int) filesize($path), $info ? $info[0] : null, $info ? $info[1] : null,
                CmsContent::text($input['title'] ?? '', 190, 'Título'), CmsContent::text($input['alt_text'] ?? '', 255, 'Texto alternativo'), $actorId]
        );
        $id = (int) \MovesCode\Model\Connection::get()->lastInsertId();
        CmsAudit::record($actorId, 'media.uploaded', 'media', (string) $id, ['name' => $original]);
        return $id;
    }

    public static function thumb(array $media): string
    {
        if (!str_starts_with($media['mime_type'], 'image/') || $media['mime_type'] === 'image/gif') {
            return self::url($media['stored_path']);
        }
        try {
            $thumb = (new Thumb(self::root(), BASE_PATH . '/public/uploads/cms-cache'))->make($media['stored_path'], 320, 220);
            if (is_string($thumb) && str_starts_with($thumb, BASE_PATH . '/public/')) {
                return substr($thumb, strlen(BASE_PATH . '/public'));
            }
        } catch (\Throwable $error) {
            error_log('Realizze CMS: miniatura indisponível.');
        }
        return self::url($media['stored_path']);
    }

    public static function update(int $id, array $input, int $actorId): void
    {
        CmsQuery::run('UPDATE cms_media SET title=?, alt_text=? WHERE id=?', [
            CmsContent::text($input['title'] ?? '', 190, 'Título'), CmsContent::text($input['alt_text'] ?? '', 255, 'Texto alternativo'), $id,
        ]);
        CmsAudit::record($actorId, 'media.updated', 'media', (string) $id);
    }

    public static function usage(array $media): int
    {
        $url = self::url($media['stored_path']);
        $total = 0;
        foreach (self::USAGE as [$table, $column]) {
            $total += (int) CmsQuery::value("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$url]);
        }
        $like = '%' . $url . '%';
        $total += (int) CmsQuery::value('SELECT COUNT(*) FROM cms_pages WHERE content LIKE ?', [$like]);
        $total += (int) CmsQuery::value('SELECT COUNT(*) FROM posts WHERE content LIKE ? AND deleted_at IS NULL', [$like]);
        return $total;
    }

    public static function delete(int $id, int $actorId): void
    {
        $media = CmsQuery::one('SELECT * FROM cms_media WHERE id=?', [$id]);
        if ($media === null) {
            throw new InvalidArgumentException('Arquivo não encontrado.');
        }
        $uses = self::usage($media);
        if ($uses > 0) {
            throw new InvalidArgumentException('Este arquivo está em uso em ' . $uses . ' conteúdo(s) e não pode ser excluído.');
        }
        (new Upload(self::root()))->remove($media['stored_path']);
        CmsQuery::run('DELETE FROM cms_media WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'media.deleted', 'media', (string) $id, ['name' => $media['original_name']]);
    }
}
