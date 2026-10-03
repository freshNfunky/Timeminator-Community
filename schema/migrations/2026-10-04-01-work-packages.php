<?php
declare(strict_types=1);

/**
 * Issue #65 — add the Arbeitspaket (en: Workpackage) layer between
 * projects and tasks. Portable across SQLite and MySQL so one file
 * covers both drivers.
 *
 * Hierarchy after this migration:
 *   clients -> projects -> work_packages -> tasks -> time_entries
 *
 * Idempotent:
 *   - CREATE TABLE IF NOT EXISTS for work_packages (so fresh installs
 *     whose base schema already carries the table just no-op).
 *   - ALTER TABLE ... ADD COLUMN wrapped in a column-exists check so a
 *     fresh install (whose schema master already has the FK columns)
 *     does not re-add them and a legacy install is upgraded in place.
 *   - Backfill UPDATEs only touch rows whose work_package_id IS NULL.
 *
 * Runs inside a single transaction; a partial failure rolls back so an
 * install never ends up with a half-mapped dataset.
 */

$pdo    = DB::pdo();
$driver = DB::driver();
$ts     = date('Y-m-d H:i:s');

/* -------------------- helpers -------------------- */

$columnExists = static function (string $table, string $column) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

/* -------------------- schema -------------------- */

if ($driver === 'sqlite') {
    $pdo->exec('CREATE TABLE IF NOT EXISTS work_packages (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id    INTEGER NOT NULL,
        code          TEXT    NOT NULL,
        name          TEXT    NOT NULL,
        active        INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT    NOT NULL,
        FOREIGN KEY (project_id) REFERENCES projects(id),
        UNIQUE (project_id, code)
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_packages_project_active
        ON work_packages (project_id, active)');
} else {
    $pdo->exec('CREATE TABLE IF NOT EXISTS work_packages (
        id            INT          AUTO_INCREMENT PRIMARY KEY,
        project_id    INT          NOT NULL,
        code          VARCHAR(64)  NOT NULL,
        name          VARCHAR(191) NOT NULL,
        active        TINYINT(1)   NOT NULL DEFAULT 1,
        created_at    DATETIME     NOT NULL,
        UNIQUE KEY uq_wp_project_code (project_id, code),
        KEY idx_wp_project_active (project_id, active),
        FOREIGN KEY (project_id) REFERENCES projects(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

if (!$columnExists('tasks', 'work_package_id')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE tasks ADD COLUMN work_package_id INTEGER NULL REFERENCES work_packages(id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_work_package ON tasks (work_package_id)');
    } else {
        $pdo->exec('ALTER TABLE tasks
            ADD COLUMN work_package_id INT NULL AFTER project_id,
            ADD KEY idx_tasks_work_package (work_package_id),
            ADD CONSTRAINT fk_tasks_work_package
                FOREIGN KEY (work_package_id) REFERENCES work_packages(id)');
    }
}

if (!$columnExists('time_entries', 'work_package_id')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE time_entries ADD COLUMN work_package_id INTEGER NULL REFERENCES work_packages(id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_time_entries_work_package ON time_entries (work_package_id)');
    } else {
        $pdo->exec('ALTER TABLE time_entries
            ADD COLUMN work_package_id INT NULL AFTER project_id,
            ADD KEY idx_time_entries_work_package (work_package_id),
            ADD CONSTRAINT fk_entries_work_package
                FOREIGN KEY (work_package_id) REFERENCES work_packages(id)');
    }
}

/* -------------------- backfill -------------------- */

$pdo->beginTransaction();
try {
    $projects = DB::all('SELECT id FROM projects', []);
    foreach ($projects as $p) {
        $pid = (int) $p['id'];

        $wpId = DB::scalar(
            'SELECT id FROM work_packages WHERE project_id = ? AND code = ?',
            [$pid, 'default']
        );
        if (!$wpId) {
            $wpId = DB::insert(
                'INSERT INTO work_packages (project_id, code, name, active, created_at)
                 VALUES (?, ?, ?, 1, ?)',
                [$pid, 'default', 'Allgemein', $ts]
            );
        }
        $wpId = (int) $wpId;

        DB::run(
            'UPDATE tasks        SET work_package_id = ? WHERE project_id = ? AND work_package_id IS NULL',
            [$wpId, $pid]
        );
        DB::run(
            'UPDATE time_entries SET work_package_id = ? WHERE project_id = ? AND work_package_id IS NULL',
            [$wpId, $pid]
        );
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
