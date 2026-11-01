<?php
declare(strict_types=1);

namespace Moves\Services;

/**
 * Reconhecimento visual de assinaturas. Somente usa arquivos locais aprovados.
 * Nunca busca imagens em URLs informadas pelo usuário.
 */
final class SubscriptionBrands
{
    private const BRANDS = [
        'netflix' => ['Netflix', ['netflix']],
        'prime-video' => ['Prime Video', ['prime video', 'amazon prime', 'primevideo']],
        'chatgpt' => ['ChatGPT', ['chatgpt', 'chat gpt', 'openai']],
        'spotify' => ['Spotify', ['spotify']],
        'youtube-premium' => ['YouTube Premium', ['youtube premium', 'youtube music']],
        'disney-plus' => ['Disney+', ['disney+', 'disney plus', 'disneyplus']],
        'max' => ['Max', ['hbo max', 'max streaming']],
        'apple-music' => ['Apple Music', ['apple music']],
        'icloud' => ['iCloud+', ['icloud', 'icloud+']],
        'canva' => ['Canva', ['canva']],
        'adobe' => ['Adobe', ['adobe', 'creative cloud']],
        'microsoft-365' => ['Microsoft 365', ['microsoft 365', 'office 365']],
        'globoplay' => ['Globoplay', ['globoplay', 'globo play']],
        'deezer' => ['Deezer', ['deezer']],
        'crunchyroll' => ['Crunchyroll', ['crunchyroll']],
    ];

    public static function identify(string $name, ?string $provider = null): ?string
    {
        $value = mb_strtolower(trim(($provider ?? '') . ' ' . $name), 'UTF-8');
        foreach (self::BRANDS as $slug => $brand) {
            foreach ($brand[1] as $alias) {
                if (preg_match('/(?<![\\pL\\pN])' . preg_quote($alias, '/') . '(?![\\pL\\pN])/iu', $value) === 1) {
                    return $slug;
                }
            }
        }
        return null;
    }

    /** Caminho publicado apenas quando o SVG correspondente realmente existe. */
    public static function logoUrl(string $name, ?string $provider = null): ?string
    {
        $slug = self::identify($name, $provider);
        if ($slug === null) {
            return null;
        }
        $file = BASE_PATH . '/public/assets/realizze-app/v1/images/subscriptions/' . $slug . '.svg';
        return is_file($file) ? '/assets/realizze-app/v1/images/subscriptions/' . $slug . '.svg' : null;
    }

    public static function suggestions(): array
    {
        return array_map(static fn (array $brand): string => $brand[0], self::BRANDS);
    }
}
