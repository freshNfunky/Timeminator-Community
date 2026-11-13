<?php
// M1.1 #192: workday_mask pro Vertragsstand. CSV '1,2,3,4,5' (ISO-Dow Mo=1..So=7).
// NULL = globaler Default (Holidays::workingDays()) - unveraendertes Verhalten fuer
// existierende Vertragsdaten. HR/GL kann im Vertragsdaten-Formular eigene Tage
// pro User setzen, der Nutzer selbst darf das nicht.

$pdo    = DB::pdo();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$columnExists = static function (string $table, string $column) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string) $row['name'] === $column) { return true; }
        }
        return false;
    }
    $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $s->execute([$table, $column]);
    return (bool) $s->fetchColumn();
};

if (!$columnExists('employee_terms', 'workday_mask')) {
    if ($driver === 'sqlite') {
        $pdo->exec('ALTER TABLE employee_terms ADD COLUMN workday_mask TEXT NULL');
    } else {
        $pdo->exec('ALTER TABLE employee_terms ADD COLUMN workday_mask VARCHAR(32) NULL');
    }
}
