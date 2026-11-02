<?php
declare(strict_types=1);

namespace Moves\Services;

/** Identificação visual de bandeiras, sem inferir a bandeira a partir do banco. */
final class CardBrands
{
    private const BRANDS = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'elo' => 'Elo',
        'american-express' => 'American Express',
        'hipercard' => 'Hipercard',
    ];

    public static function detect(string $name): ?string
    {
        $text = mb_strtolower($name, 'UTF-8');
        foreach ([
            'mastercard' => '/(?<![\\pL\\pN])master\\s?card(?![\\pL\\pN])/iu',
            'visa' => '/(?<![\\pL\\pN])visa(?![\\pL\\pN])/iu',
            'elo' => '/(?<![\\pL\\pN])elo(?![\\pL\\pN])/iu',
            'american-express' => '/(?<![\\pL\\pN])(amex|american express)(?![\\pL\\pN])/iu',
            'hipercard' => '/(?<![\\pL\\pN])hipercard(?![\\pL\\pN])/iu',
        ] as $slug => $pattern) {
            if (preg_match($pattern, $text) === 1) return $slug;
        }
        return null;
    }

    public static function label(string $slug): ?string
    {
        return self::BRANDS[$slug] ?? null;
    }

    public static function logoUrl(string $slug): ?string
    {
        if (!isset(self::BRANDS[$slug])) return null;
        $path = BASE_PATH . '/public/assets/realizze-app/v1/images/card-brands/' . $slug . '.svg';
        return is_file($path) ? '/assets/realizze-app/v1/images/card-brands/' . $slug . '.svg' : null;
    }
}
