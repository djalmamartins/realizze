<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

/** Histórico de versões independente por produto. */
final class CmsVersions
{
    public const PRODUCTS = ['web' => ['Site', 'globe'], 'app' => ['App', 'card'], 'cms' => ['CMS', 'settings'], 'erp' => ['ERP', 'building'], 'support' => ['Atendimento', 'chat']];

    public static function current(): array
    {
        $current = [];
        foreach (CmsQuery::all('SELECT v.product, v.version FROM cms_versions v JOIN (SELECT product, MAX(id) AS id FROM cms_versions GROUP BY product) x ON x.id=v.id') as $row) {
            $current[$row['product']] = $row['version'];
        }
        return $current;
    }

    public static function listing(string $product, int $limit = 50): array
    {
        return CmsQuery::all("SELECT v.*, CONCAT(u.first_name,' ',u.last_name) AS author FROM cms_versions v LEFT JOIN users u ON u.id=v.created_by WHERE v.product=? ORDER BY v.id DESC LIMIT " . max(1, min($limit, 200)), [$product]);
    }

    public static function publish(array $input, int $actorId): void
    {
        $product = is_string($input['product'] ?? null) && isset(self::PRODUCTS[$input['product']]) ? $input['product'] : null;
        $version = CmsContent::text($input['version'] ?? '', 30, 'Versão');
        $notes = CmsContent::text($input['notes'] ?? '', 5000, 'Notas');
        if ($product === null || preg_match('/^[0-9A-Za-z][0-9A-Za-z.\-]*$/', $version) !== 1 || $notes === '') {
            throw new InvalidArgumentException('Informe produto, número da versão (ex.: 1.1.0) e notas.');
        }
        if (CmsQuery::value('SELECT COUNT(*) FROM cms_versions WHERE product=? AND version=?', [$product, $version]) > 0) {
            throw new InvalidArgumentException('Esta versão já foi publicada.');
        }
        CmsQuery::run('INSERT INTO cms_versions (product,version,name,notes,created_by) VALUES (?,?,?,?,?)', [$product, $version, CmsContent::text($input['name'] ?? '', 120, 'Nome'), $notes, $actorId]);
        CmsAudit::record($actorId, 'version.published', 'version', $product . ':' . $version);
    }
}
