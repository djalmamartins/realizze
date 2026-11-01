<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

/**
 * Catálogo central de instituições financeiras e entrega segura dos logotipos.
 *
 * Os SVGs ficam em storage/bank-logos/ (fora de public/). Só são entregues:
 * - com REALIZZE_BANK_LOGOS_ENABLED=1 (os logos não têm licença aberta; desligado por padrão);
 * - para arquivos aprovados no manifest (status "ok"), fora de _revisar/;
 * - por nome validado, sem caminho vindo do usuário.
 */
final class FinancialInstitutions
{
    private const LOGO_MAX_BYTES = 512000;

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $manifest = null;

    private static ?bool $ready = null;

    /** Indica se a migration 006 (tabela do catálogo e coluna institution_id) já foi aplicada. */
    public static function catalogReady(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $pdo = Connection::get();
            $column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_accounts' AND COLUMN_NAME = 'institution_id'");
            $table = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_institutions'");
            return self::$ready = ((int) $column->fetchColumn() === 1 && (int) $table->fetchColumn() === 1);
        } catch (\Throwable) {
            return self::$ready = false;
        }
    }

    public static function logosEnabled(): bool
    {
        return getenv('REALIZZE_BANK_LOGOS_ENABLED') === '1';
    }

    public static function logoDirectory(): string
    {
        return BASE_PATH . '/storage/bank-logos';
    }

    /** Instituições ativas, as que têm logo primeiro (mais conhecidas), depois por nome. */
    public static function active(): array
    {
        if (!self::catalogReady()) {
            return [];
        }
        return Connection::get()->query(
            "SELECT id, compe_code, ispb, official_name, trade_name, slug, institution_type, logo_file
             FROM financial_institutions WHERE status = 'active'
             ORDER BY logo_file IS NULL, trade_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        if (!self::catalogReady()) {
            return null;
        }
        $statement = Connection::get()->prepare('SELECT * FROM financial_institutions WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Id de uma instituição ativa, ou null se vazio/inválido. */
    public static function activeId(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }
        $row = self::find($id);
        return $row !== null && $row['status'] === 'active' ? $id : null;
    }

    /** Caminho absoluto do SVG aprovado para o slug de logo, ou null. */
    public static function approvedLogoPath(?string $logo): ?string
    {
        if (!is_string($logo) || !preg_match('/^[a-z0-9-]{1,120}$/', $logo)) {
            return null;
        }
        $entry = self::manifest()[$logo] ?? null;
        if ($entry === null || ($entry['status'] ?? '') !== 'ok' || ($entry['file'] ?? '') !== $logo . '.svg') {
            return null;
        }
        $directory = realpath(self::logoDirectory());
        $path = realpath(self::logoDirectory() . '/' . $logo . '.svg');
        if ($directory === false || $path === false || dirname($path) !== $directory || !is_file($path)) {
            return null;
        }
        $size = filesize($path);
        return $size !== false && $size > 0 && $size <= self::LOGO_MAX_BYTES ? $path : null;
    }

    /** URL do logo para <img>, ou null quando deve ser usado o fallback visual. */
    public static function logoUrl(?string $logo): ?string
    {
        if (!self::logosEnabled() || self::approvedLogoPath($logo) === null) {
            return null;
        }
        return '/minha-conta/bancos/logo/' . $logo;
    }

    /** Iniciais para o fallback visual (ex.: "Banco do Brasil" -> "BB"). */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-\.\/]+/u', trim($name)) ?: [];
        $skip = ['de', 'da', 'do', 'das', 'dos', 'e', 's', 'a', 'sa', 'ltda'];
        $letters = '';
        foreach ($words as $word) {
            if ($word === '' || in_array(mb_strtolower($word), $skip, true)) {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
            if (mb_strlen($letters) === 2) {
                break;
            }
        }
        return $letters !== '' ? $letters : '?';
    }

    /** Matiz estável (0-359) para colorir o fallback de cada instituição. */
    public static function hue(string $key): int
    {
        return (int) (hexdec(substr(md5($key), 0, 4)) % 360);
    }

    /** Entrega o SVG com cabeçalhos que impedem execução de conteúdo ativo. */
    public static function serveLogo(string $logo): void
    {
        $path = self::logosEnabled() ? self::approvedLogoPath($logo) : null;
        if ($path === null) {
            http_response_code(404);
            header('Cache-Control: no-store');
            return;
        }
        header('Content-Type: image/svg+xml');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        header('Content-Disposition: inline; filename="' . $logo . '.svg"');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }

    /** @return array<string,array<string,mixed>> */
    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        self::$manifest = [];
        $file = self::logoDirectory() . '/manifest.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        foreach (is_array($data['logos'] ?? null) ? $data['logos'] : [] as $entry) {
            if (is_array($entry) && is_string($entry['slug'] ?? null)) {
                self::$manifest[$entry['slug']] = $entry;
            }
        }
        return self::$manifest;
    }
}
