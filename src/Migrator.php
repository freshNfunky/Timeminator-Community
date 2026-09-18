<?php
declare(strict_types=1);

/**
 * Lightweight schema migrator. Runs pending migrations at boot so existing
 * installations pick up new columns/tables without manual steps. Operations are
 * idempotent (guarded by column checks and CREATE TABLE IF NOT EXISTS), so a
 * fresh install whose base schema already has everything just records them.
 *
 * Community Edition: schema/mysql.sql and schema/sqlite.sql already contain the
 * full current schema, so there are no pending migrations here. Timeminator Pro
 * (Planung/Report, Angebote, Rechnungen) ships its own migrations on top.
 */
final class Migrator
{
    public static function run(): void
    {
        self::ensureRegistry();
        // No migrations in the Community Edition yet - reserved for future use.
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
