<?php
declare(strict_types=1);

/**
 * Sentiment Mood + Flow split (#122, v0.9.1 hotfix).
 *
 * Splits the single `score` into two independent, both-nullable 1..5
 * scalars so a developer can log mood and work-flow separately. See
 * issue #122 for the motivation.
 *
 * This migration:
 *   1. Renames the existing `score` to `mood` (keeps existing data —
 *      the old score was in effect a Mood value).
 *   2. Adds a nullable `flow` column.
 *   3. Makes `mood` NULL-able so a user who only logs Flow doesn't trip
 *      the former NOT NULL constraint.
 *
 * Idempotent + portable:
 *   - SQLite: a nullable-rebuild of the table (copy-rename-drop) since
 *     SQLite has no ALTER COLUMN DROP NOT NULL. Guarded by `columnExists`
 *     so a fresh install (whose base schema already carries the final
 *     shape) no-ops.
 *   - MySQL 8.0+: direct ALTER with MODIFY COLUMN.
 */

$pdo    = DB::pdo();
$driver = DB::driver();

$columnExists = static function (string $table, string $column) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $column) { return true; }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
};

$columnIsNullable = static function (string $table, string $column) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $column) {
                return ((int) ($r['notnull'] ?? 0)) === 0;
            }
        }
        return true;
    }
    $stmt = $pdo->prepare(
        "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return strtoupper((string) $stmt->fetchColumn()) === 'YES';
};

// Step 1: rename score -> mood.
if ($columnExists('sentiment_entries', 'score') && !$columnExists('sentiment_entries', 'mood')) {
    $pdo->exec('ALTER TABLE sentiment_entries RENAME COLUMN score TO mood');
}

// Step 2: add nullable flow column.
if (!$columnExists('sentiment_entries', 'flow')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE sentiment_entries ADD COLUMN flow INTEGER NULL');
    } else {
        $pdo->exec('ALTER TABLE sentiment_entries ADD COLUMN flow TINYINT NULL');
    }
}

// Step 3: drop NOT NULL on mood so users can log only Flow.
if ($columnExists('sentiment_entries', 'mood') && !$columnIsNullable('sentiment_entries', 'mood')) {
    if ($driver === 'sqlite') {
        // SQLite can't ALTER COLUMN DROP NOT NULL — rebuild the table in
        // a single transaction. The UNIQUE(user_id, as_of) + FK are
        // reinstated on the new table so UPSERT and cascading delete
        // keep working.
        $pdo->exec('BEGIN');
        try {
            $pdo->exec(
                'CREATE TABLE sentiment_entries_new (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    INTEGER NOT NULL,
                    as_of      TEXT    NOT NULL,
                    mood       INTEGER NULL,
                    flow       INTEGER NULL,
                    note       TEXT    NULL,
                    created_at TEXT    NOT NULL,
                    updated_at TEXT    NULL,
                    UNIQUE (user_id, as_of),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )'
            );
            $pdo->exec(
                'INSERT INTO sentiment_entries_new
                    (id, user_id, as_of, mood, flow, note, created_at, updated_at)
                 SELECT id, user_id, as_of, mood, flow, note, created_at, updated_at
                   FROM sentiment_entries'
            );
            $pdo->exec('DROP TABLE sentiment_entries');
            $pdo->exec('ALTER TABLE sentiment_entries_new RENAME TO sentiment_entries');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sentiment_as_of ON sentiment_entries (as_of)');
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    } else {
        $pdo->exec('ALTER TABLE sentiment_entries MODIFY COLUMN mood TINYINT NULL');
    }
}
