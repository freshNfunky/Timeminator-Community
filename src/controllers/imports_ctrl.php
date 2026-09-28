<?php
declare(strict_types=1);

/**
 * CSV import for time entries.
 *
 * Every accepted upload records one `import_batches` row and tags every
 * inserted `time_entries` row with its `batch_id`, so the whole import can
 * be rolled back as a unit. Rows the parser cannot map to an existing
 * client / project / task are shown in the preview and skipped on confirm.
 * Structure is never auto-created here — that would be too easy to get
 * wrong from a stray column.
 */

function ctrl_imports_index(): void
{
    require_perm('admin.imports');
    $batches = DB::all(
        'SELECT b.*, (SELECT COUNT(*) FROM time_entries e WHERE e.batch_id = b.id) AS entry_count
           FROM import_batches b
          ORDER BY b.created_at DESC'
    );
    view('imports/index', [
        'batches' => $batches,
        'preview' => $_SESSION['import_preview'] ?? null,
    ], 'CSV-Import');
}

function ctrl_imports_upload(): void
{
    require_perm('admin.imports');
    csrf_check();
    $file = $_FILES['csv'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('Keine oder fehlerhafte CSV-Datei.', 'err');
        redirect_route('imports');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        flash('Ungueltiger Upload.', 'err');
        redirect_route('imports');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        flash('CSV zu gross (max 5 MB).', 'err');
        redirect_route('imports');
    }
    try {
        $preview = imports_parse_csv($file['tmp_name']);
    } catch (Throwable $e) {
        flash('CSV konnte nicht gelesen werden: ' . $e->getMessage(), 'err');
        redirect_route('imports');
    }
    $preview['label'] = trim((string) post('label')) ?: basename((string) $file['name']);
    $_SESSION['import_preview'] = $preview;
    redirect_route('imports');
}

function ctrl_imports_confirm(): void
{
    require_perm('admin.imports');
    csrf_check();
    $preview = $_SESSION['import_preview'] ?? null;
    if (!$preview) {
        flash('Keine Vorschau vorhanden.', 'err');
        redirect_route('imports');
    }
    unset($_SESSION['import_preview']);
    $valid = array_values(array_filter($preview['rows'], static fn(array $r) => $r['ok']));
    if (!$valid) {
        flash('Kein gueltiger Datensatz in der CSV.', 'err');
        redirect_route('imports');
    }

    $batchId = DB::insert(
        'INSERT INTO import_batches (label, source, note, created_at) VALUES (?,?,?,?)',
        [$preview['label'], 'csv', $preview['note'] ?? null, now()]
    );

    $inserted = 0;
    foreach ($valid as $r) {
        $duration = null;
        if ($r['start_ts'] && $r['end_ts']) {
            $duration = Repo::minutesBetween($r['start_ts'], $r['end_ts']);
        }
        DB::run(
            'INSERT INTO time_entries
                (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min,
                 note, source, evidence, batch_id, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $r['user_id'], $r['task_id'], $r['project_id'], $r['client_id'],
                $r['start_ts'], $r['end_ts'], $duration,
                $r['note'] ?? '', 'import', $r['evidence'] ?? null,
                $batchId, now(), now(),
            ]
        );
        $inserted++;
    }
    flash("$inserted Zeitbuchungen importiert (Batch #$batchId).");
    redirect_route('imports');
}

function ctrl_imports_discard(): void
{
    require_perm('admin.imports');
    csrf_check();
    unset($_SESSION['import_preview']);
    flash('Vorschau verworfen.');
    redirect_route('imports');
}

function ctrl_imports_delete(): void
{
    require_perm('admin.imports');
    csrf_check();
    $id = post_int('id');
    if (!$id) {
        redirect_route('imports');
    }
    $batch = DB::one('SELECT * FROM import_batches WHERE id = ?', [$id]);
    if (!$batch) {
        redirect_route('imports');
    }
    $affected = (int) DB::scalar('SELECT COUNT(*) FROM time_entries WHERE batch_id = ?', [$id]);
    DB::run('DELETE FROM time_entries WHERE batch_id = ?', [$id]);
    DB::run('DELETE FROM import_batches WHERE id = ?', [$id]);
    flash("Batch #$id samt $affected Buchungen entfernt.");
    redirect_route('imports');
}

/**
 * Read + validate a CSV file. Returns:
 *   ['headers' => [...], 'rows' => [{ok, error?, ...resolved fields...}, ...], 'stats' => [...]]
 *
 * Each row is resolved (client_id, project_id, task_id, user_id) against the
 * current DB — the confirm step just inserts. Rows with missing/ambiguous
 * references are marked ok=false with an error string and skipped on confirm.
 */
function imports_parse_csv(string $path): array
{
    $fh = fopen($path, 'r');
    if (!$fh) {
        throw new RuntimeException('Datei nicht lesbar.');
    }
    // Detect delimiter and strip BOM on first line.
    $first = fgets($fh);
    if ($first === false) {
        fclose($fh);
        throw new RuntimeException('Leere Datei.');
    }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($fh);
    $headers = fgetcsv($fh, 0, $delim, '"', '\\');
    if (!$headers) {
        fclose($fh);
        throw new RuntimeException('CSV-Kopfzeile fehlt.');
    }
    $headers = array_map(static fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $headers);

    // Cache lookup maps once.
    $clientsByCode = array_column(DB::all('SELECT id, code, name FROM clients'), null, 'code');
    $clientsByName = array_column(DB::all('SELECT id, code, name FROM clients'), null, 'name');
    $projectsByCode = array_column(DB::all('SELECT id, client_id, code, name FROM projects'), null, 'code');
    $projectsByClient = [];
    foreach (DB::all('SELECT id, client_id, code, name FROM projects') as $p) {
        $projectsByClient[(int) $p['client_id']][strtolower($p['name'])] = $p;
    }
    $tasksByProject = [];
    foreach (DB::all('SELECT id, project_id, name FROM tasks') as $t) {
        $tasksByProject[(int) $t['project_id']][strtolower($t['name'])] = $t;
    }
    $usersByName = array_column(DB::all('SELECT id, username FROM users'), null, 'username');

    $rows = [];
    $ok = 0; $bad = 0;
    while (($cols = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        if (count($cols) === 1 && trim((string) $cols[0]) === '') continue;
        $r = array_combine($headers, array_pad($cols, count($headers), null)) ?: [];
        $out = imports_resolve_row(
            $r,
            $clientsByCode, $clientsByName,
            $projectsByCode, $projectsByClient,
            $tasksByProject, $usersByName
        );
        $rows[] = $out;
        $out['ok'] ? $ok++ : $bad++;
    }
    fclose($fh);
    return [
        'headers' => $headers,
        'rows'    => $rows,
        'stats'   => ['ok' => $ok, 'bad' => $bad, 'delim' => $delim],
    ];
}

/**
 * Resolve one CSV row into DB ids. `ok` is true only when every required
 * lookup succeeded and start/end are parsable.
 */
function imports_resolve_row(
    array $r,
    array $clientsByCode, array $clientsByName,
    array $projectsByCode, array $projectsByClient,
    array $tasksByProject, array $usersByName
): array {
    $errs = [];
    $client = null;
    $code = trim((string) ($r['client_code'] ?? ''));
    $name = trim((string) ($r['client_name'] ?? ''));
    if ($code !== '' && isset($clientsByCode[$code])) {
        $client = $clientsByCode[$code];
    } elseif ($name !== '' && isset($clientsByName[$name])) {
        $client = $clientsByName[$name];
    } else {
        $errs[] = 'Kunde nicht gefunden';
    }

    $project = null;
    if ($client) {
        $pcode = trim((string) ($r['project_code'] ?? ''));
        $pname = trim((string) ($r['project_name'] ?? ''));
        if ($pcode !== '' && isset($projectsByCode[$pcode])) {
            $project = $projectsByCode[$pcode];
            if ((int) $project['client_id'] !== (int) $client['id']) {
                $errs[] = 'Projekt gehoert zu anderem Kunden';
                $project = null;
            }
        } else {
            $lookup = $projectsByClient[(int) $client['id']] ?? [];
            if ($pname !== '' && isset($lookup[strtolower($pname)])) {
                $project = $lookup[strtolower($pname)];
            } else {
                $errs[] = 'Projekt nicht gefunden';
            }
        }
    }

    $task = null;
    if ($project) {
        $tname = trim((string) ($r['task_name'] ?? ''));
        $lookup = $tasksByProject[(int) $project['id']] ?? [];
        if ($tname !== '' && isset($lookup[strtolower($tname)])) {
            $task = $lookup[strtolower($tname)];
        } else {
            $errs[] = 'Aufgabe nicht gefunden';
        }
    }

    $userId = null;
    $uname = trim((string) ($r['user_username'] ?? ''));
    if ($uname !== '') {
        if (isset($usersByName[$uname])) {
            $userId = (int) $usersByName[$uname]['id'];
        } else {
            $errs[] = 'Benutzer unbekannt';
        }
    } else {
        $userId = Auth::id();
    }

    $start = imports_parse_ts($r['start_ts'] ?? null);
    $end   = imports_parse_ts($r['end_ts']   ?? null);
    if ($start === null) $errs[] = 'start_ts ungueltig';
    if ($end === null && trim((string) ($r['end_ts'] ?? '')) !== '') {
        $errs[] = 'end_ts ungueltig';
    }
    if ($start !== null && $end !== null && strcmp($end, $start) < 0) {
        $errs[] = 'end_ts vor start_ts';
    }

    return [
        'ok'         => count($errs) === 0,
        'error'      => count($errs) ? implode(', ', $errs) : null,
        'raw'        => $r,
        'user_id'    => $userId,
        'client_id'  => $client ? (int) $client['id'] : null,
        'project_id' => $project ? (int) $project['id'] : null,
        'task_id'    => $task ? (int) $task['id'] : null,
        'start_ts'   => $start,
        'end_ts'     => $end,
        'note'       => (string) ($r['note'] ?? ''),
        'evidence'   => trim((string) ($r['evidence'] ?? '')) ?: null,
    ];
}

function imports_parse_ts(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    try {
        return (new DateTimeImmutable($v))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}
