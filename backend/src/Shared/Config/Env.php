<?php

declare(strict_types=1);

namespace App\Shared\Config;

/**
 * Cienki odczyt zmiennych srodowiskowych na potrzeby plikow konfiguracyjnych.
 *
 * Aplikacyjne flagi (APP_ENV, APP_DEBUG) obsluguje {@see \App\Environment};
 * ta klasa dotyczy parametrow infrastrukturalnych Solidusa (baza, JWT).
 */
final class Env
{
    public static function string(string $key, string $default = ''): string
    {
        $value = self::raw($key);

        return $value === null || $value === '' ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::raw($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = self::raw($key);

        return $value === null || $value === ''
            ? $default
            : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }

    private static function raw(string $key): ?string
    {
        $value = getenv($key, true);
        if ($value !== false) {
            return $value;
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
