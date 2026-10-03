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
    ], 'Kunden');
}

function ctrl_client_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    if ($name === '') {
        flash('Name ist erforderlich.', 'err');
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
        flash('Kunde gespeichert.');
    } catch (PDOException $e) {
        flash('Konnte nicht speichern (Kennung evtl. schon vergeben).', 'err');
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
    ], 'Projekte');
}

function ctrl_project_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $clientId = post_int('client_id');
    if ($name === '' || !$clientId) {
        flash('Kunde und Name sind erforderlich.', 'err');
        redirect_route('projects');
    }
    $d = [
        'client_id' => $clientId,
        'name'      => $name,
        'code'      => trim((string) post('code')) ?: slugify($name),
        'track'     => trim((string) post('track')) ?: null,
        'color'     => trim((string) post('color')) ?: null,
        'active'    => post('active') ? 1 : 0,
    ];
    try {
        Repo::saveProject($d, $id ?: null);
        flash('Projekt gespeichert.');
    } catch (PDOException $e) {
        flash('Konnte nicht speichern (Kennung evtl. schon vergeben).', 'err');
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
    ], 'Arbeitspakete');
}

function ctrl_workpackage_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $projectId = post_int('project_id');
    if ($name === '' || !$projectId) {
        flash('Projekt und Name sind erforderlich.', 'err');
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
        flash('Arbeitspaket gespeichert.');
    } catch (PDOException $e) {
        flash('Konnte nicht speichern (Kennung evtl. schon vergeben).', 'err');
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
    ], 'Aufgaben');
}

function ctrl_task_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $projectId = post_int('project_id');
    if ($name === '' || !$projectId) {
        flash('Projekt und Name sind erforderlich.', 'err');
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
    flash('Aufgabe gespeichert.');
    redirect_route('tasks');
}
