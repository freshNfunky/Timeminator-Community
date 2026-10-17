<?php
declare(strict_types=1);

/**
 * Projekt-Tracker: structured sub-fields for status_report (#127, iteration 2).
 *
 * `status` holds the sub-status of a status_report entry
 * (`on_plan` / `delay` / `extension`), `date_value` the new deadline when
 * the entry marks a delay or extension. Both nullable; other tracker types
 * leave them NULL.
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

if (!$columnExists('project_trackers', 'status')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN status TEXT NULL');
    } else {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN status VARCHAR(32) NULL');
    }
}

if (!$columnExists('project_trackers', 'date_value')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN date_value TEXT NULL');
    } else {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN date_value DATE NULL');
    }
}
