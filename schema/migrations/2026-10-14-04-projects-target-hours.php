<?php
declare(strict_types=1);

/**
 * Pro-Sync B.2 (#111) — project-level target hours.
 *
 * A single scalar per project: how many hours the project is scoped for.
 * No weekly / monthly / per-person breakdown here; those stay Pro. The
 * dashboard Planning tile and future reports compare this value against
 * recorded `time_entries.duration_min`.
 *
 * Portable + idempotent across SQLite and MySQL. On fresh installs the
 * base schema already carries the column, so this migration is a no-op.
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

if (!$columnExists('projects', 'target_hours')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE projects ADD COLUMN target_hours REAL NULL');
    } else {
        $pdo->exec('ALTER TABLE projects ADD COLUMN target_hours DECIMAL(10,2) NULL');
    }
}
