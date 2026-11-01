<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\HtmlSanitizer;

/** Menus e SEO administrados no CMS, e leituras públicas consumidas pelo site. */
final class CmsSite
{
    public const SEO_ROUTES = ['/' => 'Início', '/sobre' => 'Sobre', '/solucoes' => 'Soluções', '/planos' => 'Planos', '/blog' => 'Conteúdos', '/contato' => 'Contato', '/termos' => 'Termos', '/privacidade' => 'Privacidade'];

    public static function menu(string $location, bool $onlyVisible = false): array
    {
        return CmsQuery::all('SELECT * FROM cms_menu_items WHERE location=?' . ($onlyVisible ? ' AND is_visible=1' : '') . ' ORDER BY sort_order, id', [$location]);
    }

    public static function saveMenuItem(?int $id, array $input, int $actorId): void
    {
        $label = CmsContent::text($input['label'] ?? '', 80, 'Rótulo');
        $url = CmsContent::text($input['url'] ?? '', 255, 'Destino');
        $location = ($input['location'] ?? '') === 'footer' ? 'footer' : 'header';
        if ($label === '' || !HtmlSanitizer::safeUrl($url) || str_starts_with($url, 'mailto:')) {
            throw new InvalidArgumentException('Informe rótulo e um destino começando com / ou https://.');
        }
        $order = max(0, (int) ($input['sort_order'] ?? 0));
        $visible = ($input['is_visible'] ?? '0') === '1' ? 1 : 0;
        if ($id === null) {
            CmsQuery::run('INSERT INTO cms_menu_items (location,label,url,sort_order,is_visible) VALUES (?,?,?,?,?)', [$location, $label, $url, $order, $visible]);
        } else {
            CmsQuery::run('UPDATE cms_menu_items SET location=?,label=?,url=?,sort_order=?,is_visible=? WHERE id=?', [$location, $label, $url, $order, $visible, $id]);
        }
        CmsAudit::record($actorId, 'menu.saved', 'menu', $id === null ? null : (string) $id, ['label' => $label, 'url' => $url]);
    }

    public static function deleteMenuItem(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM cms_menu_items WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'menu.deleted', 'menu', (string) $id);
    }

    public static function seoRoutes(): array
    {
        $saved = [];
        foreach (CmsQuery::all('SELECT * FROM cms_seo_routes') as $row) {
            $saved[$row['route_path']] = $row;
        }
        return $saved;
    }

    public static function saveSeoRoute(array $input, int $actorId): void
    {
        $path = is_string($input['route_path'] ?? null) ? $input['route_path'] : '';
        if (!isset(self::SEO_ROUTES[$path])) {
            throw new InvalidArgumentException('Rota inválida.');
        }
        CmsQuery::run(
            'INSERT INTO cms_seo_routes (seo_title,seo_description,canonical_url,og_image,robots_index,updated_by,route_path) VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),canonical_url=VALUES(canonical_url),og_image=VALUES(og_image),robots_index=VALUES(robots_index),updated_by=VALUES(updated_by)',
            [...CmsContent::seoValues($input), $actorId, $path]
        );
        CmsAudit::record($actorId, 'seo.updated', 'seo', $path);
    }

    // ---- Leituras públicas. Falhas nunca derrubam o site: retornam vazio. ----
    private static function safe(callable $read, mixed $fallback): mixed
    {
        try {
            return $read();
        } catch (\Throwable $error) {
            error_log('Realizze site: conteúdo do CMS indisponível.');
            return $fallback;
        }
    }

    public static function publishedPage(string $slug): ?array
    {
        return self::safe(static fn () => CmsQuery::one("SELECT * FROM cms_pages WHERE slug=? AND status='published'", [$slug]), null);
    }

    public static function publishedPosts(int $page, int $perPage = 9): array
    {
        return self::safe(static fn () => CmsQuery::page(
            "FROM posts p LEFT JOIN categories c ON c.id=p.category WHERE p.status='published' AND p.deleted_at IS NULL AND p.post_type IN ('article','news')",
            'p.id,p.title,p.uri,p.subtitle,p.cover,p.post_at,p.post_type,c.title AS category_title', 'p.post_at DESC, p.id DESC', [], $page, $perPage
        ), ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
    }

    public static function publishedPost(string $uri): ?array
    {
        return self::safe(static fn () => CmsQuery::one(
            "SELECT p.*, c.title AS category_title FROM posts p LEFT JOIN categories c ON c.id=p.category WHERE p.uri=? AND p.status='published' AND p.deleted_at IS NULL AND p.post_type<>'help'",
            [$uri]
        ), null);
    }

    /** Base de conhecimento: artigos do tipo Ajuda, lidos pelo App e pelo CMS. */
    public static function helpArticles(string $search, int $page, int $perPage = 20): array
    {
        $where = "p.status='published' AND p.deleted_at IS NULL AND p.post_type='help'";
        $args = [];
        if ($search !== '') {
            $where .= ' AND (p.title LIKE ? OR p.subtitle LIKE ? OR p.content LIKE ?)';
            array_push($args, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        }
        return self::safe(static fn () => CmsQuery::page('FROM posts p LEFT JOIN categories c ON c.id=p.category WHERE ' . $where, 'p.id,p.title,p.uri,p.subtitle,c.title AS category_title', 'c.title, p.title', $args, $page, $perPage), ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
    }

    public static function helpArticle(string $column, int|string $value): ?array
    {
        $column = $column === 'id' ? 'p.id' : 'p.uri';
        return self::safe(static fn () => CmsQuery::one("SELECT p.*, c.title AS category_title FROM posts p LEFT JOIN categories c ON c.id=p.category WHERE {$column}=? AND p.status='published' AND p.deleted_at IS NULL AND p.post_type='help'", [$value]), null);
    }

    public static function publishedFaqs(): array
    {
        return self::safe(static fn () => CmsQuery::all("SELECT question, answer FROM faqs WHERE status='published' ORDER BY sort_order, id"), []);
    }

    public static function publicMenu(string $location): array
    {
        return self::safe(static fn () => self::menu($location, true), []);
    }

    public static function publicSettings(): array
    {
        return self::safe(static fn () => CmsSettings::publicValues(), []);
    }

    /** Metadados da rota: valores vazios mantêm os textos do template. */
    public static function routeMeta(string $path): array
    {
        $row = self::safe(static fn () => CmsQuery::one('SELECT * FROM cms_seo_routes WHERE route_path=?', [$path]), null);
        return self::meta($row ?? []);
    }

    public static function meta(array $source, string $title = '', string $description = ''): array
    {
        $settings = self::publicSettings();
        $meta = [];
        $metaTitle = ($source['seo_title'] ?? '') !== '' ? $source['seo_title'] : $title;
        $metaDescription = ($source['seo_description'] ?? '') !== '' ? $source['seo_description'] : $description;
        if ($metaTitle !== '') {
            $meta['title'] = $metaTitle;
        }
        if ($metaDescription !== '') {
            $meta['description'] = $metaDescription;
        }
        $meta['canonical'] = (string) ($source['canonical_url'] ?? '');
        $meta['ogImage'] = (string) ($source['og_image'] ?? '');
        $meta['noindex'] = isset($source['robots_index']) && (int) $source['robots_index'] === 0;
        return $meta;
    }
}
