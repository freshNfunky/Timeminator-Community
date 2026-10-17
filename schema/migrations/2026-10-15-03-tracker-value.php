<?php
declare(strict_types=1);

/**
 * Projekt-Tracker: numeric value for progress entries (#127).
 *
 * The `progress` tracker-type carries a 0..100 percentage; other
 * types leave this column NULL. Idempotent + portable.
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

if (!$columnExists('project_trackers', 'value')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN value REAL NULL');
    } else {
        $pdo->exec('ALTER TABLE project_trackers ADD COLUMN value DECIMAL(10,2) NULL');
    }
}
