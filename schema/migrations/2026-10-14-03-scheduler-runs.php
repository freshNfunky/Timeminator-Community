<?php
declare(strict_types=1);

/**
 * Pro-Sync A.3 (#109) — scheduler state table.
 *
 * Portable + idempotent CREATE TABLE for both engines, so fresh installs
 * (whose base schema already carries the table) and existing installs end
 * up with the same shape.
 */

$pdo    = DB::pdo();
$driver = DB::driver();

if ($driver === 'sqlite') {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS scheduler_runs (
            name              TEXT    PRIMARY KEY,
            last_run_at       TEXT    NULL,
            last_result       TEXT    NULL,
            last_duration_ms  INTEGER NULL,
            last_error        TEXT    NULL
        )'
    );
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS scheduler_runs (
            name              VARCHAR(64) PRIMARY KEY,
            last_run_at       DATETIME    NULL,
            last_result       VARCHAR(32) NULL,
            last_duration_ms  INT         NULL,
            last_error        VARCHAR(512) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}
