<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use Moves\Support\HtmlSanitizer;

/** Páginas, artigos, categorias e FAQ. Reaproveita posts, categories e faqs. */
final class CmsContent
{
    public const POST_TYPES = ['article' => 'Artigo', 'news' => 'Notícia', 'institutional' => 'Institucional', 'help' => 'Ajuda'];
    /** Caminhos já usados por rotas do site; não podem virar página dinâmica por engano. */
    private const RESERVED = ['blog', 'entrar', 'sair', 'cadastrar', 'recuperar-senha', 'redefinir-senha', 'minha-conta', 'confirmar-email', 'configuracao-inicial', 'planos', 'assets', 'suporte', 'sitemap.xml', 'robots.txt'];
    /** Páginas com template próprio cujo conteúdo o CMS pode substituir ao publicar. */
    public const TEMPLATE_PAGES = ['sobre', 'solucoes', 'contato', 'termos', 'privacidade'];

    // ---- Páginas ----
    public static function pages(array $filters, int $page): array
    {
        [$where, $args] = self::filter($filters, ['title', 'slug']);
        return CmsQuery::page('FROM cms_pages WHERE ' . $where, '*', 'updated_at DESC, id DESC', $args, $page);
    }

    public static function page(int $id): ?array
    {
        return CmsQuery::one('SELECT * FROM cms_pages WHERE id = ?', [$id]);
    }

    public static function revisions(int $pageId): array
    {
        return CmsQuery::all(
            'SELECT r.id, r.note, r.created_at, u.email AS author FROM cms_page_revisions r LEFT JOIN users u ON u.id = r.created_by WHERE r.page_id = ? ORDER BY r.id DESC LIMIT 30',
            [$pageId]
        );
    }

    public static function savePage(?int $id, array $input, int $actorId): int
    {
        $title = self::text($input['title'] ?? '', 190, 'Título');
        if ($title === '') {
            throw new InvalidArgumentException('Informe o título.');
        }
        $slug = str_slug(is_string($input['slug'] ?? null) && trim($input['slug']) !== '' ? $input['slug'] : $title);
        if ($slug === '' || strlen($slug) > 160 || in_array($slug, self::RESERVED, true)) {
            throw new InvalidArgumentException('Endereço (slug) inválido ou reservado.');
        }
        if (CmsQuery::value('SELECT COUNT(*) FROM cms_pages WHERE slug = ? AND id <> ?', [$slug, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Já existe uma página com este endereço.');
        }
        $data = [$slug, $title, self::text($input['excerpt'] ?? '', 500, 'Resumo'),
            HtmlSanitizer::clean(is_string($input['content'] ?? null) ? $input['content'] : ''),
            self::mediaPath($input['image_path'] ?? ''), ...self::seoValues($input), $actorId];
        return CmsQuery::transaction(static function () use ($id, $data, $actorId): int {
            if ($id === null) {
                CmsQuery::run(
                    'INSERT INTO cms_pages (slug,title,excerpt,content,image_path,seo_title,seo_description,canonical_url,og_image,robots_index,updated_by,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [...$data, $actorId]
                );
                $id = (int) \MovesCode\Model\Connection::get()->lastInsertId();
                CmsAudit::record($actorId, 'page.created', 'page', (string) $id, ['slug' => $data[0]]);
            } elseif (CmsQuery::run(
                'UPDATE cms_pages SET slug=?,title=?,excerpt=?,content=?,image_path=?,seo_title=?,seo_description=?,canonical_url=?,og_image=?,robots_index=?,updated_by=? WHERE id=?',
                [...$data, $id]
            ) === 0 && self::page($id) === null) {
                throw new InvalidArgumentException('Página não encontrada.');
            } else {
                CmsAudit::record($actorId, 'page.updated', 'page', (string) $id, ['slug' => $data[0]]);
            }
            self::revision($id, 'Edição', $actorId);
            return $id;
        });
    }

    public static function setPageStatus(int $id, bool $publish, int $actorId): void
    {
        $page = self::page($id);
        if ($page === null) {
            throw new InvalidArgumentException('Página não encontrada.');
        }
        if ($publish && trim($page['content']) === '') {
            throw new InvalidArgumentException('Escreva o conteúdo antes de publicar.');
        }
        CmsQuery::run(
            'UPDATE cms_pages SET status=?, published_at=IF(?=1, COALESCE(published_at,NOW()), published_at), updated_by=? WHERE id=?',
            [$publish ? 'published' : 'draft', $publish ? 1 : 0, $actorId, $id]
        );
        self::revision($id, $publish ? 'Publicação' : 'Despublicação', $actorId);
        CmsAudit::record($actorId, $publish ? 'page.published' : 'page.unpublished', 'page', (string) $id, ['slug' => $page['slug']]);
    }

    public static function restoreRevision(int $pageId, int $revisionId, int $actorId): void
    {
        $revision = CmsQuery::one('SELECT snapshot_json FROM cms_page_revisions WHERE id=? AND page_id=?', [$revisionId, $pageId]);
        if ($revision === null) {
            throw new InvalidArgumentException('Versão não encontrada.');
        }
        $snap = json_decode($revision['snapshot_json'], true, 16, JSON_THROW_ON_ERROR);
        CmsQuery::run('UPDATE cms_pages SET title=?,excerpt=?,content=?,updated_by=? WHERE id=?', [$snap['title'], $snap['excerpt'], $snap['content'], $actorId, $pageId]);
        self::revision($pageId, 'Restauração da versão ' . $revisionId, $actorId);
        CmsAudit::record($actorId, 'page.restored', 'page', (string) $pageId, ['revision' => $revisionId]);
    }

    private static function revision(int $id, string $note, int $actorId): void
    {
        $page = self::page($id);
        CmsQuery::run('INSERT INTO cms_page_revisions (page_id,snapshot_json,note,created_by) VALUES (?,?,?,?)', [
            $id, json_encode(array_intersect_key($page, array_flip(['slug', 'title', 'excerpt', 'content', 'status'])), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $note, $actorId,
        ]);
    }

    // ---- Artigos, notícias e conteúdos institucionais ----
    public static function posts(array $filters, int $page): array
    {
        [$where, $args] = self::filter($filters, ['p.title', 'p.uri'], 'p.status');
        $where .= ' AND p.deleted_at IS NULL';
        if (isset(self::POST_TYPES[$filters['type'] ?? ''])) {
            $where .= ' AND p.post_type = ?';
            $args[] = $filters['type'];
        }
        if ((int) ($filters['category'] ?? 0) > 0) {
            $where .= ' AND p.category = ?';
            $args[] = (int) $filters['category'];
        }
        return CmsQuery::page('FROM posts p LEFT JOIN categories c ON c.id = p.category WHERE ' . $where, 'p.*, c.title AS category_title', 'p.updated_at DESC, p.id DESC', $args, $page);
    }

    public static function post(int $id): ?array
    {
        return CmsQuery::one('SELECT * FROM posts WHERE id=? AND deleted_at IS NULL', [$id]);
    }

    public static function savePost(?int $id, array $input, int $actorId): int
    {
        $title = self::text($input['title'] ?? '', 140, 'Título');
        $type = is_string($input['post_type'] ?? null) && isset(self::POST_TYPES[$input['post_type']]) ? $input['post_type'] : 'article';
        if ($title === '') {
            throw new InvalidArgumentException('Informe o título.');
        }
        $slug = str_slug(is_string($input['uri'] ?? null) && trim($input['uri']) !== '' ? $input['uri'] : $title);
        if ($slug === '' || CmsQuery::value('SELECT COUNT(*) FROM posts WHERE uri=? AND id<>?', [$slug, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Endereço inválido ou já utilizado.');
        }
        $category = filter_var($input['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($category !== null && CmsQuery::value('SELECT COUNT(*) FROM categories WHERE id=?', [$category]) == 0) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
        $author = filter_var($input['author'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: $actorId;
        if (CmsQuery::value("SELECT COUNT(*) FROM cms_admins WHERE user_id=? AND status='active'", [$author]) == 0) {
            throw new InvalidArgumentException('Autor inválido.');
        }
        $postAt = self::dateTime($input['post_at'] ?? '');
        $video = self::youtubeId($input['video'] ?? '');
        $extra = [$author, $video, $postAt];
        $values = [$category, $type, $title, $slug, self::text($input['subtitle'] ?? '', 300, 'Resumo'),
            HtmlSanitizer::clean(is_string($input['content'] ?? null) ? $input['content'] : ''),
            self::mediaPath($input['cover'] ?? ''), ...self::seoValues($input)];
        if ($id === null) {
            CmsQuery::run('INSERT INTO posts (category,post_type,title,uri,subtitle,content,cover,seo_title,seo_description,canonical_url,og_image,robots_index,author,video,post_at,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE(?,NOW()),\'draft\')', [...$values, ...$extra]);
            $id = (int) \MovesCode\Model\Connection::get()->lastInsertId();
            CmsAudit::record($actorId, 'content.created', 'post', (string) $id, ['uri' => $slug]);
            return $id;
        }
        if (self::post($id) === null) {
            throw new InvalidArgumentException('Conteúdo não encontrado.');
        }
        CmsQuery::run('UPDATE posts SET category=?,post_type=?,title=?,uri=?,subtitle=?,content=?,cover=?,seo_title=?,seo_description=?,canonical_url=?,og_image=?,robots_index=?,author=?,video=?,post_at=COALESCE(?,post_at) WHERE id=?', [...$values, ...$extra, $id]);
        CmsAudit::record($actorId, 'content.updated', 'post', (string) $id, ['uri' => $slug]);
        return $id;
    }

    public static function setPostStatus(int $id, bool $publish, int $actorId): void
    {
        $post = self::post($id);
        if ($post === null) {
            throw new InvalidArgumentException('Conteúdo não encontrado.');
        }
        if ($publish && trim($post['content']) === '') {
            throw new InvalidArgumentException('Escreva o conteúdo antes de publicar.');
        }
        // Data futura fica como agendamento: o site só mostra quando ela chegar.
        CmsQuery::run('UPDATE posts SET status=?, post_at=IF(?=1 AND post_at<=NOW(),NOW(),post_at) WHERE id=?', [$publish ? 'published' : 'draft', $publish && $post['status'] !== 'published' ? 1 : 0, $id]);
        CmsAudit::record($actorId, $publish ? 'content.published' : 'content.unpublished', 'post', (string) $id, ['uri' => $post['uri']]);
    }

    public static function authors(): array
    {
        return CmsQuery::all("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name FROM cms_admins a JOIN users u ON u.id=a.user_id WHERE a.status='active' ORDER BY name");
    }

    private static function dateTime(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        if ($date === false || $date->format('Y-m-d\TH:i') !== $value) {
            throw new InvalidArgumentException('Data de publicação inválida.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    /** Aceita endereço do YouTube ou o ID do vídeo e guarda somente o ID. */
    public static function youtubeId(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        if (preg_match('~^(?:https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtube-nocookie\.com/embed/|youtu\.be/))?([A-Za-z0-9_-]{11})(?:[?&#].*)?$~', $value, $match) !== 1) {
            throw new InvalidArgumentException('Informe um endereço ou ID válido do YouTube.');
        }
        return $match[1];
    }

    public static function deletePost(int $id, int $actorId): void
    {
        if (CmsQuery::run('UPDATE posts SET deleted_at=NOW(), status=\'draft\' WHERE id=? AND deleted_at IS NULL', [$id]) === 0) {
            throw new InvalidArgumentException('Conteúdo não encontrado.');
        }
        CmsAudit::record($actorId, 'content.deleted', 'post', (string) $id);
    }

    // ---- Categorias e FAQ ----
    public static function categories(): array
    {
        return CmsQuery::all("SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category=c.id AND p.deleted_at IS NULL) AS posts_count FROM categories c WHERE c.type IN ('post','help') ORDER BY c.type, c.title");
    }

    public static function saveCategory(?int $id, array $input, int $actorId): void
    {
        $title = self::text($input['title'] ?? '', 120, 'Nome');
        $uri = str_slug($title);
        if ($uri === '' || CmsQuery::value('SELECT COUNT(*) FROM categories WHERE uri=? AND id<>?', [$uri, $id ?? 0]) > 0) {
            throw new InvalidArgumentException('Nome de categoria vazio ou repetido.');
        }
        $description = self::text($input['description'] ?? '', 500, 'Descrição');
        $kind = ($input['type'] ?? '') === 'help' ? 'help' : 'post';
        if ($id === null) {
            CmsQuery::run('INSERT INTO categories (title,uri,description,type) VALUES (?,?,?,?)', [$title, $uri, $description, $kind]);
        } else {
            CmsQuery::run('UPDATE categories SET title=?,uri=?,description=? WHERE id=?', [$title, $uri, $description, $id]);
        }
        CmsAudit::record($actorId, 'content.category_saved', 'category', $id === null ? null : (string) $id, ['title' => $title]);
    }

    public static function deleteCategory(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM categories WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'content.category_deleted', 'category', (string) $id);
    }

    /** Base de conhecimento: matérias com visualizações e avaliação dos leitores. */
    public static function helpListing(array $filters, int $page): array
    {
        $where = "p.post_type='help' AND p.deleted_at IS NULL";
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND p.title LIKE ?';
            $args[] = '%' . $filters['q'] . '%';
        }
        return CmsQuery::page(
            'FROM posts p LEFT JOIN categories c ON c.id=p.category WHERE ' . $where,
            'p.id,p.title,p.status,p.views,c.title AS folder,(SELECT COUNT(*) FROM help_article_votes v WHERE v.post_id=p.id) AS votes,(SELECT SUM(v.helpful) FROM help_article_votes v WHERE v.post_id=p.id) AS helpful',
            'c.title, p.title', $args, $page
        );
    }

    public static function helpFolders(): array
    {
        return CmsQuery::all("SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category=c.id AND p.deleted_at IS NULL) AS posts_count FROM categories c WHERE c.type='help' ORDER BY c.title");
    }

    public static function voteHelp(int $postId, int $userId, bool $helpful): void
    {
        CmsQuery::run('INSERT INTO help_article_votes (post_id,user_id,helpful) VALUES (?,?,?) ON DUPLICATE KEY UPDATE helpful=VALUES(helpful)', [$postId, $userId, $helpful ? 1 : 0]);
    }

    public static function faqs(): array
    {
        return CmsQuery::all('SELECT * FROM faqs ORDER BY sort_order, id');
    }

    public static function saveFaq(?int $id, array $input, int $actorId): void
    {
        $question = self::text($input['question'] ?? '', 255, 'Pergunta');
        $answer = self::text($input['answer'] ?? '', 4000, 'Resposta');
        if ($question === '' || $answer === '') {
            throw new InvalidArgumentException('Preencha pergunta e resposta.');
        }
        $order = max(0, (int) ($input['sort_order'] ?? 0));
        $status = ($input['status'] ?? '') === 'published' ? 'published' : 'draft';
        if ($id === null) {
            CmsQuery::run('INSERT INTO faqs (question,answer,sort_order,status) VALUES (?,?,?,?)', [$question, $answer, $order, $status]);
        } else {
            CmsQuery::run('UPDATE faqs SET question=?,answer=?,sort_order=?,status=? WHERE id=?', [$question, $answer, $order, $status, $id]);
        }
        CmsAudit::record($actorId, 'content.faq_saved', 'faq', $id === null ? null : (string) $id);
    }

    public static function deleteFaq(int $id, int $actorId): void
    {
        CmsQuery::run('DELETE FROM faqs WHERE id=?', [$id]);
        CmsAudit::record($actorId, 'content.faq_deleted', 'faq', (string) $id);
    }

    // ---- Comuns ----
    public static function seoValues(array $input): array
    {
        $canonical = self::text($input['canonical_url'] ?? '', 255, 'URL canônica');
        if ($canonical !== '' && !(filter_var($canonical, FILTER_VALIDATE_URL) && str_starts_with($canonical, 'https://'))) {
            throw new InvalidArgumentException('A URL canônica precisa ser https.');
        }
        return [
            self::text($input['seo_title'] ?? '', 190, 'Título SEO'),
            self::text($input['seo_description'] ?? '', 320, 'Descrição SEO'),
            $canonical,
            (string) self::mediaPath($input['og_image'] ?? ''),
            ($input['robots_index'] ?? '1') === '0' ? 0 : 1,
        ];
    }

    /** Imagens só podem apontar para a biblioteca pública. */
    public static function mediaPath(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        if (preg_match('~^/uploads/cms/[A-Za-z0-9/_.-]+$~', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException('Selecione a imagem pela biblioteca de mídia.');
        }
        return $value;
    }

    public static function text(mixed $value, int $max, string $label): string
    {
        $value = is_string($value) ? trim(str_replace("\0", '', $value)) : '';
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . ': máximo de ' . $max . ' caracteres.');
        }
        return $value;
    }

    private static function filter(array $filters, array $columns, string $statusColumn = 'status'): array
    {
        $where = ['1=1'];
        $args = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(' . implode(' OR ', array_map(static fn ($c) => $c . ' LIKE ?', $columns)) . ')';
            foreach ($columns as $ignored) {
                $args[] = '%' . $filters['q'] . '%';
            }
        }
        if (in_array($filters['status'] ?? '', ['draft', 'published'], true)) {
            $where[] = $statusColumn . ' = ?';
            $args[] = $filters['status'];
        }
        return [implode(' AND ', $where), $args];
    }
}
