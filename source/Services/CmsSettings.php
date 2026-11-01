<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

/** Configurações editáveis. Segredos ficam no ambiente e nunca entram nesta lista. */
final class CmsSettings
{
    public const FIELDS = [
        'company_name' => ['Identidade', 'Nome institucional', 'text'],
        'company_tagline' => ['Identidade', 'Frase institucional', 'text'],
        'contact_email' => ['Contatos', 'E-mail de contato', 'email'],
        'contact_phone' => ['Contatos', 'Telefone', 'text'],
        'support_email' => ['Contatos', 'E-mail de suporte', 'email'],
        'support_whatsapp' => ['Contatos', 'WhatsApp de suporte (somente dígitos, com DDI)', 'text'],
        'support_hours' => ['Contatos', 'Horário de atendimento', 'text'],
        'social_instagram' => ['Redes sociais', 'Instagram', 'url'],
        'social_linkedin' => ['Redes sociais', 'LinkedIn', 'url'],
        'social_facebook' => ['Redes sociais', 'Facebook', 'url'],
        'seo_default_title' => ['SEO padrão', 'Título padrão', 'text'],
        'seo_default_description' => ['SEO padrão', 'Descrição padrão', 'textarea'],
        'public_footer_text' => ['Textos públicos', 'Texto do rodapé', 'textarea'],
        'admin_page_size' => ['Preferências administrativas', 'Itens por página', 'number'],
    ];
    public const NOTIFY_FIELDS = [
        'notify_telegram_enabled' => 'Enviar avisos pelo Telegram',
        'notify_email_enabled' => 'Enviar e-mails aos clientes',
        'notify_internal_urgent_only' => 'Avisar a equipe somente de chamados urgentes',
    ];
    private const PUBLIC_KEYS = ['company_name', 'company_tagline', 'contact_email', 'contact_phone', 'support_email', 'support_whatsapp', 'support_hours', 'social_instagram', 'social_linkedin', 'social_facebook', 'seo_default_title', 'seo_default_description', 'public_footer_text'];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (CmsQuery::all('SELECT setting_key, setting_value FROM cms_settings') as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    public static function publicValues(): array
    {
        return array_intersect_key(self::all(), array_flip(self::PUBLIC_KEYS));
    }

    public static function save(array $input, int $actorId): void
    {
        $changed = [];
        foreach (self::FIELDS as $key => [, $label, $type]) {
            $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');
            if (mb_strlen($value) > 2000) {
                throw new InvalidArgumentException($label . ': texto muito longo.');
            }
            if ($value !== '' && $type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException($label . ': e-mail inválido.');
            }
            if ($value !== '' && $type === 'url' && (!filter_var($value, FILTER_VALIDATE_URL) || !str_starts_with($value, 'https://'))) {
                throw new InvalidArgumentException($label . ': informe um endereço https válido.');
            }
            if ($key === 'support_whatsapp' && $value !== '' && preg_match('/^[0-9]{10,15}$/', $value) !== 1) {
                throw new InvalidArgumentException($label . ': use somente dígitos.');
            }
            if ($value !== '' && $type === 'number' && (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 100]]) === false)) {
                throw new InvalidArgumentException($label . ': use um número de 5 a 100.');
            }
            if (self::get($key) !== $value) {
                $changed[] = $key;
                self::put($key, $value, $actorId);
            }
        }
        if ($changed !== []) {
            CmsAudit::record($actorId, 'settings.updated', 'settings', null, ['fields' => $changed]);
        }
    }

    public static function saveNotifications(array $input, int $actorId): void
    {
        $changes = [];
        foreach (self::NOTIFY_FIELDS as $key => $label) {
            $value = ($input[$key] ?? '') === '1' ? '1' : '0';
            if (self::get($key, '0') !== $value) {
                $changes[$key] = $value;
                self::put($key, $value, $actorId);
            }
        }
        if ($changes !== []) {
            CmsAudit::record($actorId, 'settings.notifications_updated', 'settings', null, $changes);
        }
    }

    public static function enabled(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    private static function put(string $key, string $value, int $actorId): void
    {
        CmsQuery::run(
            'INSERT INTO cms_settings (setting_key, setting_value, updated_by) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$key, $value, $actorId]
        );
        self::$cache = null;
    }
}
