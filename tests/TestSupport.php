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

    /**
     * Felix-Feedback 2026-11-08 (#108/#105-#107): simulate a logged-in user
     * with a specific default-role. Seeds default role catalog + permissions,
     * creates a user with that role and marks them as logged-in on the Auth
     * singleton. Returns the user id.
     */
    public static function loginAsRole(string $roleName, ?string $username = null): int
    {
        if (!function_exists('seed_roles')) {
            require_once APP_ROOT . '/src/seed.php';
        }
        seed_roles();
        $username ??= $roleName . '_user';
        $roleId = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', [$roleName]);
        if (!$roleId) {
            throw new RuntimeException("Unknown role: $roleName");
        }
        $uid = DB::insert(
            'INSERT INTO users (username, display_name, password_hash, role_id, active, created_at)
             VALUES (?,?,?,?,1,?)',
            [$username, ucfirst($username), password_hash('x', PASSWORD_DEFAULT), $roleId, date('Y-m-d H:i:s')]
        );
        // Auth::$user shape matches what Auth::boot normally fills.
        $perms = DB::all(
            'SELECT p.code FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?',
            [$roleId]
        );
        $permCodes = array_map(static fn($r) => (string) $r['code'], $perms);
        $prop = new ReflectionProperty(Auth::class, 'user');
        $prop->setValue(null, [
            'id'         => $uid,
            'username'   => $username,
            'display_name' => ucfirst($username),
            'role_id'    => $roleId,
            'role_name'  => $roleName,
            'perms'      => $permCodes,
        ]);
        return $uid;
    }

    /** Render a view partial file with the current request context as if the nav was being built. */
    public static function renderPartial(string $relPath): string
    {
        // Suppress `get()`-based route helpers by seeding $_GET.
        $_GET['r'] = $_GET['r'] ?? 'dashboard';
        ob_start();
        require APP_ROOT . '/' . ltrim($relPath, '/');
        return (string) ob_get_clean();
    }
}
