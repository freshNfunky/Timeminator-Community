<?php
// v1.0 F5 (Epic #146): Abwesenheiten — Urlaub (ganztaegig), Krankheit
// (ganztaegig), Ueberstundenabbau (stundenweise). Portiert aus Pro
// Migrations 0018 + 0048.
//
// Standard-Kategorien tragen eine `account`-Markierung (vacation / sick /
// overtime), die F1 (Mitarbeiter-Konten) spaeter nutzt um die
// Resturlaubs- und Ueberstunden-Salden fortzuschreiben. Fuer F5 ist das
// nur ein Hint — Community zeigt die Account-Zuordnung an, bucht aber
// (noch) nichts automatisch auf ein Konto.
//
// `absences.hours` ist nur fuer Konto 'overtime' sinnvoll (Ueberstunden-
// abbau stundenweise innerhalb eines Tages); Urlaub/Krankheit bleiben
// ganztaegig (hours = NULL).

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
$columnExists = static function (string $t, string $c) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info($t)") as $row) {
            if (($row['name'] ?? '') === $c) { return true; }
        }
        return false;
    }
    $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $s->execute([$t, $c]);
    return (bool) $s->fetchColumn();
};

if (!$tableExists('absence_categories')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE absence_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ckey TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                account TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE absence_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ckey VARCHAR(64) NOT NULL UNIQUE,
                label VARCHAR(191) NOT NULL,
                position INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                account VARCHAR(16) NULL,
                created_at DATETIME NOT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
} else {
    // existing table — ensure account column (added in Pro 0047 nachtraeglich)
    if (!$columnExists('absence_categories', 'account')) {
        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE absence_categories ADD COLUMN account TEXT NULL');
        } else {
            $pdo->exec('ALTER TABLE absence_categories ADD COLUMN account VARCHAR(16) NULL');
        }
    }
}

if (!$tableExists('absences')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE absences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                note TEXT NULL,
                hours REAL NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES absence_categories(id)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absences_user ON absences (user_id, start_date, end_date)');
    } else {
        $pdo->exec(
            'CREATE TABLE absences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                category_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                note VARCHAR(255) NULL,
                hours DECIMAL(4,2) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_absences_user (user_id, start_date, end_date),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES absence_categories(id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
} else {
    if (!$columnExists('absences', 'hours')) {
        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE absences ADD COLUMN hours REAL NULL');
        } else {
            $pdo->exec('ALTER TABLE absences ADD COLUMN hours DECIMAL(4,2) NULL');
        }
    }
}

$now = date('Y-m-d H:i:s');
$defaults = [
    ['urlaub',       'Urlaub',                   10, 'vacation'],
    ['krankheit',    'Ausfall wegen Krankheit',  20, 'sick'],
    ['ueberstunden', 'Ueberstundenabbau',        30, 'overtime'],
];
$check = $pdo->prepare('SELECT COUNT(*) FROM absence_categories WHERE ckey = ?');
$ins   = $pdo->prepare('INSERT INTO absence_categories (ckey, label, position, active, account, created_at) VALUES (?,?,?,1,?,?)');
foreach ($defaults as [$ckey, $label, $pos, $account]) {
    $check->execute([$ckey]);
    if ((int) $check->fetchColumn() === 0) {
        $ins->execute([$ckey, $label, $pos, $account, $now]);
    }
}
