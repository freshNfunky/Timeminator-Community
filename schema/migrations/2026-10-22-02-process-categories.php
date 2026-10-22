<?php
// Feature 6 (v1.0 Pro-Sync, Epic #146): interne Prozesse als eigene
// Zeiterfassung — fuer firmeninterne Taetigkeiten die kein Kundenprojekt
// sind (Angebotserstellung, Akquise, Weiterbildung, Firmen-Events).
//
// Portiert aus Pro-Migration 0038. Die process_entries sind strukturell
// ein Spiegel von time_entries, aber ohne Projekt/Workpackage/Task — nur
// Kategorie + Zeitraum + Notiz. Firmenbilanz-Verknuepfung (Pro) laeuft
// hier NICHT mit; Community sieht nur die reinen Verbrauchszahlen.

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

if (!$tableExists('process_categories')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE process_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ckey TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE process_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ckey VARCHAR(64) NOT NULL UNIQUE,
                label VARCHAR(191) NOT NULL,
                position INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

if (!$tableExists('process_entries')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE process_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                start_ts TEXT NOT NULL,
                end_ts TEXT NOT NULL,
                duration_min INTEGER NOT NULL,
                note TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES process_categories(id)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_process_entries_user ON process_entries (user_id, start_ts)');
    } else {
        $pdo->exec(
            'CREATE TABLE process_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                category_id INT NOT NULL,
                start_ts DATETIME NOT NULL,
                end_ts DATETIME NOT NULL,
                duration_min INT NOT NULL,
                note VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_process_entries_user (user_id, start_ts),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES process_categories(id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

$now = date('Y-m-d H:i:s');
$defaults = [
    ['angebotserstellung', 'Angebotserstellung',          10],
    ['aussendarstellung',  'Aussendarstellung',           20],
    ['akquise',            'Akquisetaetigkeit',           30],
    ['weiterbildung',      'Weiterbildung',               40],
    ['firmen_events',      'Firmen-Events',               50],
    ['sonstige',           'Sonstige interne Prozesse',   99],
];
$check = $pdo->prepare('SELECT COUNT(*) FROM process_categories WHERE ckey = ?');
$ins   = $pdo->prepare('INSERT INTO process_categories (ckey, label, position, active, created_at) VALUES (?,?,?,1,?)');
foreach ($defaults as [$ckey, $label, $pos]) {
    $check->execute([$ckey]);
    if ((int) $check->fetchColumn() === 0) {
        $ins->execute([$ckey, $label, $pos, $now]);
    }
}
