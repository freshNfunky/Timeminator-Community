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
    $value = (bool) post('done') ? (new DateTimeImmutable('today'))->format('Y-m-d H:i:s') : null;
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

/** v1.0 F2.4: Burn-Up JSON (kumulative Stunden ueber Zeit). */
function ctrl_cockpit_burnup_data(): void
{
    require_perm('stats.view');
    $id = (int) get('id');
    $project = $id ? Repo::project($id) : null;
    if (!$project) {
        json_out(['ok' => false, 'error' => 'not_found'], 404);
    }
    $burnup = Repo::projectBurnup($id);
    $target = $project['target_hours'] !== null ? (float) $project['target_hours'] : null;
    $points = [];
    foreach ($burnup as $p) {
        $points[] = [
            'd'  => (string) $p['d'],
            'h'  => round(((int) $p['cumulative_minutes']) / 60, 2),
        ];
    }
    // Fortschrittsmarker aus project_trackers (type=progress) fuer Overlay.
    $trackers = DB::all(
        "SELECT date_value AS as_of, value AS percent, value_forecast AS forecast, created_at
           FROM project_trackers
          WHERE project_id = ? AND type = 'progress' AND value IS NOT NULL
          ORDER BY created_at ASC",
        [$id]
    );
    $markers = [];
    foreach ($trackers as $t) {
        $date = $t['as_of'] ?: substr((string) $t['created_at'], 0, 10);
        $markers[] = [
            'd'  => $date,
            'p'  => (float) $t['percent'],
            'f'  => $t['forecast'] !== null ? (float) $t['forecast'] : null,
        ];
    }
    json_out([
        'ok'       => true,
        'points'   => $points,
        'target_h' => $target,
        'markers'  => $markers,
        'planned_end' => $project['planned_end'] ?? null,
    ]);
}
