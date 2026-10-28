<?php
// v1.0 F2 (Epic #146, Issue #160): Terminverschiebungs-Log fuer Projekte
// (und optional Arbeitspakete). Portiert aus Pro.
//
// Jede Verschiebung begruendet ein neues Enddatum mit Reason — das macht
// Delays nachvollziehbar und spielt in den Health-Flag "deadline_overdue"
// hinein (ist das letzte new_end bereits ueberschritten?).

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

if (!$tableExists('date_shifts')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE date_shifts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                kind       TEXT    NOT NULL,
                object_id  INTEGER NOT NULL,
                project_id INTEGER NOT NULL,
                old_end    TEXT    NULL,
                new_end    TEXT    NULL,
                reason     TEXT    NOT NULL,
                user_id    INTEGER NULL,
                created_at TEXT    NOT NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_date_shifts_project ON date_shifts (project_id, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_date_shifts_object  ON date_shifts (kind, object_id, created_at)');
    } else {
        $pdo->exec(
            'CREATE TABLE date_shifts (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                kind       VARCHAR(32) NOT NULL,
                object_id  INT NOT NULL,
                project_id INT NOT NULL,
                old_end    DATE NULL,
                new_end    DATE NULL,
                reason     VARCHAR(500) NOT NULL,
                user_id    INT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_date_shifts_project (project_id, created_at),
                INDEX idx_date_shifts_object  (kind, object_id, created_at),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
