<?php
declare(strict_types=1);

/**
 * Lightweight schema migrator. Runs pending migrations at boot so existing
 * installations pick up new columns/tables without manual steps. Operations are
 * idempotent (guarded by column checks and CREATE TABLE IF NOT EXISTS), so a
 * fresh install whose base schema already has everything just records them.
 *
 * Delegates the file-walking work to `Updater::runMigrations()`, which already
 * knows how to iterate `schema/migrations/<driver>/YYYY-MM-DD-NN-slug.{sql,php}`
 * and track which basenames are applied. Doing it here on every request means
 * self-hosted installs pick up a new release's data migrations the moment they
 * replace the files on disk, without having to run the in-app updater first.
 */
final class Migrator
{
    public static function run(): void
    {
        self::ensureRegistry();
        // Delegate to the Updater's migration walker. Swallow errors so a
        // broken migration can't take the whole app down — the exception
        // (and its stack) still shows up in the PHP error log for the
        // operator to see.
        try {
            Updater::runMigrations();
        } catch (Throwable $e) {
            error_log('[Migrator] runMigrations failed: ' . $e->getMessage());
        }
    }

    private static function ensureRegistry(): void
    {
        if (DB::driver() === 'mysql') {
            DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(64) PRIMARY KEY, applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        }
    }
}
