<?php
declare(strict_types=1);

/**
 * Projekt-Tracker — zeitgestempelte Status-Berichte pro Projekt (#127).
 *
 * Replaces the thin "Report"-page from v0.9.0 B.3 with a per-project log
 * of PM status reports inside the Cockpit. A tracker row carries a
 * `type` (vorerst nur "status_report"; weitere Pro-exklusiv), a free-
 * text note, timestamp and the author.
 *
 * Portable + idempotent CREATE TABLE for both engines.
 */

$pdo    = DB::pdo();
$driver = DB::driver();

if ($driver === 'sqlite') {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS project_trackers (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id  INTEGER NOT NULL,
            type        TEXT    NOT NULL DEFAULT "status_report",
            note        TEXT    NOT NULL,
            user_id     INTEGER NULL,
            created_at  TEXT    NOT NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_project_trackers_project ON project_trackers (project_id, created_at)');
} else {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_trackers (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            project_id  INT NOT NULL,
            type        VARCHAR(64) NOT NULL DEFAULT 'status_report',
            note        TEXT NOT NULL,
            user_id     INT NULL,
            created_at  DATETIME NOT NULL,
            KEY idx_project_trackers_project (project_id, created_at),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
