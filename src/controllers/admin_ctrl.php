<?php
declare(strict_types=1);

// ---------- Users ----------

function ctrl_users_index(): void
{
    require_perm('admin.users');
    $editId = (int) get('edit', 0);
    view('admin/users', [
        'users' => DB::all('SELECT u.*, r.label AS role_label FROM users u LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.username'),
        'roles' => DB::all('SELECT * FROM roles ORDER BY label'),
        'edit'  => $editId ? DB::one('SELECT * FROM users WHERE id = ?', [$editId]) : null,
    ], 'Benutzer');
}

function ctrl_user_save(): void
{
    require_perm('admin.users');
    csrf_check();
    $id = post_int('id');
    $username = trim((string) post('username'));
    $display = trim((string) post('display_name'));
    $roleId = post_int('role_id');
    $active = post('active') ? 1 : 0;
    $password = (string) post('password');

    if ($username === '') {
        flash('Benutzername ist erforderlich.', 'err');
        redirect_route('admin_users');
    }

    try {
        if ($id) {
            DB::run('UPDATE users SET username=?, display_name=?, role_id=?, active=? WHERE id=?',
                [$username, $display, $roleId, $active, $id]);
            if ($password !== '') {
                DB::run('UPDATE users SET password_hash=? WHERE id=?',
                    [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('Benutzer aktualisiert.');
        } else {
            if (strlen($password) < 8) {
                flash('Passwort muss mindestens 8 Zeichen haben.', 'err');
                redirect_route('admin_users');
            }
            DB::insert('INSERT INTO users (username, password_hash, display_name, role_id, active, created_at) VALUES (?,?,?,?,?,?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $display, $roleId, $active, now()]);
            flash('Benutzer angelegt.');
        }
    } catch (PDOException $e) {
        flash('Konnte nicht speichern (Benutzername evtl. vergeben).', 'err');
    }
    redirect_route('admin_users');
}

// ---------- Roles & permissions ----------

function ctrl_roles_index(): void
{
    require_perm('admin.roles');
    $editId = (int) get('edit', 0);
    $editRole = $editId ? DB::one('SELECT * FROM roles WHERE id = ?', [$editId]) : null;
    $rolePerms = [];
    if ($editRole) {
        $rows = DB::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$editId]);
        $rolePerms = array_map('intval', array_column($rows, 'permission_id'));
    }
    view('admin/roles', [
        'roles'       => DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count FROM roles r ORDER BY r.label'),
        'permissions' => DB::all('SELECT * FROM permissions ORDER BY grp, label'),
        'edit'        => $editRole,
        'rolePerms'   => $rolePerms,
    ], 'Rollen und Rechte');
}

function ctrl_role_save(): void
{
    require_perm('admin.roles');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    $label = trim((string) post('label'));
    $perms = array_map('intval', (array) post('perms', []));

    if ($label === '') {
        flash('Bezeichnung ist erforderlich.', 'err');
        redirect_route('admin_roles');
    }

    try {
        if ($id) {
            $role = DB::one('SELECT * FROM roles WHERE id = ?', [$id]);
            // System role name stays fixed; label + permissions editable.
            if ($role && !$role['is_system'] && $name !== '') {
                DB::run('UPDATE roles SET name=?, label=? WHERE id=?', [$name, $label, $id]);
            } else {
                DB::run('UPDATE roles SET label=? WHERE id=?', [$label, $id]);
            }
        } else {
            if ($name === '') {
                $name = preg_replace('/[^a-z0-9_]+/', '_', strtolower($label)) ?: 'role';
            }
            $id = DB::insert('INSERT INTO roles (name, label, is_system, created_at) VALUES (?,?,?,?)',
                [$name, $label, 0, now()]);
        }
        // Reset and reassign permissions.
        DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
        foreach ($perms as $pid) {
            DB::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$id, $pid]);
        }
        flash('Rolle gespeichert.');
    } catch (PDOException $e) {
        flash('Konnte nicht speichern (Kennung evtl. vergeben).', 'err');
    }
    redirect_route('admin_roles');
}

function ctrl_role_delete(): void
{
    require_perm('admin.roles');
    csrf_check();
    $id = post_int('id');
    $role = $id ? DB::one('SELECT * FROM roles WHERE id = ?', [$id]) : null;
    if (!$role) {
        redirect_route('admin_roles');
    }
    if ($role['is_system']) {
        flash('System-Rollen koennen nicht geloescht werden.', 'err');
        redirect_route('admin_roles');
    }
    if ((int) DB::scalar('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]) > 0) {
        flash('Rolle ist noch Benutzern zugewiesen.', 'err');
        redirect_route('admin_roles');
    }
    DB::run('DELETE FROM roles WHERE id = ?', [$id]);
    flash('Rolle geloescht.');
    redirect_route('admin_roles');
}

// ---------- System: updates + registration ----------

function ctrl_system_index(): void
{
    require_perm('admin.system');
    if (is_post() && post('action') === 'sync_perms') {
        csrf_check();
        sync_permissions();
        flash('Rechte-Katalog synchronisiert.');
        redirect_route('admin_system');
    }
    view('admin/system', [
        'version'         => app_version(),
        'update'          => Updater::cached(),
        'registration'    => Registration::status(),
        'reg_endpoint'    => Registration::endpoint(),
        'reg_endpoint_default' => (string) cfg('registration_endpoint', ''),
        'reg_endpoint_override' => (string) Settings::get('registration_endpoint_override', ''),
        'reg_disabled'    => Registration::isDisabled(),
        'manifest'        => Updater::manifestUrl(),
        'manifest_default' => (string) cfg('update_manifest_url', ''),
        'manifest_override' => (string) Settings::get('update_manifest_url_override', ''),
        'update_disabled' => Updater::isDisabled(),
        'banner_config_off'        => Banners::isConfigDisabled(),
        'banner_visible'           => (bool) Settings::get('banner_visible', true),
        'banner_telemetry'         => Banners::telemetryEnabled(),
        'banner_endpoint'          => Banners::endpoint(),
        'banner_endpoint_default'  => Banners::endpointDefault(),
        'banner_endpoint_override' => (string) Settings::get('banner_endpoint_override', ''),
        'install_id'               => Banners::installId(),
    ], 'System');
}

function ctrl_update_check(): void
{
    require_perm('admin.system');
    csrf_check();
    $res = Updater::check();
    if (empty($res['ok'])) {
        flash('Update-Pruefung fehlgeschlagen: ' . ($res['error'] ?? 'unbekannt'), 'err');
    } elseif (!empty($res['newer'])) {
        flash('Update verfuegbar: Version ' . $res['latest'] . ' (aktuell ' . $res['current'] . ').', 'ok');
    } else {
        flash('Timeminator ist aktuell (Version ' . $res['current'] . ').');
    }
    redirect_route('admin_system');
}

function ctrl_update_apply(): void
{
    require_perm('admin.system');
    csrf_check();
    [$ok, $msg] = Updater::apply();
    flash($msg, $ok ? 'ok' : 'err');
    redirect_route('admin_system');
}

/**
 * Dismiss the update banner for this session. The next login (or a fresh
 * session cookie) will show it again — this is the "Ignorieren" button.
 */
function ctrl_update_dismiss(): void
{
    require_perm('admin.system');
    csrf_check();
    $version = trim((string) post('version'));
    if ($version !== '') {
        Updater::dismissForSession($version);
    }
    redirect(referrer_or_default(route('dashboard')));
}

/**
 * Skip a specific released version. The banner comes back only when a newer
 * one appears — this is the "Ueberspringen" button.
 */
function ctrl_update_skip(): void
{
    require_perm('admin.system');
    csrf_check();
    $version = trim((string) post('version'));
    if ($version === '') {
        redirect_route('admin_system');
    }
    Updater::skipVersion($version);
    flash('Version ' . $version . ' wird uebersprungen.');
    redirect(referrer_or_default(route('dashboard')));
}

/**
 * Forget every skipped version so all offers are shown again.
 */
function ctrl_update_clear_skipped(): void
{
    require_perm('admin.system');
    csrf_check();
    Updater::clearSkippedVersions();
    flash('Uebersprungene Versionen zurueckgesetzt.');
    redirect_route('admin_system');
}

function ctrl_registration_save(): void
{
    require_perm('admin.system');
    csrf_check();
    $optIn = (bool) post('opt_in');
    $email = trim((string) post('email'));
    $res = Registration::save($optIn, $email);
    flash($res['message'], $res['ok'] ? 'ok' : 'err');
    redirect_route('admin_system');
}

function ctrl_endpoints_save(): void
{
    require_perm('admin.system');
    csrf_check();
    $manifest = trim((string) post('manifest_override'));
    $reg = trim((string) post('registration_override'));
    if ($manifest !== '' && !preg_match('~^https?://~i', $manifest)) {
        flash('Update-URL muss mit http:// oder https:// beginnen.', 'err');
        redirect_route('admin_system');
    }
    if ($reg !== '' && !preg_match('~^https?://~i', $reg)) {
        flash('Registration-URL muss mit http:// oder https:// beginnen.', 'err');
        redirect_route('admin_system');
    }
    Settings::set('update_manifest_url_override', $manifest);
    Settings::set('registration_endpoint_override', $reg);
    Settings::set('update_check_disabled', (bool) post('update_disabled'));
    Settings::set('registration_disabled', (bool) post('registration_disabled'));
    flash('Endpoints gespeichert.');
    redirect_route('admin_system');
}
