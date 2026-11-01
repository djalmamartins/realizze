<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\HtmlSanitizer;
use MovesCode\Model\Connection;

/** Destaques e depoimentos exibidos no site. */
final class CmsShowcase
{
    public const ALIGN = ['left' => 'Esquerda', 'center' => 'Centro', 'right' => 'Direita'];

    public static function slides(): array
    {
        return CmsQuery::all('SELECT * FROM cms_slides ORDER BY sort_order, id DESC');
    }

    public static function slide(int $id): ?array
    {
        return CmsQuery::one('SELECT * FROM cms_slides WHERE id=?', [$id]);
    }

    public static function saveSlide(?int $id, array $input, int $actorId): int
    {
        $title = CmsContent::text($input['title'] ?? '', 140, 'Título');
        if ($title === '') {
            throw new InvalidArgumentException('Informe o título.');
        }
        $link = CmsContent::text($input['link_url'] ?? '', 255, 'Destino');
        if ($link !== '' && (!HtmlSanitizer::safeUrl($link) || str_starts_with($link, 'mailto:'))) {
            throw new InvalidArgumentException('O destino precisa começar com / ou https://.');
        }
        $values = [$title, HtmlSanitizer::clean(is_string($input['content'] ?? null) ? $input['content'] : ''), CmsContent::mediaPath($input['image_path'] ?? ''), $link,
            CmsContent::text($input['link_label'] ?? '', 60, 'Texto do botão'), isset(self::ALIGN[$input['alignment'] ?? '']) ? $input['alignment'] : 'left',
            max(0, (int) ($input['sort_order'] ?? 0)), ($input['status'] ?? '') === 'published' ? 'published' : 'draft'];
        if ($id === null) {
            CmsQuery::run('INSERT INTO cms_slides (title,content,image_path,link_url,link_label,alignment,sort_order,status) VALUES (?,?,?,?,?,?,?,?)', $values);
            $id = (int) Connection::get()->lastInsertId();
        } else {
            CmsQuery::run('UPDATE cms_slides SET title=?,content=?,image_path=?,link_url=?,link_label=?,alignment=?,sort_order=?,status=? WHERE id=?', [...$values, $id]);
        }
        CmsAudit::record($actorId, 'slide.saved', 'slide', (string) $id, ['title' => $title, 'status' => $values[7]]);
        return $id;
    }

    public static function deleteSlide(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM cms_slides WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'slide.deleted', 'slide', (string) $id);
    }

    public static function testimonials(): array
    {
        return CmsQuery::all('SELECT * FROM cms_testimonials ORDER BY sort_order, id DESC');
    }

    public static function saveTestimonial(?int $id, array $input, int $actorId): int
    {
        $name = CmsContent::text($input['person_name'] ?? '', 120, 'Nome');
        $body = CmsContent::text($input['body'] ?? '', 1000, 'Depoimento');
        if ($name === '' || $body === '') {
            throw new InvalidArgumentException('Informe o nome e o depoimento.');
        }
        $rating = filter_var($input['rating'] ?? 5, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
        if ($rating === false) {
            throw new InvalidArgumentException('Selecione de 1 a 5 estrelas.');
        }
        $values = [$name, CmsContent::text($input['company'] ?? '', 120, 'Empresa'), $body, $rating, CmsContent::mediaPath($input['photo_path'] ?? ''),
            max(0, (int) ($input['sort_order'] ?? 0)), ($input['status'] ?? '') === 'published' ? 'published' : 'draft'];
        if ($id === null) {
            CmsQuery::run('INSERT INTO cms_testimonials (person_name,company,body,rating,photo_path,sort_order,status) VALUES (?,?,?,?,?,?,?)', $values);
            $id = (int) Connection::get()->lastInsertId();
        } else {
            CmsQuery::run('UPDATE cms_testimonials SET person_name=?,company=?,body=?,rating=?,photo_path=?,sort_order=?,status=? WHERE id=?', [...$values, $id]);
        }
        CmsAudit::record($actorId, 'testimonial.saved', 'testimonial', (string) $id, ['status' => $values[6]]);
        return $id;
    }

    public static function deleteTestimonial(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM cms_testimonials WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'testimonial.deleted', 'testimonial', (string) $id);
    }

    public static function publicSlides(): array
    {
        return self::read("SELECT * FROM cms_slides WHERE status='published' ORDER BY sort_order, id DESC LIMIT 6");
    }

    public static function publicTestimonials(): array
    {
        return self::read("SELECT * FROM cms_testimonials WHERE status='published' ORDER BY sort_order, id DESC LIMIT 12");
    }

    private static function read(string $sql): array
    {
        try {
            return CmsQuery::all($sql);
        } catch (\Throwable $error) {
            error_log('Realizze site: vitrine indisponível.');
            return [];
        }
    }
}
