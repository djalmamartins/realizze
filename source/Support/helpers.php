<?php

/**
 * Helpers públicos do Realizze 2.0.
 * Funções legadas foram selecionadas e adaptadas; não usar constantes CONF_*.
 */

if (!function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $string): string
    {
        $string = mb_strtolower(trim($string), 'UTF-8');
        if (class_exists(\\Transliterator::class)) {
            $transliterated = \\Transliterator::transliterate('Any-Latin; Latin-ASCII', $string);
            if ($transliterated !== null) {
                $string = $transliterated;
            }
        } else {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
            if ($transliterated !== false) {
                $string = $transliterated;
            }
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $string) ?? '';
        return trim(strtolower($slug), '-');
    }
}

if (!function_exists('str_studly_case')) {
    function str_studly_case(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', str_slug($string))));
    }
}

if (!function_exists('str_camel_case')) {
    function str_camel_case(string $string): string
    {
        return lcfirst(str_studly_case($string));
    }
}

if (!function_exists('str_limit_words')) {
    function str_limit_words(string $string, int $limit, string $pointer = '...'): string
    {
        if ($limit <= 0) {
            return '';
        }
        $words = preg_split('/\\s+/u', trim($string), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count($words) <= $limit ? implode(' ', $words) : implode(' ', array_slice($words, 0, $limit)) . $pointer;
    }
}

if (!function_exists('str_limit_chars')) {
    function str_limit_chars(string $string, int $limit, string $pointer = '...'): string
    {
        if ($limit <= 0) {
            return '';
        }
        return mb_strlen($string, 'UTF-8') <= $limit
            ? $string
            : mb_substr($string, 0, $limit, 'UTF-8') . $pointer;
    }
}

if (!function_exists('str_price')) {
    function str_price(int|float|string|null $price): string
    {
        if ($price === null || $price === '') {
            $price = 0;
        }
        if (!is_numeric($price)) {
            throw new \\InvalidArgumentException('Preço inválido.');
        }
        return number_format((float) $price, 2, ',', '.');
    }
}

if (!function_exists('url')) {
    function url(?string $path = null): string
    {
        $base = rtrim((string) (getenv('APP_URL') ?: 'https://www.realizze.lab'), '/');
        if ($path === null || $path === '') {
            return $base;
        }
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $path) || str_starts_with($path, '//')) {
            throw new \\InvalidArgumentException('URL externa não permitida.');
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('date_fmt')) {
    function date_fmt(?string $date, string $format = 'd/m/Y H\\hi'): string
    {
        return (new \\DateTimeImmutable($date ?: 'now'))->format($format);
    }
}

if (!function_exists('passwd_verify')) {
    function passwd_verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists('passwd_rehash')) {
    function passwd_rehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
