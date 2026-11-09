<?php
/**
 * Dev-Only-Seeder: bringt genug Vielfalt in die lokale DB, damit das
 * Mitarbeiter-Cockpit (Verteilung je Woche + Donut "Wofuer die Zeit
 * draufging" + KPI-Tiles) sinnvoll angezeigt werden kann.
 *
 * Felix-Feedback 2026-11-07: "die datenbasis ist bisschen schlecht zum
 * testen wenn es nur eine kategorie gibt." Dieses Script ist idempotent -
 * mehrfaches Ausfuehren bringt den Zielzustand ohne Dubletten.
 *
 * Was passiert:
 *  - Projekte 10 (Wissensbasis) und 11 (Senkrechtstarter) werden auf
 *    "Internes Projekt" umkategorisiert, Projekt 7 (Sicherheitsanalyse)
 *    auf "Foerderprojekt".
 *  - employee_terms fuer user_id=1 (Felix): Angestellt, 40 h/Woche,
 *    30 Tage Urlaub, DE-BY, gueltig ab 2024-01-01.
 *  - account_carryovers fuer Jahr 2026: 3 Tage Urlaub, +5 h Ueberstunden.
 *  - Prozess-Kategorien "Admin" und "Buchhaltung" + rund 12 Prozess-
 *    Buchungen pro Mittwoch der letzten 10 Wochen.
 *  - Je eine Urlaubs-Woche im August 2026 und ein Krankentag im September
 *    2026 fuer user_id=1.
 *
 * Nicht produktiv ausfuehren - das Script schreibt in projects,
 * employee_terms, account_carryovers, process_categories, process_entries
 * und absences.
 *
 * Verwendung:
 *   php scripts/dev-seed-employee-cockpit.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dev-Seed-Script darf nur in der CLI laufen.');
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/LoginThrottle.php';
require APP_ROOT . '/src/I18n.php';
require APP_ROOT . '/src/EmployeeRepo.php';
$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config.php';
DB::boot($GLOBALS['APP_CONFIG']);
date_default_timezone_set($GLOBALS['APP_CONFIG']['timezone'] ?? 'Europe/Berlin');

$userId = (int) (($argv[1] ?? null) ?: 1);
$u = DB::one('SELECT id, username FROM users WHERE id = ?', [$userId]);
if (!$u) {
    fwrite(STDERR, "User {$userId} nicht gefunden.\n");
    exit(1);
}
echo "Seed-Ziel: user_id=$userId ({$u['username']})\n";

// --- 1. Projekt-Kategorien neu verteilen ------------------------------
$intCat = DB::one('SELECT id FROM project_categories WHERE ckey = ? OR label = ?', ['internal', 'Internes Projekt']);
$forCat = DB::one('SELECT id FROM project_categories WHERE ckey = ? OR label = ?', ['support', 'Foerderprojekt']);
if (!$intCat) {
    fwrite(STDERR, "Kategorie 'Internes Projekt' fehlt - Seed abgebrochen.\n");
    exit(1);
}
$movedInt = [10, 11];
$movedFor = [7];
DB::run('UPDATE projects SET category_id = ? WHERE id IN (' . implode(',', $movedInt) . ')', [$intCat['id']]);
if ($forCat) {
    DB::run('UPDATE projects SET category_id = ? WHERE id IN (' . implode(',', $movedFor) . ')', [$forCat['id']]);
}
echo "  projects: " . implode(',', $movedInt) . " -> Internes Projekt" . ($forCat ? "; " . implode(',', $movedFor) . " -> Foerderprojekt" : '') . "\n";

// --- 2. employee_terms + account_carryovers ---------------------------
EmployeeRepo::saveTerms($userId, '2024-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 30.0, 'DE-BY');
EmployeeRepo::saveCarryover($userId, 2026, 3.0, 5.0);
echo "  employee_terms: Angestellt 40h, 30 Urlaubstage, DE-BY ab 2024-01-01\n";
echo "  account_carryovers 2026: +3 Tage Urlaub, +5 h Ueberstunden\n";

// --- 3. Prozess-Kategorien + Prozess-Buchungen ------------------------
$ensureProcCat = function (string $ckey, string $label): int {
    $row = DB::one('SELECT id FROM process_categories WHERE ckey = ?', [$ckey]);
    if ($row) { return (int) $row['id']; }
    return DB::insert(
        'INSERT INTO process_categories (ckey, label, created_at) VALUES (?, ?, ?)',
        [$ckey, $label, '2024-01-01 00:00:00']
    );
};
$catAdmin = $ensureProcCat('admin', 'Admin');
$catBook  = $ensureProcCat('buchhaltung', 'Buchhaltung');

// Alte Dev-Buchungen loeschen (idempotent).
DB::run(
    "DELETE FROM process_entries WHERE user_id = ? AND (note LIKE 'dev-seed-%' OR note IS NULL AND category_id IN (?, ?) AND start_ts > '2026-07-01')",
    [$userId, $catAdmin, $catBook]
);

$insProc = static function (int $catId, string $startTs, int $durationMin, string $note) use ($userId): void {
    $end = (new DateTimeImmutable($startTs))->modify('+' . $durationMin . ' minutes')->format('Y-m-d H:i:s');
    DB::insert(
        'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, note, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        [$userId, $catId, $startTs, $end, $durationMin, $note, $startTs, $startTs]
    );
};
$today = new DateTimeImmutable('today');
for ($w = 10; $w >= 1; $w--) {
    $monday = $today->modify('monday this week')->modify("-{$w} weeks");
    // Mittwoch der Woche: 1h Admin, 45min Buchhaltung
    $wed = $monday->modify('+2 days')->setTime(14, 0);
    $insProc($catAdmin, $wed->format('Y-m-d H:i:s'),         60, 'dev-seed-admin KW ' . $monday->format('W'));
    $insProc($catBook,  $wed->modify('+1 hour')->format('Y-m-d H:i:s'), 45, 'dev-seed-buchhaltung KW ' . $monday->format('W'));
}
echo "  process_entries: ~20 Buchungen auf Admin + Buchhaltung in den letzten 10 Wochen\n";

// --- 4. Abwesenheit: eine Urlaubswoche + ein Krankentag ---------------
$ensureAbsCat = function (string $ckey, string $label, string $account): int {
    $row = DB::one('SELECT id FROM absence_categories WHERE ckey = ?', [$ckey]);
    if ($row) { return (int) $row['id']; }
    return DB::insert(
        'INSERT INTO absence_categories (ckey, label, position, active, account, created_at) VALUES (?,?,?,?,?,?)',
        [$ckey, $label, 10, 1, $account, '2024-01-01 00:00:00']
    );
};
$catVac  = $ensureAbsCat('urlaub', 'Urlaub', 'vacation');
$catSick = $ensureAbsCat('krank',  'Krankheit', 'sick');

$exists = DB::one(
    'SELECT id FROM absences WHERE user_id = ? AND category_id = ? AND start_date = ?',
    [$userId, $catVac, '2026-08-17']
);
if (!$exists) {
    DB::insert(
        'INSERT INTO absences (user_id, category_id, start_date, end_date, created_at, updated_at) VALUES (?,?,?,?,?,?)',
        [$userId, $catVac, '2026-08-17', '2026-08-21', '2026-01-01 00:00:00', '2026-01-01 00:00:00']
    );
    echo "  absence: Urlaub 2026-08-17..21 (KW 34)\n";
}
$existsSick = DB::one(
    'SELECT id FROM absences WHERE user_id = ? AND category_id = ? AND start_date = ?',
    [$userId, $catSick, '2026-09-18']
);
if (!$existsSick) {
    DB::insert(
        'INSERT INTO absences (user_id, category_id, start_date, end_date, created_at, updated_at) VALUES (?,?,?,?,?,?)',
        [$userId, $catSick, '2026-09-18', '2026-09-18', '2026-01-01 00:00:00', '2026-01-01 00:00:00']
    );
    echo "  absence: Krank 2026-09-18\n";
}

echo "Fertig. Browser-Refresh auf /index.php?r=cockpit_employee&user_id=$userId zeigt die neue Datenvielfalt.\n";
