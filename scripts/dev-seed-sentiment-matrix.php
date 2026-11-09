<?php
/**
 * Dev-Only-Seeder: legt mehrere Fake-Mitarbeiter mit Rollenverteilung +
 * plan_allocations + sentiment_entries an, damit die Stimmungs-Matrix
 * (#102 Team, #104 Belegschaft) mit echten mehrzeiligen Daten testbar
 * wird.
 *
 * Felix-Feedback 2026-11-09: "Das waere jetzt natuerlich zum Testen mal
 * interessant, noch ein paar andere Testdaten zu haben. Also sprich mal
 * ein paar Fake-Mitarbeiter mit einzufuegen, um dann mal die
 * Funktionalitaet zu testen."
 *
 * Was passiert (idempotent):
 *  - 5 Fake-User werden angelegt, falls noch nicht vorhanden:
 *      * alice   -> Rolle projektmanager
 *      * bob     -> Rolle user (Mitarbeiter)
 *      * carla   -> Rolle user (Mitarbeiter)
 *      * dani    -> Rolle hr
 *      * erik    -> Rolle assistenz
 *  - Alle ausser erik bekommen eine plan_allocations auf das erste
 *    existierende Projekt (so dass sie im Team-Scope landen - Felix'
 *    Default-Team-Modell ueber plan_allocations).
 *  - Pro Fake-User werden 12 Wochen Rueckblick mit variierenden Mood/
 *    Flow-Werten geseeded, mit gezielten Luecken damit "—" in der
 *    Matrix auftaucht.
 *
 * Nicht produktiv ausfuehren. Verwendung:
 *   php scripts/dev-seed-sentiment-matrix.php
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
require APP_ROOT . '/src/Settings.php';
require APP_ROOT . '/src/CalendarLocale.php';
require APP_ROOT . '/src/seed.php';
require APP_ROOT . '/src/Sentiment.php';

$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config.php';
DB::boot($GLOBALS['APP_CONFIG']);
date_default_timezone_set($GLOBALS['APP_CONFIG']['timezone'] ?? 'Europe/Berlin');

seed_roles(); // sorgt dafuer, dass die Zielrollen existieren.
$now = date('Y-m-d H:i:s');

/** @return int User-ID */
function dev_seed_user(string $username, string $displayName, string $roleName, string $now): int
{
    $uid = (int) (DB::scalar('SELECT id FROM users WHERE username = ?', [$username]) ?: 0);
    if ($uid) {
        return $uid;
    }
    $roleId = (int) (DB::scalar('SELECT id FROM roles WHERE name = ?', [$roleName]) ?: 0);
    $uid = DB::insert(
        'INSERT INTO users (username, display_name, password_hash, role_id, active, created_at)
         VALUES (?, ?, ?, ?, 1, ?)',
        [$username, $displayName, password_hash('demo', PASSWORD_DEFAULT), $roleId, $now]
    );
    echo "User '$username' angelegt (id=$uid, role=$roleName).\n";
    return $uid;
}

$users = [
    'alice' => dev_seed_user('alice', 'Alice Lead',   'projektmanager', $now),
    'bob'   => dev_seed_user('bob',   'Bob Beispiel', 'user',           $now),
    'carla' => dev_seed_user('carla', 'Carla Clerk',  'user',           $now),
    'dani'  => dev_seed_user('dani',  'Dani HR',      'hr',             $now),
    'erik'  => dev_seed_user('erik',  'Erik Office',  'assistenz',      $now),
];

/*
 * plan_allocations fuer Team-Scope: alle ausser Erik (Assistenz) bekommen
 * eine Zuordnung auf das erste existierende Projekt. Dann tauchen sie bei
 * jedem PM, der auf dieses Projekt schaut, im Team auf.
 */
$pid = (int) (DB::scalar('SELECT id FROM projects ORDER BY id ASC LIMIT 1') ?: 0);
if ($pid > 0) {
    foreach (['alice', 'bob', 'carla', 'dani'] as $k) {
        $uid = $users[$k];
        $exists = (int) DB::scalar(
            'SELECT COUNT(*) FROM plan_allocations WHERE project_id = ? AND user_id = ?',
            [$pid, $uid]
        );
        if (!$exists) {
            DB::run(
                'INSERT INTO plan_allocations (project_id, user_id, period_kind, planned_minutes, created_at)
                 VALUES (?,?,?,?,?)',
                [$pid, $uid, 'total', 0, $now]
            );
            echo "plan_allocation fuer user_id=$uid auf project_id=$pid angelegt.\n";
        }
    }
} else {
    echo "WARN: kein Projekt vorhanden - plan_allocations werden uebersprungen.\n";
}

/*
 * Sentiment-Entries: pro Fake-User ein mehrwoechiger Verlauf, damit die
 * Matrix mehrere Smiley-Spalten pro Zeile zeigt. Gezielte Luecken lassen
 * "—" erscheinen.
 *
 * Verteilungslogik:
 *  - alice: positive Grundstimmung, einzelne Taeler.
 *  - bob:   neutral, abwechselnd.
 *  - carla: in letzter Zeit steigend (Trend), viele Luecken am Anfang.
 *  - dani:  kein Trend, nur Mood (kein Flow).
 *  - erik:  nur Flow in den letzten Wochen (zeigt, dass Mood/Flow
 *           unabhaengig voneinander laufen).
 */
$today = new DateTimeImmutable('today');
$weekStart = CalendarLocale::weekStart($today);

$plans = [
    'alice' => [[5,5],[4,4],[5,5],[4,3],[2,2],[4,5],[5,4],null,[5,5],[4,4],[5,5],[4,5]],
    'bob'   => [[3,3],[3,2],null,[3,3],[2,2],[3,3],null,[3,4],[2,3],[3,3],[4,3],[3,3]],
    'carla' => [null, null, null, [2,2],[2,3],[3,3],[3,4],[4,4],[4,4],[4,5],[5,5],[5,5]],
    'dani'  => [[4,null],[3,null],[4,null],[3,null],null,[4,null],[5,null],[4,null],null,[4,null],[5,null],[4,null]],
    'erik'  => [null, null, null, null, null, [null,3],[null,4],[null,3],[null,4],[null,5],[null,4],[null,5]],
];

$inserted = 0; $skipped = 0;
foreach ($plans as $key => $weekPlan) {
    $uid = $users[$key];
    foreach ($weekPlan as $wkOffset => $cell) {
        if ($cell === null) {
            continue;
        }
        [$mood, $flow] = $cell;
        // Buche einen Eintrag am Mittwoch der jeweiligen Woche (verlaesslicher
        // Referenzpunkt; CalendarLocale::weekStart liefert den Wochenanfang,
        // '+ 2 days' = Mittwoch bei Montag-Start, bleibt in der KW).
        $wk = $weekStart->modify('-' . ($weeksBack = 11 - $wkOffset) . ' week');
        $day = $wk->modify('+2 days')->format('Y-m-d');
        $exists = (int) DB::scalar(
            'SELECT COUNT(*) FROM sentiment_entries WHERE user_id = ? AND as_of = ?',
            [$uid, $day]
        );
        if ($exists) {
            $skipped++;
            continue;
        }
        Sentiment::record($uid, $day, $mood, $flow, null);
        $inserted++;
    }
}
echo "sentiment_entries: $inserted neu, $skipped bereits vorhanden.\n";

echo "Fertig. Dev-User-Logins (Passwort 'demo'): alice, bob, carla, dani, erik.\n";
