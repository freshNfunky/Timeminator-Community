<?php
declare(strict_types=1);

/**
 * Mitarbeiter-Cockpit (v1.0 F1, Epic #146, Issue #158): Konten und
 * Buchungsstand je Mitarbeiter — Woche Ist/Soll, Monat, Resturlaub,
 * Ueberstunden, Krank-Tage; dazu die gestapelte Wochen-Verteilung.
 *
 * Jeder Nutzer mit `stats.view` sieht sein eigenes Konto; Admins
 * (admin.users) sehen alle aktiven Nutzer und koennen fuer die
 * Chart den Mitarbeiter umschalten.
 */

function ctrl_cockpit_employees(): void
{
    require_perm('stats.view');
    $asOf = (new DateTimeImmutable('today'))->format('Y-m-d');
    $all = Auth::can('admin.users');
    $users = $all
        ? DB::all('SELECT id, username, display_name FROM users WHERE active = 1 ORDER BY COALESCE(NULLIF(display_name, ""), username)')
        : DB::all('SELECT id, username, display_name FROM users WHERE id = ?', [(int) Auth::id()]);
    $rows = [];
    foreach ($users as $u) {
        $rows[] = ['user' => $u, 'summary' => AccountRepo::summary((int) $u['id'], $asOf)];
    }
    view('cockpit/employees', [
        'rows' => $rows,
        'asOf' => $asOf,
        'all'  => $all,
    ], t('cockpit.employees_title', 'Mitarbeiter-Cockpit'));
}

/** JSON fuer die gestapelte Wochen-Grafik. Admins waehlen den Mitarbeiter; alle anderen sehen nur sich selbst. */
function ctrl_cockpit_employee_weeks(): void
{
    require_perm('stats.view');
    $all = Auth::can('admin.users');
    $userId = $all && get('user_id') ? (int) get('user_id') : (int) Auth::id();
    $weeks = (int) get('weeks', 26);
    json_out(EmployeeStats::weekly($userId, in_array($weeks, [13, 26, 52], true) ? $weeks : 26));
}
