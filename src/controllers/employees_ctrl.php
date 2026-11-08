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
    require_perm('cockpit.employee.view');
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

/**
 * Mitarbeiter-Detail (Pro-Parity 2026-11-07): Name, Vertragsdaten,
 * KPI-Tiles (Ueberstundenkonto, Resturlaub, Krankheit, Auslastung),
 * Verteilungs-Chart (Stunden/Prozent) und ein Donut "Wofuer die Zeit
 * draufging". Nicht-Admins sehen nur ihr eigenes Konto; Admins koennen
 * jede user_id aufrufen.
 */
function ctrl_cockpit_employee(): void
{
    require_perm('cockpit.employee.view');
    $all = Auth::can('admin.users');
    $uid = (int) (get('user_id') ?: Auth::id());
    if (!$all && $uid !== (int) Auth::id()) {
        $uid = (int) Auth::id();
    }
    $u = DB::one('SELECT id, username, display_name FROM users WHERE id = ?', [$uid]);
    if (!$u) {
        flash(t('cockpit.err_user_not_found', 'Mitarbeiter nicht gefunden.'), 'err');
        redirect_route('cockpit_employees');
    }
    $asOf = (new DateTimeImmutable('today'))->format('Y-m-d');
    $summary = AccountRepo::summary((int) $u['id'], $asOf);
    // Tagessoll aus EmployeeRepo::dailyTarget (view zeigt es neben der
    // Wochenarbeitszeit an); fuer Freelancer bzw. fehlende Vertragsdaten null.
    $summary['daily_target'] = !empty($summary['terms']) && $summary['terms']['employment_type'] !== EmployeeRepo::TYPE_FREELANCER
        ? EmployeeRepo::dailyTarget($summary['terms'])
        : null;
    view('cockpit/employee_detail', [
        'user'    => $u,
        'summary' => $summary,
        'asOf'    => $asOf,
        'all'     => $all,
    ], sprintf(t('cockpit.employee_detail_title', 'Mitarbeiter: %s'), $u['display_name'] ?: $u['username']));
}

/** JSON fuer die gestapelte Wochen-Grafik. Admins waehlen den Mitarbeiter; alle anderen sehen nur sich selbst. */
function ctrl_cockpit_employee_weeks(): void
{
    require_perm('cockpit.employee.view');
    $all = Auth::can('admin.users');
    $userId = $all && get('user_id') ? (int) get('user_id') : (int) Auth::id();
    $weeks = (int) get('weeks', 26);
    json_out(EmployeeStats::weekly($userId, in_array($weeks, [13, 26, 52], true) ? $weeks : 26));
}
