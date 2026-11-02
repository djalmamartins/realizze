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


    /** Cores de identificação para quando a marca não possui arquivo local aprovado. */
    public static function visual(string $name, ?string $provider = null): array
    {
        $slug = self::identify($name, $provider);
        $colors = [
            'netflix' => ['#e50914', '#ffffff', 'N'],
            'prime-video' => ['#0f79af', '#ffffff', 'P'],
            'chatgpt' => ['#106b58', '#ffffff', 'C'],
            'spotify' => ['#1db954', '#09251a', 'S'],
            'youtube-premium' => ['#ff0033', '#ffffff', 'Y'],
            'disney-plus' => ['#142e80', '#ffffff', 'D'],
            'max' => ['#4824b8', '#ffffff', 'M'],
            'apple-music' => ['#e94367', '#ffffff', 'A'],
            'icloud' => ['#287cbb', '#ffffff', 'i'],
            'canva' => ['#6a42b8', '#ffffff', 'C'],
            'adobe' => ['#db202c', '#ffffff', 'A'],
            'microsoft-365' => ['#d55b21', '#ffffff', 'M'],
            'globoplay' => ['#e64024', '#ffffff', 'G'],
            'deezer' => ['#5840a5', '#ffffff', 'D'],
            'crunchyroll' => ['#f47521', '#ffffff', 'C'],
        ];
        return $colors[$slug ?? ''] ?? ['#e7f5ed', '#06703d', mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: '?'];
    }

    public static function suggestions(): array
    {
        return array_map(static fn (array $brand): string => $brand[0], self::BRANDS);
    }
}
