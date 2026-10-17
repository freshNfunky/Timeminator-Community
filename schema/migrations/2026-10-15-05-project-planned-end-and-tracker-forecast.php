<?php
declare(strict_types=1);

/**
 * v1.0.0 cockpit iteration (#127):
 *   - `projects.planned_end` — the original planned project end date,
 *     rendered as a dashed vertical "Deadline"-line in the cockpit chart.
 *   - `project_trackers.value_forecast` — second progress value for the
 *     "Projektfortschritt" tracker type: the user's 1-month-ahead
 *     estimate, shown alongside the current progress to signal momentum.
 *
 * Portable + idempotent, guarded by columnExists probes.
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

if (!$columnExists('projects', 'planned_end')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE projects ADD COLUMN planned_end TEXT NULL');
    } else {
        $pdo->exec('ALTER TABLE projects ADD COLUMN planned_end DATE NULL');
    }
}

if (!$columnExists('project_trackers', 'value_forecast')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN value_forecast REAL NULL');
    } else {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN value_forecast DECIMAL(10,2) NULL');
    }
}
