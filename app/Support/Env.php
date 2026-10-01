<?php
declare(strict_types=1);

namespace Suite\Support;

final class Env
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (is_file($file)) {
            $parsed = parse_ini_file($file, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                self::$values = $parsed;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $server = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
        if ($server !== false && $server !== null && $server !== '') {
            return (string) $server;
        }

        $value = self::$values[$key] ?? $default;
        return $value === null ? null : (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }
}
