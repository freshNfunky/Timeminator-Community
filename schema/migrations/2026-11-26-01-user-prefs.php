<?php
// M1.1 #191: user_prefs - persoenliche Darstellungs-Praeferenzen pro User.
// locale ist der erste Bewohner, theme_preference folgt in #194. Alle
// Spalten nullable - NULL bedeutet "Firmen-Default verwenden".
//
// Trennung von employee_terms (vertraglich, HR/GL) und Admin-System
// (global, Admin). Siehe Memory settings-layer-classification.

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

if (!$tableExists('user_prefs')) {
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE user_prefs (
                user_id INTEGER PRIMARY KEY,
                locale  TEXT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE user_prefs (
                user_id INT PRIMARY KEY,
                locale  VARCHAR(8) NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
