<?php
declare(strict_types=1);

/** Convert an <input type="datetime-local"> value to 'Y-m-d H:i:s'. */
function parse_dtlocal(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    try {
        return (new DateTimeImmutable($v))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function ctrl_entries_index(): void
{
    require_perm('entries.manage');
    // Calendar is the default; the flat list is an opt-in via ?view=list.
    $view = get('view') === 'list' ? 'list' : 'calendar';
    $f    = entries_filter_from_request();

    if ($view === 'calendar') {
        $weekStart = entries_week_start_from_request();
        $weekEnd   = $weekStart->modify('+6 days');
        // In the calendar view the from/to filter follows the visible week.
        $f['from'] = $weekStart->format('Y-m-d');
        $f['to']   = $weekEnd->format('Y-m-d');
    }

    $entries = Repo::entries($f);
    view('entries/index', [
        'entries'     => $entries,
        'clients'     => Repo::clients(),
        'projects'    => Repo::projects(),
        'filter'      => $f,
        'total'       => Stats::totalMinutes($entries),
        'view'        => $view,
        'pro_url'     => (string) cfg('pro_url', 'https://timeminator.felixschaller.com'),
        'week_start'  => $view === 'calendar' ? ($weekStart ?? null) : null,
    ], 'Zeiteintraege');
}

/**
 * Parse the ?week=YYYY-Www parameter into the Monday of that ISO week, or
 * fall back to the Monday of the current week. Clamped to a 10-year window
 * around today so a malicious value can never produce an absurd range.
 */
function entries_week_start_from_request(): DateTimeImmutable
{
    $raw = (string) get('week', '');
    if ($raw !== '' && preg_match('/^(\d{4})-W(\d{1,2})$/', $raw, $m)) {
        try {
            $wk = (new DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2], 1);
            $now = new DateTimeImmutable('today');
            if (abs($wk->getTimestamp() - $now->getTimestamp()) < 10 * 365 * 86400) {
                return $wk;
            }
        } catch (Throwable) {
            // fall through to default
        }
    }
    $today = new DateTimeImmutable('today');
    $dow   = (int) $today->format('N'); // Monday = 1 … Sunday = 7
    return $today->modify('-' . ($dow - 1) . ' days');
}

/**
 * Build a Repo::entries filter from the query string. Admins may pass
 * `all=1` to include every user's entries; anyone else is pinned to their
 * own. The limit stays generous for exports.
 */
function entries_filter_from_request(): array
{
    $all = Auth::isAdmin() && (int) get('all', 0) === 1;
    return [
        'user_id'    => $all ? 0 : Auth::id(),
        'from'       => (string) get('from', ''),
        'to'         => (string) get('to', ''),
        'client_id'  => (int) get('client_id', 0),
        'project_id' => (int) get('project_id', 0),
        'limit'      => 5000,
    ];
}

function ctrl_entries_export(): void
{
    require_perm('entries.manage');
    $format = strtolower((string) get('format', 'csv'));
    if (!in_array($format, ['csv', 'json'], true)) {
        http_response_code(400);
        exit('Unsupported format. Use csv or json.');
    }
    $f = entries_filter_from_request();
    $rows = Repo::entries($f);
    $filename = 'timeminator-entries-' . date('Ymd-His') . '.' . $format;

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode(
            array_map(static fn(array $r) => entries_export_row($r), $rows),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'start_ts', 'end_ts', 'duration_min', 'client_code', 'client_name',
        'project_code', 'project_name',
        'work_package_code', 'work_package_name',
        'task_name', 'user_id', 'note',
        'source', 'evidence', 'batch_id',
    ], ',', '"', '\\');
    foreach ($rows as $r) {
        $exp = entries_export_row($r);
        fputcsv($out, [
            $exp['start_ts'], $exp['end_ts'], $exp['duration_min'],
            $exp['client_code'] ?? '', $exp['client_name'],
            $exp['project_code'] ?? '', $exp['project_name'],
            $exp['work_package_code'] ?? '', $exp['work_package_name'] ?? '',
            $exp['task_name'], $exp['user_id'], $exp['note'],
            $exp['source'], $exp['evidence'] ?? '', $exp['batch_id'] ?? '',
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/** Shape one Repo::entries row for export. */
function entries_export_row(array $r): array
{
    return [
        'start_ts'     => $r['start_ts'],
        'end_ts'       => $r['end_ts'],
        'duration_min' => (int) $r['duration_min'],
        'client_code'       => $r['client_code'] ?? null,
        'client_name'       => $r['client_name'],
        'project_code'      => $r['project_code'] ?? null,
        'project_name'      => $r['project_name'],
        'work_package_code' => $r['work_package_code'] ?? null,
        'work_package_name' => $r['work_package_name'] ?? null,
        'task_name'    => $r['task_name'],
        'user_id'      => (int) $r['user_id'],
        'note'         => (string) $r['note'],
        'source'       => (string) ($r['source'] ?? 'manual'),
        'evidence'     => $r['evidence'] ?? null,
        'batch_id'     => isset($r['batch_id']) ? (int) $r['batch_id'] : null,
    ];
}

function ctrl_entry_form(): void
{
    require_perm('entries.manage');
    $id = (int) get('id', 0);
    $entry = $id ? Repo::entry($id) : null;
    if ($entry && (int) $entry['user_id'] !== Auth::id() && !Auth::isAdmin()) {
        redirect_route('entries');
    }
    // For brand-new entries the week calendar can pre-fill start/end via
    // ?start_ts=…&end_ts=… (both as 'Y-m-d\TH:i' or 'Y-m-d H:i:s'). Values
    // are parsed through DateTimeImmutable so an invalid format just drops
    // the pre-fill rather than crashing.
    if (!$entry) {
        $prefill = entry_form_prefill_from_request();
        if ($prefill !== null) {
            $entry = $prefill;
        }
    }
    view('entries/form', [
        'entry'    => $entry,
        'clients'  => Repo::clients(true),
        'projects' => Repo::projects(true),
        'tasks'    => Repo::tasks(true),
    ], $entry && !empty($entry['id']) ? 'Eintrag bearbeiten' : 'Neuer Eintrag');
}

function entry_form_prefill_from_request(): ?array
{
    $start = (string) get('start_ts', '');
    $end   = (string) get('end_ts', '');
    if ($start === '' && $end === '') {
        return null;
    }
    $norm = static function (string $v): ?string {
        $v = trim(str_replace('T', ' ', $v));
        if ($v === '') return null;
        try {
            return (new DateTimeImmutable($v))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    };
    return [
        'start_ts' => $norm($start),
        'end_ts'   => $norm($end),
    ];
}

function ctrl_entry_save(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id = post_int('id');
    $taskId = post_int('task_id');
    $start = parse_dtlocal((string) post('start_ts'));
    $end = parse_dtlocal((string) post('end_ts'));
    $note = trim((string) post('note'));

    if (!$taskId || !$start || !$end) {
        flash('Aufgabe, Start und Ende sind erforderlich.', 'err');
        redirect_route('entry_form', $id ? ['id' => $id] : []);
    }
    if ($end <= $start) {
        flash('Ende muss nach dem Start liegen.', 'err');
        redirect_route('entry_form', $id ? ['id' => $id] : []);
    }

    if ($id) {
        $existing = Repo::entry($id);
        if (!$existing || ((int) $existing['user_id'] !== Auth::id() && !Auth::isAdmin())) {
            redirect_route('entries');
        }
        Repo::updateEntry($id, $taskId, $start, $end, $note);
        flash('Eintrag aktualisiert.');
    } else {
        Repo::createEntry(Auth::id(), $taskId, $start, $end, $note, 'manual');
        flash('Eintrag gespeichert.');
    }
    redirect_route('entries');
}

function ctrl_entry_delete(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        Repo::deleteEntry($id, Auth::id(), Auth::isAdmin());
        flash('Eintrag geloescht.');
    }
    redirect_route('entries');
}

// ---------- Timer ----------

function ctrl_timer_start(): void
{
    require_perm('entries.manage');
    csrf_check();
    $taskId = post_int('task_id');
    if (!$taskId) {
        flash('Bitte eine Aufgabe waehlen.', 'err');
        redirect_route('dashboard');
    }
    Repo::startTimer(Auth::id(), $taskId, trim((string) post('note')));
    flash('Timer gestartet.');
    redirect_route('dashboard');
}

function ctrl_timer_stop(): void
{
    require_perm('entries.manage');
    csrf_check();
    Repo::stopTimer(Auth::id());
    flash('Timer gestoppt und gebucht.');
    redirect_route('dashboard');
}

function ctrl_timer_status(): void
{
    require_login();
    $run = Repo::running(Auth::id());
    if (!$run) {
        json_out(['running' => false]);
    }
    $started = new DateTimeImmutable((string) $run['start_ts']);
    json_out([
        'running'      => true,
        'task'         => $run['task_name'],
        'project'      => $run['project_name'],
        'client'       => $run['client_name'],
        'start_ts'     => $run['start_ts'],
        'elapsed_secs' => max(0, (new DateTimeImmutable('now'))->getTimestamp() - $started->getTimestamp()),
    ]);
}
