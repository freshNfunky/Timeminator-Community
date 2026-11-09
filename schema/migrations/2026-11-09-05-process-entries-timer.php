<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (Fix #186): Dashboard-Timer kann auch
 * Interne Prozesse starten. Dafuer muss process_entries end_ts +
 * duration_min NULL erlauben (analog time_entries fuer den laufenden
 * Timer). Beim Stop werden beide Felder gesetzt.
 *
 * SQLite: ALTER COLUMN gibt es nicht - Tabelle neu bauen mit nullable
 * Spalten und Daten rueberkopieren. MySQL: einfache ALTER TABLE.
 */

$pdo = DB::pdo();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS process_entries_new (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id       INTEGER NOT NULL,
            category_id   INTEGER NOT NULL,
            start_ts      TEXT    NOT NULL,
            end_ts        TEXT    NULL,
            duration_min  INTEGER NULL,
            note          TEXT    NULL,
            created_at    TEXT    NOT NULL,
            updated_at    TEXT    NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES process_categories(id)
        )');
        $pdo->exec('INSERT INTO process_entries_new (id, user_id, category_id, start_ts, end_ts, duration_min, note, created_at, updated_at)
                    SELECT id, user_id, category_id, start_ts, end_ts, duration_min, note, created_at, updated_at FROM process_entries');
        $pdo->exec('DROP TABLE process_entries');
        $pdo->exec('ALTER TABLE process_entries_new RENAME TO process_entries');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_process_entries_user ON process_entries (user_id, start_ts)');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
} else {
    $pdo->exec('ALTER TABLE process_entries MODIFY end_ts DATETIME NULL');
    $pdo->exec('ALTER TABLE process_entries MODIFY duration_min INT NULL');
}
