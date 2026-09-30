<?php
declare(strict_types=1);

/**
 * Shared test helpers.
 *
 * `bootInMemoryDb()` gives a test its own fresh in-memory SQLite database
 * with the full application schema applied. The DB class is a singleton, so
 * calling this again inside the same PHP process replaces the previous
 * connection — good for a per-test fixture.
 */
final class TestSupport
{
    public static function bootInMemoryDb(): void
    {
        // DB::boot is idempotent by design, so tests need to force a fresh
        // connection between tests. There is no public reset on the DB class
        // (production has no reason to reset), so we null the singleton via
        // reflection — a test-only hack, explicitly scoped to test setUp.
        $prop = new ReflectionProperty(DB::class, 'pdo');
        $prop->setValue(null, null);

        $config = ['db_driver' => 'sqlite', 'sqlite' => ['path' => ':memory:']];
        DB::boot($config);
        DB::applySqlFile(APP_ROOT . '/schema/sqlite.sql');
    }

    /**
     * Seed a bare "user" needed for foreign-key satisfaction in some tests.
     * Returns the id.
     */
    public static function seedUser(string $username = 'tester'): int
    {
        return DB::insert(
            'INSERT INTO users (username, display_name, password_hash, role_id, active, created_at)
             VALUES (?, ?, ?, NULL, 1, ?)',
            [$username, ucfirst($username), password_hash('irrelevant', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]
        );
    }

    /**
     * Reset the Settings cache and delete data/settings.json, so a test
     * starts from a clean state and does not leak into siblings. Settings
     * is a static singleton in production; tests need to poke it directly.
     */
    public static function resetSettings(): void
    {
        $prop = new ReflectionProperty(Settings::class, 'cache');
        $prop->setValue(null, null);
        $file = APP_ROOT . '/data/settings.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
