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
    $d = [
        'client_id'    => $clientId,
        'name'         => $name,
        'code'         => trim((string) post('code')) ?: slugify($name),
        'track'        => trim((string) post('track')) ?: null,
        'color'        => trim((string) post('color')) ?: null,
        'active'       => post('active') ? 1 : 0,
        'target_hours' => $target,
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
