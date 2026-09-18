<?php
declare(strict_types=1);

/**
 * Simple key/value settings persisted as JSON under data/ (protected from the
 * web). Used for runtime-editable, non-secret state: update status, the
 * registration opt-in, etc. Secrets stay in config.php.
 */
final class Settings
{
    private static ?array $cache = null;

    private static function file(): string
    {
        return APP_ROOT . '/data/settings.json';
    }

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $f = self::file();
        self::$cache = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::load()[$key] ?? $default;
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function set(string $key, mixed $value): void
    {
        $data = self::load();
        $data[$key] = $value;
        self::$cache = $data;
        @file_put_contents(self::file(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
