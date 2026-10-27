<?php

namespace Moves\Core;

final class Config
{
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function has(string $key): bool
    {
        return getenv($key) !== false;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }

    public static function isDebug(): bool
    {
        return filter_var(
            self::get('APP_DEBUG', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
