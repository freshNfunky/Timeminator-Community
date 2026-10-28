<?php
// v1.0 F2 (Epic #146, Issue #160): Projekt-Health-Felder + Mood an
// Fortschritts-Trackern.
//
// - projects.kickoff_at / closeout_at: einfach als "ist passiert"-Flag
//   (NULL = offen); zugleich Timestamp fuer die Chronologie im Burn-Up.
// - projects.review_interval_days: Review-Turnus-Override in Tagen; NULL
//   bedeutet globaler Default (Settings.cockpit_review_default_days).
// - projects.planned_start: Start-Datum (ergaenzt bestehendes planned_end),
//   damit der Burn-Up eine Start-X-Achse hat.
// - project_trackers.project_mood / customer_mood: 1..5-Skala am Progress-
//   Tracker (Pro trennt es in progress_updates; Community haengt die
//   Werte an den bestehenden tracker type='progress' an).

$pdo    = DB::pdo();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

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

// -------- projects: health + planning fields
$projectCols = [
    'kickoff_at'           => $driver === 'sqlite' ? 'TEXT NULL'    : 'DATETIME NULL',
    'closeout_at'          => $driver === 'sqlite' ? 'TEXT NULL'    : 'DATETIME NULL',
    'review_interval_days' => $driver === 'sqlite' ? 'INTEGER NULL' : 'INT NULL',
    'planned_start'        => $driver === 'sqlite' ? 'TEXT NULL'    : 'DATE NULL',
];
foreach ($projectCols as $col => $decl) {
    if (!$columnExists('projects', $col)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN $col $decl");
    }
}

// -------- project_trackers: mood at progress entries
$trackerCols = [
    'project_mood'  => $driver === 'sqlite' ? 'INTEGER NULL' : 'TINYINT NULL',
    'customer_mood' => $driver === 'sqlite' ? 'INTEGER NULL' : 'TINYINT NULL',
];
foreach ($trackerCols as $col => $decl) {
    if (!$columnExists('project_trackers', $col)) {
        $pdo->exec("ALTER TABLE project_trackers ADD COLUMN $col $decl");
    }
}
