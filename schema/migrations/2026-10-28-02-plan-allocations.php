<?php
// v1.0 F2 (Epic #146, Issue #160): Plan-Allokationen (Soll-Stunden-Matrix
// Mitarbeiter x Zeitraum). Portiert aus Pro.
//
// Jeder Eintrag entspricht einer Zelle in der Soll/Ist-Matrix:
//   period_kind='total' + user_id=NULL  -> Projekt-Gesamt-Soll
//   period_kind='week'  + period_start  -> Soll fuer eine KW
//   period_kind='week'  + period_start + user_id -> Soll fuer Mitarbeiter in KW
//
// planned_minutes bleibt bewusst Integer (keine Nachkomma-Minuten).

$pdo    = DB::pdo();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$tableExists = static function (string $t) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $s = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $s->execute([$t]);
    } else {
        $s = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$t]);
    }
    return (bool) $s->fetchColumn();
};

if (!$tableExists('plan_allocations')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE plan_allocations (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id      INTEGER NOT NULL,
                user_id         INTEGER NULL,
                period_kind     TEXT    NOT NULL DEFAULT "total",
                period_start    TEXT    NULL,
                planned_minutes INTEGER NOT NULL DEFAULT 0,
                note            TEXT    NULL,
                created_at      TEXT    NOT NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_plan_project       ON plan_allocations (project_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_plan_project_week  ON plan_allocations (project_id, period_kind, period_start)');
    } else {
        $pdo->exec(
            'CREATE TABLE plan_allocations (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                project_id      INT NOT NULL,
                user_id         INT NULL,
                period_kind     VARCHAR(16) NOT NULL DEFAULT "total",
                period_start    DATE NULL,
                planned_minutes INT NOT NULL DEFAULT 0,
                note            VARCHAR(255) NULL,
                created_at      DATETIME NOT NULL,
                INDEX idx_plan_project       (project_id),
                INDEX idx_plan_project_week  (project_id, period_kind, period_start),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
