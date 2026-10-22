<?php
// Feature 6 (v1.0 Pro-Sync, Epic #146): project_categories + projects.category_id.
//
// Portiert aus Pro-Migration 0016. Kategorien typisieren ein Projekt und
// steuern, ob ein Kunde Pflicht ist (needs_client) und wie das Kunden-Feld
// in der UI heisst (client_label — "Foerdermittelgeber" statt "Kunde" bei
// Foerderprojekten). Defaults decken die drei haeufigsten Faelle ab;
// weitere Kategorien pflegt der Admin in Presets.

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

if (!$tableExists('project_categories')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE project_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ckey TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                needs_client INTEGER NOT NULL DEFAULT 1,
                client_label TEXT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE project_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ckey VARCHAR(64) NOT NULL UNIQUE,
                label VARCHAR(191) NOT NULL,
                needs_client TINYINT(1) NOT NULL DEFAULT 1,
                client_label VARCHAR(64) NULL,
                position INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

$now = date('Y-m-d H:i:s');
$defaults = [
    ['kunde',      'Kundenprojekt',    1, null,                 10],
    ['intern',     'Internes Projekt', 0, null,                 20],
    ['foerderung', 'Foerderprojekt',   1, 'Foerdermittelgeber', 30],
];
$check = $pdo->prepare('SELECT COUNT(*) FROM project_categories WHERE ckey = ?');
$ins = $pdo->prepare('INSERT INTO project_categories (ckey, label, needs_client, client_label, position, active, created_at) VALUES (?,?,?,?,?,1,?)');
foreach ($defaults as [$ckey, $label, $needsClient, $clientLabel, $pos]) {
    $check->execute([$ckey]);
    if ((int) $check->fetchColumn() === 0) {
        $ins->execute([$ckey, $label, $needsClient, $clientLabel, $pos, $now]);
    }
}

if (!$columnExists('projects', 'category_id')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE projects ADD COLUMN category_id INTEGER NULL');
    } else {
        $pdo->exec('ALTER TABLE projects ADD COLUMN category_id INT NULL');
    }
    $kundeId = $pdo->query("SELECT id FROM project_categories WHERE ckey = 'kunde'")->fetchColumn();
    if ($kundeId) {
        $pdo->prepare('UPDATE projects SET category_id = ? WHERE category_id IS NULL')->execute([(int) $kundeId]);
    }
}
