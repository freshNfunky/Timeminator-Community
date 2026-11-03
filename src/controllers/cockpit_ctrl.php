<?php
declare(strict_types=1);

/**
 * Projekt-Cockpit (v1.0 F2, Epic #146, Issue #160).
 *
 * Unternehmensweite Projekt-Uebersicht mit Health-Flags (Kickoff,
 * Closeout, Termin ueberfaellig, Zeit-%) und Review-Batch fuer faellige
 * Fortschrittsmeldungen. Portiert aus Pro — Geld-Felder (Rechnung,
 * Budget-EUR) sind weggelassen; Rechnungs-Teaser verlinken auf
 * pro_invoices.
 *
 * Progress wird in dieser Edition am bestehenden project_trackers mit
 * type='progress' gespeichert (keine separate progress_updates-Tabelle).
 */

/** Review-Turnus-Default in Tagen (Projekt-Override: projects.review_interval_days). */
const COCKPIT_REVIEW_INTERVAL_DEFAULT_DAYS = 30;

/**
 * Health-Flags je Projektzeile.
 *
 * @return array{kickoff:bool,closeout:bool,deadline_overdue:bool}
 */
function cockpit_flags_for(array $row): array
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $plannedEnd = $row['planned_end'] ?? null;
    $progress = $row['progress'] ?? null;
    $deadlineOverdue = $plannedEnd !== null && $plannedEnd !== ''
        && $plannedEnd < $today
        && ($progress === null || (float) $progress < 100.0);

    return [
        'kickoff'          => !empty($row['kickoff_at']),
        'closeout'         => !empty($row['closeout_at']),
        'deadline_overdue' => $deadlineOverdue,
    ];
}

/**
 * Cockpit-Zeilen fuer alle aktiven Projekte: Ist aus time_entries, Zeit-%
 * aus Ist/target_hours, Progress + Forecast aus letztem project_trackers.
 *
 * @return list<array<string,mixed>>
 */
function cockpit_rows(): array
{
    $projects = Repo::projects(true);
    if (!$projects) {
        return [];
    }
    $ids = array_map('intval', array_column($projects, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Ist-Minuten pro Projekt (Gesamt).
    $istTotal = [];
    foreach (DB::all(
        "SELECT project_id, COALESCE(SUM(duration_min), 0) AS m
           FROM time_entries
          WHERE project_id IN ($placeholders) AND duration_min IS NOT NULL
          GROUP BY project_id",
        $ids
    ) as $r) {
        $istTotal[(int) $r['project_id']] = (int) $r['m'];
    }

    // Letzter progress-Tracker je Projekt.
    $latestProgress = [];
    foreach (DB::all(
        "SELECT pt.project_id, pt.value AS percent, pt.value_forecast AS forecast,
                pt.date_value AS as_of, pt.created_at
           FROM project_trackers pt
          WHERE pt.type = 'progress' AND pt.project_id IN ($placeholders)
            AND pt.id = (SELECT id FROM project_trackers
                          WHERE type = 'progress' AND project_id = pt.project_id
                          ORDER BY created_at DESC, id DESC LIMIT 1)",
        $ids
    ) as $r) {
        $latestProgress[(int) $r['project_id']] = [
            'percent'  => $r['percent'] !== null ? (float) $r['percent'] : null,
            'forecast' => $r['forecast'] !== null ? (float) $r['forecast'] : null,
            'as_of'    => $r['as_of'] ?: substr((string) $r['created_at'], 0, 10),
        ];
    }

    $rows = [];
    foreach ($projects as $p) {
        $pid = (int) $p['id'];
        $istMin = $istTotal[$pid] ?? 0;
        $targetMin = $p['target_hours'] !== null ? (int) round(((float) $p['target_hours']) * 60) : 0;
        $zeitPct = $targetMin > 0 ? round(($istMin / $targetMin) * 100, 1) : null;
        $prog = $latestProgress[$pid] ?? null;

        $row = [
            'project_id'     => $pid,
            'project'        => $p['name'],
            'client'         => $p['client_name'] ?? '',
            'category_label' => $p['category_label'] ?? null,
            'ist_hours'      => round($istMin / 60, 1),
            'target_hours'   => $p['target_hours'] !== null ? (float) $p['target_hours'] : null,
            'zeit_pct'       => $zeitPct,
            'progress'       => $prog['percent']  ?? null,
            'forecast'       => $prog['forecast'] ?? null,
            'progress_as_of' => $prog['as_of']    ?? null,
            'planned_end'    => $p['planned_end'] ?? null,
            'planned_start'  => $p['planned_start'] ?? null,
            'kickoff_at'     => $p['kickoff_at'] ?? null,
            'closeout_at'    => $p['closeout_at'] ?? null,
        ];
        $row['flags'] = cockpit_flags_for($row);
        $rows[] = $row;
    }
    usort($rows, fn($a, $b) =>
        strcmp((string) $a['client'], (string) $b['client'])
        ?: strcmp((string) $a['project'], (string) $b['project']));
    return $rows;
}

/**
 * Projekte, die faellig fuer ein Review sind (Issue #146): letzter
 * Fortschritts-Eintrag aelter als das effektive Intervall, oder nie
 * ein Fortschritt gemeldet.
 *
 * @return list<array<string,mixed>>
 */
function cockpit_review_due_projects(): array
{
    $today = new DateTimeImmutable('today');
    $due = [];
    foreach (Repo::projects(true) as $p) {
        $pid = (int) $p['id'];
        $interval = $p['review_interval_days'] !== null
            ? (int) $p['review_interval_days']
            : COCKPIT_REVIEW_INTERVAL_DEFAULT_DAYS;
        $latest = DB::one(
            "SELECT date_value AS as_of, created_at FROM project_trackers
              WHERE project_id = ? AND type = 'progress'
              ORDER BY created_at DESC, id DESC LIMIT 1",
            [$pid]
        );
        $asOf = $latest['as_of'] ?? ($latest ? substr((string) $latest['created_at'], 0, 10) : null);
        $daysSince = $asOf ? $today->diff(new DateTimeImmutable($asOf))->days : null;
        $isDue = $asOf === null || $daysSince > $interval;
        if ($isDue) {
            $due[] = [
                'project_id'    => $pid,
                'project'       => $p['name'],
                'client'        => $p['client_name'] ?? '',
                'interval_days' => $interval,
                'last_review'   => $asOf,
                'days_since'    => $daysSince,
            ];
        }
    }
    usort($due, fn($a, $b) => ($b['days_since'] ?? PHP_INT_MAX) <=> ($a['days_since'] ?? PHP_INT_MAX));
    return $due;
}

function ctrl_cockpit_project(): void
{
    require_perm('stats.view');
    $id = (int) get('id');
    $project = $id ? Repo::project($id) : null;
    if (!$project) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('cockpit');
    }
    $row = null;
    foreach (cockpit_rows() as $r) {
        if ($r['project_id'] === $id) { $row = $r; break; }
    }

    // Soll/Ist-Matrix: 13 Wochen rueckwaerts bis +4 Wochen voraus.
    $today    = new DateTimeImmutable('today');
    $from     = $today->modify('monday this week')->modify('-12 weeks')->format('Y-m-d');
    $to       = $today->modify('monday this week')->modify('+4 weeks')->format('Y-m-d');
    $members  = PlanRepo::projectMembers($id);
    $weekKeys = PlanRepo::weekKeys($from, $to);
    $istMap   = PlanRepo::istByProjectUserWeek($from, $to)[$id] ?? [];
    $sollMap  = PlanRepo::sollByProjectUserWeek($from, $to)[$id] ?? [];
    $planTotal = (int) DB::scalar(
        "SELECT COALESCE(SUM(planned_minutes), 0) FROM plan_allocations WHERE project_id = ? AND period_kind = 'total'",
        [$id]
    );

    // v1.0 F2.4: Timeline aus project_trackers (progress + date_shift + status_report).
    $timeline = Repo::projectTrackers($id, 100);
    $shifts   = DateShiftRepo::forProject($id);

    view('cockpit/project', [
        'project'             => $project,
        'row'                 => $row,
        'default_review_days' => COCKPIT_REVIEW_INTERVAL_DEFAULT_DAYS,
        'matrix_members'      => $members,
        'matrix_weeks'        => $weekKeys,
        'matrix_ist'          => $istMap,
        'matrix_soll'         => $sollMap,
        'plan_total_minutes'  => $planTotal,
        'timeline'            => $timeline,
        'date_shifts'         => $shifts,
    ], sprintf(t('cockpit.project_title', 'Projekt-Cockpit: %s'), $project['name']));
}

function ctrl_cockpit_index(): void
{
    require_perm('stats.view');
    view('cockpit/index', [
        'rows'   => cockpit_rows(),
        'dueIds' => array_column(cockpit_review_due_projects(), 'project_id'),
    ], t('cockpit.index_title', 'Projekt-Cockpit'));
}

function ctrl_cockpit_review(): void
{
    require_perm('structure.manage');
    view('cockpit/review_batch', [
        'due' => cockpit_review_due_projects(),
    ], t('cockpit.review_title', 'Faellige Reviews'));
}

/**
 * Sammel-Review: ein Fortschritts-Tracker-Eintrag je ausgewaehltem
 * Projekt. Nutzt Repo::saveProjectTracker, da Community die Daten
 * am bestehenden project_trackers festhaelt.
 */
function ctrl_cockpit_review_batch_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $asOf = trim((string) post('as_of')) ?: (new DateTimeImmutable('now'))->format('Y-m-d');
    $entries = post('entries');
    $saved = 0;
    if (is_array($entries)) {
        foreach ($entries as $pid => $e) {
            if (empty($e['include']) || !is_array($e)) {
                continue;
            }
            $pid = (int) $pid;
            if (!Repo::project($pid)) {
                continue;
            }
            $percent = (string) ($e['percent'] ?? '');
            if ($percent === '') {
                continue;
            }
            $fc = (string) ($e['forecast_percent'] ?? '');
            $moodOf = static fn($v) => in_array((string) $v, ['1', '2', '3', '4', '5'], true) ? (int) $v : null;
            Repo::saveProjectTracker(
                $pid,
                'progress',
                trim((string) ($e['comment'] ?? '')),
                [
                    'value'          => max(0.0, min(100.0, (float) str_replace(',', '.', $percent))),
                    'value_forecast' => $fc === '' ? null : max(0.0, min(100.0, (float) str_replace(',', '.', $fc))),
                    'date_value'     => $asOf,
                    'project_mood'   => $moodOf($e['project_mood']  ?? null),
                    'customer_mood'  => $moodOf($e['customer_mood'] ?? null),
                ],
                (int) Auth::id()
            );
            $saved++;
        }
    }
    flash(sprintf(
        $saved > 0
            ? t('cockpit.review_saved_n', '%d Projekt(e) aktualisiert.')
            : t('cockpit.review_none_saved', 'Keine Eintraege ausgewaehlt.'),
        $saved
    ));
    redirect_route('cockpit');
}

/** Kickoff/Closeout-Flag toggeln. */
function ctrl_cockpit_health_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    if (!$id || !Repo::project($id)) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('cockpit');
    }
    $field = (string) post('field');
    if (!in_array($field, ['kickoff_at', 'closeout_at'], true)) {
        flash(t('cockpit.err_unknown_field', 'Unbekanntes Feld.'), 'err');
        redirect_route('cockpit_project', ['id' => $id]);
    }
    if ((bool) post('clear')) {
        $value = null;
    } else {
        $date = trim((string) post('date'));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $value = $date . ' 00:00:00';
        } elseif ((bool) post('done')) {
            $value = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');
        } else {
            $value = null;
        }
    }
    Repo::setProjectHealthField($id, $field, $value);
    flash(t('common.saved', 'Gespeichert.'));
    redirect_route('cockpit_project', ['id' => $id]);
}

/** Review-Turnus pro Projekt (Default = globale Konstante). */
function ctrl_cockpit_review_interval_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    if (!$id || !Repo::project($id)) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('cockpit');
    }
    $raw = trim((string) post('review_interval_days'));
    $value = $raw === '' ? null : max(1, (int) $raw);
    Repo::setProjectReviewIntervalDays($id, $value);
    flash(t('common.saved', 'Gespeichert.'));
    redirect_route('cockpit_project', ['id' => $id]);
}

/** v1.0 F2.3: Soll-Allokation (Mitarbeiter x KW) inline via JSON speichern. */
function ctrl_cockpit_plan_cell_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $projectId = post_int('project_id');
    $userId    = post_int('user_id');
    $user      = $userId ? DB::one('SELECT id FROM users WHERE id = ? AND active = 1', [$userId]) : null;
    if (!$projectId || !Repo::project($projectId) || !$user) {
        json_out(['ok' => false, 'error' => t('cockpit.err_project_or_employee', 'Projekt oder Mitarbeiter nicht gefunden.')], 404);
    }
    $raw = trim((string) post('week'));
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$dt || $dt->format('Y-m-d') !== $raw) {
        json_out(['ok' => false, 'error' => t('cockpit.err_invalid_week', 'Ungueltige Woche (ISO-Datum YYYY-MM-DD erforderlich).')], 422);
    }
    $week = $dt->modify('monday this week')->format('Y-m-d');
    $hoursRaw = trim(str_replace(',', '.', (string) post('hours')));
    if ($hoursRaw !== '' && !is_numeric($hoursRaw)) {
        json_out(['ok' => false, 'error' => t('cockpit.err_hours_number', 'Stundenwert muss eine Zahl sein.')], 422);
    }
    $hours = $hoursRaw === '' ? 0.0 : (float) $hoursRaw;
    if ($hours < 0 || $hours > 168) {
        json_out(['ok' => false, 'error' => t('cockpit.err_hours_range', 'Stundenwert ausserhalb 0..168.')], 422);
    }
    $existing = DB::all(
        "SELECT * FROM plan_allocations WHERE project_id = ? AND user_id = ? AND period_kind = 'week' AND period_start = ? ORDER BY id",
        [$projectId, $userId, $week]
    );
    $minutes = (int) round($hours * 60);
    if ($minutes <= 0) {
        foreach ($existing as $e) {
            PlanRepo::deleteAllocation((int) $e['id'], $projectId);
        }
        json_out(['ok' => true, 'minutes' => 0]);
    }
    if ($existing) {
        $keep = array_shift($existing);
        PlanRepo::saveAllocation([
            'project_id'      => $projectId,
            'user_id'         => $userId,
            'period_kind'     => 'week',
            'period_start'    => $week,
            'planned_minutes' => $minutes,
            'note'            => $keep['note'],
        ], (int) $keep['id']);
        foreach ($existing as $dup) {
            PlanRepo::deleteAllocation((int) $dup['id'], $projectId);
        }
    } else {
        PlanRepo::saveAllocation([
            'project_id'      => $projectId,
            'user_id'         => $userId,
            'period_kind'     => 'week',
            'period_start'    => $week,
            'planned_minutes' => $minutes,
            'note'            => null,
        ]);
    }
    json_out(['ok' => true, 'minutes' => $minutes]);
}

/** Projekt-Gesamt-Soll setzen (period_kind='total', user_id=NULL). */
function ctrl_cockpit_plan_total_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $projectId = post_int('project_id');
    if (!$projectId || !Repo::project($projectId)) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('cockpit');
    }
    $hours = (float) str_replace(',', '.', (string) post('planned_hours'));
    $existing = DB::one(
        "SELECT id FROM plan_allocations WHERE project_id = ? AND period_kind = 'total' AND user_id IS NULL ORDER BY id LIMIT 1",
        [$projectId]
    );
    $minutes = max(0, (int) round($hours * 60));
    if ($minutes <= 0 && $existing) {
        PlanRepo::deleteAllocation((int) $existing['id'], $projectId);
        flash(t('common.saved', 'Gespeichert.'));
    } elseif ($minutes > 0) {
        PlanRepo::saveAllocation([
            'project_id'      => $projectId,
            'user_id'         => null,
            'period_kind'     => 'total',
            'period_start'    => null,
            'planned_minutes' => $minutes,
            'note'            => null,
        ], $existing ? (int) $existing['id'] : null);
        flash(t('common.saved', 'Gespeichert.'));
    }
    redirect_route('cockpit_project', ['id' => $projectId]);
}

function ctrl_cockpit_plan_delete(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id        = post_int('id');
    $projectId = post_int('project_id');
    if ($id && $projectId) {
        PlanRepo::deleteAllocation($id, $projectId);
    }
    redirect_route('cockpit_project', ['id' => $projectId]);
}

/**
 * v1.0 F2.4: Terminverschiebung erfassen — loggt in date_shifts, aktualisiert
 * projects.planned_end und legt einen `date_shift`-Tracker-Eintrag an, damit
 * die Verschiebung in der Timeline auftaucht.
 */
function ctrl_cockpit_date_shift_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $projectId = post_int('project_id');
    $project   = $projectId ? Repo::project($projectId) : null;
    if (!$project) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('cockpit');
    }
    $oldEnd = $project['planned_end'] ?? null;
    $newEnd = trim((string) post('new_end')) ?: null;
    $reason = trim((string) post('reason'));
    if (!$newEnd) {
        flash(t('date_shift.err_new_end_required', 'Neues Datum ist erforderlich.'), 'err');
        redirect_route('cockpit_project', ['id' => $projectId]);
    }
    try {
        $result = DateShiftRepo::logIfShifted(DateShiftRepo::KIND_PROJECT, $projectId, $projectId, $oldEnd, $newEnd, $reason);
        if ($result !== 'no_change') {
            Repo::setProjectPlannedEnd($projectId, DateShiftRepo::normDate($newEnd));
        }
        if ($result === 'logged') {
            // Zusaetzlich im Tracker-Log, damit die Timeline die Verschiebung sieht.
            Repo::saveProjectTracker(
                $projectId,
                'date_shift',
                $reason !== '' ? $reason : t('date_shift.default_note', 'Terminverschiebung'),
                ['date_value' => DateShiftRepo::normDate($newEnd)],
                (int) Auth::id()
            );
            flash(t('date_shift.flash_saved', 'Terminverschiebung gespeichert.'));
        } elseif ($result === 'first_set') {
            flash(t('date_shift.flash_first_set', 'Datum gesetzt (keine Verschiebung, da vorher leer).'));
        } else {
            flash(t('date_shift.flash_no_change', 'Keine Aenderung — Datum identisch.'), 'err');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect_route('cockpit_project', ['id' => $projectId]);
}

/**
 * v1.0 F2.4: Burn-Up Payload (Pure Function, testbar).
 *
 * Shape persistiert durch tests/BurnupDataTest.php — Keys/Felder sind
 * Vertrag zum View-Script in views/cockpit/project.php, das daraus Ist-Linie,
 * Soll-Dash, Fortschritt/Prognose-Punkte und Planende-Vline rendert.
 */
function cockpit_burnup_payload(int $projectId): array
{
    $project = Repo::project($projectId);
    if (!$project) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $burnup = Repo::projectBurnup($projectId);
    $points = [];
    $labels = [];
    foreach ($burnup as $p) {
        $d = (string) $p['d'];
        $labels[] = $d;
        $points[] = [
            'd'  => $d,
            'h'  => round(((int) $p['cumulative_minutes']) / 60, 2),
        ];
    }

    // Fortschritts-Tracker (type=progress) als Rohdaten; die Frontend-fertigen
    // Reihen (progress_line + progress_markers + forecast_rays) bauen wir
    // unten im selben PHP, damit das View nur noch rendert.
    $trackers = DB::all(
        "SELECT date_value AS as_of, value AS percent, value_forecast AS forecast, created_at
           FROM project_trackers
          WHERE project_id = ? AND type = 'progress' AND value IS NOT NULL
          ORDER BY (COALESCE(date_value, substr(created_at,1,10))) ASC, created_at ASC",
        [$projectId]
    );
    $trackers = array_map(function ($t) {
        return [
            'as_of'    => $t['as_of'] ?: substr((string) $t['created_at'], 0, 10),
            'percent'  => (float) $t['percent'],
            'forecast' => $t['forecast'] !== null ? (float) $t['forecast'] : null,
        ];
    }, $trackers);

    // Review-Interval (fuer Prognose-Ray-Enden) brauchen wir schon beim Ausdehnen
    // der Labels bis zum Horizont, damit kein Marker am rechten Rand klemmt.
    $interval = $project['review_interval_days'] !== null
        ? (int) $project['review_interval_days']
        : COCKPIT_REVIEW_INTERVAL_DEFAULT_DAYS;

    // Horizont ueber "heute" hinaus (Pro-Vorbild): mindestens planned_end, plus
    // die am weitesten in die Zukunft reichende Prognose-Ray-Spitze. Sonst klemmt
    // die Deadline-vline direkt am rechten Rand und die Ideal-Linie kann ihre
    // 100% nicht mehr erreichen (Felix-Feedback 2026-11-03: "ideal muss am
    // projektstart beginnen und bei der deadline mit 100% schneiden").
    if ($labels) {
        $today    = (new DateTimeImmutable('today'))->format('Y-m-d');
        $horizon  = $labels[count($labels) - 1];
        if ($today > $horizon) $horizon = $today;
        if (!empty($project['planned_end']) && (string) $project['planned_end'] > $horizon) {
            $horizon = (string) $project['planned_end'];
        }
        foreach ($trackers as $t) {
            if ($t['forecast'] === null) continue;
            $rayEnd = (new DateTimeImmutable($t['as_of']))->modify('+' . $interval . ' days')->format('Y-m-d');
            if ($rayEnd > $horizon) $horizon = $rayEnd;
        }
        $last = new DateTimeImmutable($labels[count($labels) - 1]);
        $horD = new DateTimeImmutable($horizon);
        // Zukunfts-Tage auffuellen: h bleibt null (Ist-Linie endet bei today),
        // die Serien fuer Ideal/Deadline/Rays koennen aber darauf landen.
        while ($last < $horD) {
            $last     = $last->modify('+1 day');
            $labels[] = $last->format('Y-m-d');
            $points[] = ['d' => $last->format('Y-m-d'), 'h' => null];
        }
    }
    $n = count($labels);

    // Alten Marker-Shape beibehalten (Backwards-Compat fuer BurnupDataTest).
    $markers = array_map(fn($t) => [
        'd' => $t['as_of'], 'p' => $t['percent'], 'f' => $t['forecast'],
    ], $trackers);

    // xIndex zum naechstgelegenen Burnup-Label: letzter Label <= $ymd, sonst 0.
    $xIndex = function (string $ymd) use ($labels, $n): ?int {
        if ($n === 0) return null;
        $idx = -1;
        for ($i = 0; $i < $n; $i++) {
            if ($labels[$i] <= $ymd) $idx = $i; else break;
        }
        return $idx >= 0 ? $idx : 0;
    };

    // Progress-Linie: step-forward gefuellt (letzter gemeldeter Wert bleibt bis
    // zur naechsten Meldung) — konsistent mit Pro (progress_pct), damit zwischen
    // Meldungen eine durchgehende Linie steht, nicht eine Lineal-Verbindung.
    $progressLine = array_fill(0, $n, null);
    foreach ($trackers as $t) {
        $xi = $xIndex($t['as_of']);
        if ($xi === null) continue;
        for ($i = $xi; $i < $n; $i++) $progressLine[$i] = $t['percent'];
    }

    // Marker-Punkte an den tatsaechlichen Meldeterminen (sitzen auf der Linie).
    $progressMarkers = [];
    foreach ($trackers as $t) {
        $xi = $xIndex($t['as_of']);
        if ($xi !== null) $progressMarkers[] = ['x' => $xi, 'y' => $t['percent']];
    }

    // Prognose-Strahlen: jede Meldung mit forecast zeichnet einen EIGENEN,
    // unverbundenen Strahl von (as_of, percent) nach (as_of + review_interval, forecast).
    // $interval ist oben schon fuer die Horizont-Berechnung ermittelt worden.
    $forecastRays = [];
    foreach ($trackers as $t) {
        if ($t['forecast'] === null) continue;
        $x0 = $xIndex($t['as_of']);
        $endDate = (new DateTimeImmutable($t['as_of']))->modify('+' . $interval . ' days')->format('Y-m-d');
        $x1 = $xIndex($endDate);
        if ($x0 === null || $x1 === null) continue;
        $forecastRays[] = ['x0' => $x0, 'y0' => $t['percent'], 'x1' => $x1, 'y1' => $t['forecast']];
    }

    // Ideal-Linie (0-100%): lineare Trajektorie von der ersten gezeichneten
    // Woche (labels[0] = erste Buchung bzw. Projekt-Start im Chart) bis zur
    // Deadline bei 100 %. Felix-Feedback 2026-11-03: die Linie muss am
    // Projektstart beginnen und bei der Deadline exakt 100 % schneiden, auch
    // wenn kickoff_at nachtraeglich auf ein spaeteres Datum gesetzt wurde -
    // sonst beginnt die Ideal-Kurve mitten im Chart und erreicht am rechten
    // Rand nicht die 100-%-Linie.
    $plannedEnd = $project['planned_end'] ?? null;
    $start = $n > 0 ? $labels[0] : null;
    $idealLine = array_fill(0, $n, null);
    if ($start && $plannedEnd && $n > 0) {
        $tsStart = strtotime($start . ' 00:00:00');
        $tsEnd   = strtotime($plannedEnd . ' 00:00:00');
        if ($tsEnd > $tsStart) {
            foreach ($labels as $i => $ymd) {
                $tsAt = strtotime($ymd . ' 00:00:00');
                if ($tsAt < $tsStart) continue;
                // Nach dem Deadline-Datum bleibt die Linie auf 100 % stehen,
                // damit sie sichtbar bis zum rechten Chart-Rand durchlaeuft.
                if ($tsAt >= $tsEnd) { $idealLine[$i] = 100.0; continue; }
                $idealLine[$i] = round(100.0 * ($tsAt - $tsStart) / ($tsEnd - $tsStart), 2);
            }
        }
    }

    // Deadline (urspruengliches Enddatum) + Delay (aktuelles Enddatum nach
    // Verschiebung) aus DateShiftRepo. Pro-konform: der aelteste project-Shift
    // traegt die urspruengliche Deadline in old_end; ohne Shifts ist Deadline
    // = aktuelles planned_end und es gibt keinen Delay-Marker.
    $deadlineInfo = null;
    $delayInfo    = null;
    if ($plannedEnd) {
        $shifts = array_values(array_filter(
            DateShiftRepo::forProject($projectId),
            fn($s) => $s['kind'] === DateShiftRepo::KIND_PROJECT
        ));
        $originalEnd = $shifts ? (string) end($shifts)['old_end'] : $plannedEnd;
        $deadlineInfo = ['date' => $originalEnd, 'x' => $xIndex($originalEnd)];
        if ($plannedEnd !== $originalEnd) {
            $delayInfo = ['date' => $plannedEnd, 'x' => $xIndex($plannedEnd)];
        }
    }

    return [
        'ok'               => true,
        'points'           => $points,
        'target_h'         => $project['target_hours'] !== null ? (float) $project['target_hours'] : null,
        'markers'          => $markers,            // Legacy-Shape (view/tests pre-rays).
        'progress_line'    => $progressLine,       // Series rechte Achse, step-forward.
        'progress_markers' => $progressMarkers,    // Punkte auf der Linie.
        'forecast_rays'    => $forecastRays,       // Strahlen im Review-Turnus-Abstand.
        'ideal_line'       => $idealLine,          // 0-100% lineare Trajektorie.
        'review_interval'  => $interval,
        'planned_end'      => $plannedEnd,         // = Delay-Datum wenn verschoben, sonst = Deadline.
        'deadline'         => $deadlineInfo,       // urspruengliches Enddatum (grau).
        'delay'            => $delayInfo,          // aktuelles Enddatum nach Shift (orange).
    ];
}

/** v1.0 F2.4: Burn-Up JSON (kumulative Stunden ueber Zeit). */
function ctrl_cockpit_burnup_data(): void
{
    require_perm('stats.view');
    $id = (int) get('id');
    $payload = cockpit_burnup_payload($id);
    if (!($payload['ok'] ?? false)) {
        json_out($payload, 404);
    }
    json_out($payload);
}
