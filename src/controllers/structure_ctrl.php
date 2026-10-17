<?php
declare(strict_types=1);

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'x';
}

// ---------- Clients ----------

function ctrl_clients_index(): void
{
    require_perm('structure.manage');
    $editId = (int) get('edit', 0);
    view('structure/clients', [
        'clients' => Repo::clients(),
        'edit'    => $editId ? Repo::client($editId) : null,
        'tracks'  => Repo::distinctTracks(),
    ], t('nav.clients'));
}

function ctrl_client_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    if ($name === '') {
        flash(t('struct.err_name_required', 'Name ist erforderlich.'), 'err');
        redirect_route('clients');
    }
    $code = trim((string) post('code')) ?: slugify($name);
    $d = [
        'name'   => $name,
        'code'   => $code,
        'track'  => trim((string) post('track')) ?: null,
        'color'  => trim((string) post('color')) ?: null,
        'active' => post('active') ? 1 : 0,
    ];
    try {
        Repo::saveClient($d, $id ?: null);
        flash(t('clients.flash_saved', 'Kunde gespeichert.'));
    } catch (PDOException $e) {
        flash(t('struct.err_duplicate_code', 'Konnte nicht speichern (Kennung evtl. schon vergeben).'), 'err');
    }
    redirect_route('clients');
}

// ---------- Projects ----------

function ctrl_projects_index(): void
{
    require_perm('structure.manage');
    $editId = (int) get('edit', 0);
    view('structure/projects', [
        'projects' => Repo::projects(),
        'clients'  => Repo::clients(),
        'edit'     => $editId ? Repo::project($editId) : null,
        'tracks'   => Repo::distinctTracks(),
    ], t('nav.projects'));
}

/**
 * Projekt-Cockpit (v0.9.1 catch-up on #112) — a per-project detail view
 * with a burn-up chart (cumulative Ist over time) + Soll horizontal line
 * + status KPIs + last entries. Linked from the projects list and from
 * the dashboard planning tile.
 */
function ctrl_project_show(): void
{
    require_perm('structure.manage');
    $id = (int) get('id', 0);
    $project = $id ? Repo::project($id) : null;
    if (!$project) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('projects');
    }
    $burnup = Repo::projectBurnup($id);
    $recent = DB::all(
        'SELECT e.*, u.username AS user_username, t.name AS task_name
           FROM time_entries e
      LEFT JOIN users u ON u.id = e.user_id
      LEFT JOIN tasks t ON t.id = e.task_id
          WHERE e.project_id = ?
          ORDER BY e.start_ts DESC
          LIMIT 10',
        [$id]
    );
    $totalMin = (int) DB::scalar(
        'SELECT COALESCE(SUM(duration_min), 0) FROM time_entries
          WHERE project_id = ? AND duration_min IS NOT NULL',
        [$id]
    );
    $targetMin = $project['target_hours'] !== null
        ? (int) round(((float) $project['target_hours']) * 60)
        : 0;
    $percent = $targetMin > 0 ? (int) round(($totalMin / $targetMin) * 100) : 0;
    $status  = project_status_bucket($percent);
    // Simple 4-week trend: avg h/week over the last 28 days.
    $cutoff28 = (new DateTimeImmutable('today'))->modify('-28 days')->format('Y-m-d');
    $recent28 = (int) DB::scalar(
        "SELECT COALESCE(SUM(duration_min), 0) FROM time_entries
          WHERE project_id = ? AND duration_min IS NOT NULL AND DATE(start_ts) >= ?",
        [$id, $cutoff28]
    );
    $trendPerWeek = round($recent28 / 60 / 4, 1);

    view('structure/project_show', [
        'project'         => $project,
        'burnup'          => $burnup,
        'recent'          => $recent,
        'total_min'       => $totalMin,
        'target_min'      => $targetMin,
        'percent'         => $percent,
        'status'          => $status,
        'trend_per_week'  => $trendPerWeek,
        'trackers'        => Repo::projectTrackers($id, 50),
        'planned_end'     => $project['planned_end'] ?? null,
        'estimated_end'   => Repo::projectEstimatedEnd($id),
    ], sprintf(t('projects.cockpit_title', 'Projekt: %s'), $project['name']));
}

/** Save a new Projekt-Status-Report tracker entry (#127). */
function ctrl_project_tracker_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $projectId = post_int('project_id');
    $note      = trim((string) post('note'));
    $type      = trim((string) post('type', 'status_report'));
    if (!$projectId || !Repo::project($projectId)) {
        flash(t('projects.err_not_found', 'Projekt nicht gefunden.'), 'err');
        redirect_route('projects');
    }
    if ($note === '') {
        flash(t('tracker.err_note_empty', 'Bitte einen Text eintragen.'), 'err');
        redirect_route('project_show', ['id' => $projectId]);
    }

    $parseNum = static function (string $raw): ?float {
        $raw = trim($raw);
        if ($raw === '') { return null; }
        $norm = str_replace(',', '.', $raw);
        return is_numeric($norm) ? (float) $norm : null;
    };

    $extra = [];
    if ($type === 'progress') {
        $value = $parseNum((string) post('value', ''));
        if ($value === null) {
            flash(t('tracker.err_value_empty', 'Bitte einen Fortschrittswert (0..100) angeben.'), 'err');
            redirect_route('project_show', ['id' => $projectId]);
        }
        $forecast = $parseNum((string) post('value_forecast', ''));
        $extra['value']          = $value;
        $extra['value_forecast'] = $forecast;
    } else {
        $extra['status'] = (string) post('status', 'on_plan');
        $extra['date_value'] = trim((string) post('date_value', ''));
    }

    try {
        Repo::saveProjectTracker($projectId, $type, $note, $extra, Auth::id());
        flash(t('tracker.flash_saved', 'Status-Eintrag gespeichert.'));
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect_route('project_show', ['id' => $projectId]);
}

/**
 * Edit an existing tracker entry — enables backlog-fill scenarios and
 * typo corrections. Pass `created_at` to backdate the entry to when it
 * should have been recorded (common PM case: forgot to write the status
 * report on Friday, writes it Monday but wants it to appear on Friday).
 */
function ctrl_project_tracker_update(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id        = post_int('id');
    $projectId = post_int('project_id');
    if (!$id) { redirect_route('projects'); }

    $extra = [
        'status'         => (string) post('status', ''),
        'date_value'     => trim((string) post('date_value', '')),
        'value'          => post('value', null)          !== null && post('value', '')          !== '' ? (float) str_replace(',', '.', (string) post('value'))          : null,
        'value_forecast' => post('value_forecast', null) !== null && post('value_forecast', '') !== '' ? (float) str_replace(',', '.', (string) post('value_forecast')) : null,
    ];
    $createdAt = trim((string) post('created_at', ''));

    try {
        Repo::updateProjectTracker($id, (string) post('note', ''), $createdAt !== '' ? $createdAt : null, $extra);
        flash(t('tracker.flash_updated', 'Status-Eintrag aktualisiert.'));
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect_route('project_show', ['id' => $projectId]);
}

/** Admins (or the author) can retract a mis-posted tracker entry. */
function ctrl_project_tracker_delete(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id        = post_int('id');
    $projectId = post_int('project_id');
    if ($id) {
        Repo::deleteProjectTracker($id);
        flash(t('tracker.flash_deleted', 'Status-Eintrag entfernt.'));
    }
    redirect_route('project_show', ['id' => $projectId]);
}

function ctrl_project_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $clientId = post_int('client_id');
    if ($name === '' || !$clientId) {
        flash(t('projects.err_client_name_required', 'Kunde und Name sind erforderlich.'), 'err');
        redirect_route('projects');
    }
    $targetRaw = trim((string) post('target_hours'));
    $target = null;
    if ($targetRaw !== '') {
        // Accept comma OR dot as decimal separator — German users type "120,5".
        $norm = str_replace(',', '.', $targetRaw);
        if (!is_numeric($norm) || (float) $norm < 0) {
            flash(t('planning.err_target_invalid', 'Soll-Stunden muss eine positive Zahl sein.'), 'err');
            redirect_route('projects');
        }
        $target = round((float) $norm, 2);
    }
    $plannedEnd = trim((string) post('planned_end'));
    if ($plannedEnd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedEnd)) {
        flash(t('planning.err_planned_end_invalid', 'Deadline muss ein ISO-Datum (YYYY-MM-DD) sein.'), 'err');
        redirect_route('projects');
    }
    $d = [
        'client_id'    => $clientId,
        'name'         => $name,
        'code'         => trim((string) post('code')) ?: slugify($name),
        'track'        => trim((string) post('track')) ?: null,
        'color'        => trim((string) post('color')) ?: null,
        'active'       => post('active') ? 1 : 0,
        'target_hours' => $target,
        'planned_end'  => $plannedEnd !== '' ? $plannedEnd : null,
    ];
    try {
        Repo::saveProject($d, $id ?: null);
        flash(t('projects.flash_saved', 'Projekt gespeichert.'));
    } catch (PDOException $e) {
        flash(t('struct.err_duplicate_code', 'Konnte nicht speichern (Kennung evtl. schon vergeben).'), 'err');
    }
    redirect_route('projects');
}

// ---------- Work packages (Arbeitspakete) ----------

function ctrl_workpackages_index(): void
{
    require_perm('structure.manage');
    $editId = (int) get('edit', 0);
    view('structure/workpackages', [
        'work_packages' => Repo::workPackages(),
        'projects'      => Repo::projects(),
        'edit'          => $editId ? Repo::workPackage($editId) : null,
    ], t('nav.workpackages'));
}

function ctrl_workpackage_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $projectId = post_int('project_id');
    if ($name === '' || !$projectId) {
        flash(t('struct.err_project_name_required', 'Projekt und Name sind erforderlich.'), 'err');
        redirect_route('workpackages');
    }
    $d = [
        'project_id' => $projectId,
        'code'       => trim((string) post('code')) ?: slugify($name),
        'name'       => $name,
        'active'     => post('active') ? 1 : 0,
    ];
    try {
        Repo::saveWorkPackage($d, $id ?: null);
        flash(t('wp.flash_saved', 'Arbeitspaket gespeichert.'));
    } catch (PDOException $e) {
        flash(t('struct.err_duplicate_code', 'Konnte nicht speichern (Kennung evtl. schon vergeben).'), 'err');
    }
    redirect_route('workpackages');
}

// ---------- Tasks ----------

function ctrl_tasks_index(): void
{
    require_perm('structure.manage');
    $editId = (int) get('edit', 0);
    view('structure/tasks', [
        'tasks'         => Repo::tasks(),
        'projects'      => Repo::projects(),
        'work_packages' => Repo::workPackages(),
        'edit'          => $editId ? Repo::task($editId) : null,
    ], t('nav.tasks'));
}

function ctrl_task_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $projectId = post_int('project_id');
    if ($name === '' || !$projectId) {
        flash(t('struct.err_project_name_required', 'Projekt und Name sind erforderlich.'), 'err');
        redirect_route('tasks');
    }
    $d = [
        'project_id'      => $projectId,
        'work_package_id' => post_int('work_package_id') ?: null,
        'name'            => $name,
        'kind'            => trim((string) post('kind')) ?: null,
        'active'          => post('active') ? 1 : 0,
    ];
    Repo::saveTask($d, $id ?: null);
    flash(t('tasks.flash_saved', 'Aufgabe gespeichert.'));
    redirect_route('tasks');
}
