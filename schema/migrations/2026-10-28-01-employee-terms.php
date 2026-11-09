<?php
// v1.0 F1 (Epic #146, Issue #158): Mitarbeiter-Vertragsdaten + Jahresuebertraege
//
// `employee_terms`: historisierte Vertragsstaende (Beschaeftigungsart,
// Wochenarbeitszeit, Urlaubsanspruch, Feiertagsregion) ab einem Stichtag.
// Erlaubt Vertragsaenderungen ohne vergangene Konten zu verfaelschen.
//
// `account_carryovers`: pro User und Jahr Resturlaub + Ueberstundensaldo aus
// dem Vorjahr. Ein Jahresabschluss schreibt die Werte, sonst leer.
//
// Portiert aus Pro Migrationen 0028 + 0030.

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

if (!$tableExists('employee_terms')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE employee_terms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                valid_from TEXT NOT NULL,
                employment_type TEXT NOT NULL,
                weekly_hours REAL NOT NULL DEFAULT 40,
                vacation_days REAL NOT NULL DEFAULT 25,
                holiday_region TEXT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_employee_terms_user_from ON employee_terms (user_id, valid_from)');
    } else {
        $pdo->exec(
            'CREATE TABLE employee_terms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                valid_from DATE NOT NULL,
                employment_type VARCHAR(16) NOT NULL,
                weekly_hours DECIMAL(5,2) NOT NULL DEFAULT 40,
                vacation_days DECIMAL(5,2) NOT NULL DEFAULT 25,
                holiday_region VARCHAR(32) NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_employee_terms_user_from (user_id, valid_from),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

if (!$tableExists('account_carryovers')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE account_carryovers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                year INTEGER NOT NULL,
                vacation_days REAL NOT NULL DEFAULT 0,
                overtime_hours REAL NOT NULL DEFAULT 0,
                UNIQUE (user_id, year),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE account_carryovers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                year INT NOT NULL,
                vacation_days DECIMAL(6,2) NOT NULL DEFAULT 0,
                overtime_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
                UNIQUE KEY uk_carry_user_year (user_id, year),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
