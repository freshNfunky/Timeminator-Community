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
    $f = [
        'user_id'    => Auth::id(),
        'from'       => (string) get('from', ''),
        'to'         => (string) get('to', ''),
        'client_id'  => (int) get('client_id', 0),
        'project_id' => (int) get('project_id', 0),
        'limit'      => 500,
    ];
    $entries = Repo::entries($f);
    view('entries/index', [
        'entries'  => $entries,
        'clients'  => Repo::clients(),
        'projects' => Repo::projects(),
        'filter'   => $f,
        'total'    => Stats::totalMinutes($entries),
    ], 'Zeiteintraege');
}

function ctrl_entry_form(): void
{
    require_perm('entries.manage');
    $id = (int) get('id', 0);
    $entry = $id ? Repo::entry($id) : null;
    if ($entry && (int) $entry['user_id'] !== Auth::id() && !Auth::isAdmin()) {
        redirect_route('entries');
    }
    view('entries/form', [
        'entry'    => $entry,
        'clients'  => Repo::clients(true),
        'projects' => Repo::projects(true),
        'tasks'    => Repo::tasks(true),
    ], $entry ? 'Eintrag bearbeiten' : 'Neuer Eintrag');
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
