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
    ], t('nav.users'));
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
        flash(t('users.err_username_required', 'Benutzername ist erforderlich.'), 'err');
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
            flash(t('users.flash_updated', 'Benutzer aktualisiert.'));
        } else {
            if (strlen($password) < 8) {
                flash(t('users.err_pw_len', 'Passwort muss mindestens 8 Zeichen haben.'), 'err');
                redirect_route('admin_users');
            }
            DB::insert('INSERT INTO users (username, password_hash, display_name, role_id, active, created_at) VALUES (?,?,?,?,?,?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $display, $roleId, $active, now()]);
            flash(t('users.flash_created', 'Benutzer angelegt.'));
        }
    } catch (PDOException $e) {
        flash(t('users.err_username_taken', 'Konnte nicht speichern (Benutzername evtl. vergeben).'), 'err');
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
    ], t('nav.roles'));
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
        flash(t('roles.err_label_required', 'Bezeichnung ist erforderlich.'), 'err');
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
        flash(t('roles.flash_saved', 'Rolle gespeichert.'));
    } catch (PDOException $e) {
        flash(t('roles.err_code_taken', 'Konnte nicht speichern (Kennung evtl. vergeben).'), 'err');
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
        flash(t('roles.err_system_delete', 'System-Rollen koennen nicht geloescht werden.'), 'err');
        redirect_route('admin_roles');
    }
    if ((int) DB::scalar('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]) > 0) {
        flash(t('roles.err_role_assigned', 'Rolle ist noch Benutzern zugewiesen.'), 'err');
        redirect_route('admin_roles');
    }
    DB::run('DELETE FROM roles WHERE id = ?', [$id]);
    flash(t('roles.flash_deleted', 'Rolle geloescht.'));
    redirect_route('admin_roles');
}

// ---------- System: updates + registration ----------

function ctrl_system_index(): void
{
    require_perm('admin.system');
    if (is_post() && post('action') === 'sync_perms') {
        csrf_check();
        sync_permissions();
        flash(t('system.flash_perms_synced', 'Rechte-Katalog synchronisiert.'));
        redirect_route('admin_system');
    }
    $mail = Mail::config();
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
        'theme_preference'         => (string) Settings::get('theme_preference', 'system'),
        'working_days'             => Holidays::workingDays(),
        'banner_config_off'        => Banners::isConfigDisabled(),
        'banner_visible'           => (bool) Settings::get('banner_visible', true),
        'banner_telemetry'         => Banners::telemetryEnabled(),
        'banner_endpoint'          => Banners::endpoint(),
        'banner_endpoint_default'  => Banners::endpointDefault(),
        'banner_endpoint_override' => (string) Settings::get('banner_endpoint_override', ''),
        'install_id'               => Banners::installId(),
        'mail'                     => $mail,
        'mail_has_password'        => $mail['pass'] !== '',
        'mail_configured'          => Mail::isConfigured(),
    ], t('nav.system'));
}

/**
 * Admin -> System -> Darstellung. Persists a single global theme preference
 * (`system` | `light` | `dark`) that the layout emits as `data-theme` on
 * `<html>`. Community is single-tenant, so one setting for the whole install
 * is enough; a per-user override can come later if a multi-user install
 * actually needs it.
 */
function ctrl_theme_save(): void
{
    require_perm('admin.system');
    csrf_check();
    $pref = (string) post('theme_preference');
    if (!in_array($pref, ['system', 'light', 'dark'], true)) {
        $pref = 'system';
    }
    Settings::set('theme_preference', $pref);
    flash(t('system.flash_theme_saved', 'Darstellung gespeichert.'));
    redirect_route('admin_system');
}

/**
 * Persist the ISO weekday numbers that count as working days (Mon=1 … Sun=7).
 * Lives under Admin -> System because it is a system-wide policy that
 * drives both the week-view tinting and the future 40h target reduction.
 */
function ctrl_working_days_save(): void
{
    require_perm('admin.system');
    csrf_check();
    $raw = post('working_days', []);
    if (!is_array($raw)) { $raw = []; }
    $clean = [];
    foreach ($raw as $v) {
        $n = (int) $v;
        if ($n >= 1 && $n <= 7) { $clean[$n] = true; }
    }
    $days = array_keys($clean);
    sort($days);
    if (!$days) { $days = [1, 2, 3, 4, 5]; } // never allow a zero-work week
    Settings::set('working_days', implode(',', $days));
    flash(t('system.flash_working_days_saved', 'Werktage gespeichert.'));
    redirect_route('admin_system');
}

/**
 * Persist the chosen holiday region (one entry from Holidays::catalog(),
 * or the sentinel `__none__` to disable the overlay). Submitted from the
 * "Externe Kalender" card below the week grid.
 */
function ctrl_holiday_region_save(): void
{
    require_perm('admin.system');
    csrf_check();
    $r = (string) post('holiday_region', Holidays::defaultRegion());
    if (!Holidays::isValidRegion($r)) {
        $r = Holidays::defaultRegion();
    }
    Settings::set('holiday_region', $r);
    flash(t('calimport.flash_saved', 'Feiertagskalender gespeichert.'));
    // Return to wherever the user triggered the save from (the week view).
    $back = (string) post('_back', route('entries'));
    header('Location: ' . $back);
    exit;
}

/**
 * Set the UI language for the current session (fixes #95). The chosen code is
 * clamped to I18n::SUPPORTED; anything else is ignored silently rather than
 * flashing an error, since the UI only ever offers the valid options itself.
 */
function ctrl_language_save(): void
{
    require_login();
    csrf_check();
    if (I18n::setSession((string) post('locale', ''))) {
        flash(t('admin.language_saved'));
    }
    redirect(referrer_or_default(route('admin_system')));
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
    flash(t('system.flash_unskip', 'Uebersprungene Versionen zurueckgesetzt.'));
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
        flash(t('system.err_update_url_proto', 'Update-URL muss mit http:// oder https:// beginnen.'), 'err');
        redirect_route('admin_system');
    }
    if ($reg !== '' && !preg_match('~^https?://~i', $reg)) {
        flash(t('system.err_registry_url_proto', 'Registration-URL muss mit http:// oder https:// beginnen.'), 'err');
        redirect_route('admin_system');
    }
    Settings::set('update_manifest_url_override', $manifest);
    Settings::set('registration_endpoint_override', $reg);
    Settings::set('update_check_disabled', (bool) post('update_disabled'));
    Settings::set('registration_disabled', (bool) post('registration_disabled'));
    flash(t('system.flash_endpoints_saved', 'Endpoints gespeichert.'));
    redirect_route('admin_system');
}

/**
 * Save the mail (SMTP) configuration (#107). Leaving the SMTP host blank
 * is valid and falls back to PHP's `mail()`; the admin only needs to pick
 * the host when the sendmail path is unavailable or hands outbound off to
 * a smarthost.
 */
function ctrl_mail_save(): void
{
    require_perm('admin.system');
    csrf_check();

    $from = trim((string) post('mail_from'));
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        flash(t('mail.err_from_invalid', 'Die From-Adresse ist keine gueltige E-Mail-Adresse.'), 'err');
        redirect_route('admin_system');
    }
    $port = (int) post('mail_port');
    if ($port < 0 || $port > 65535) {
        flash(t('mail.err_port_invalid', 'Port muss zwischen 0 und 65535 liegen.'), 'err');
        redirect_route('admin_system');
    }

    // An empty password field means "keep current" — don't let a redraw of
    // the admin form clobber a working credential when the admin only
    // changed, say, the host.
    $existing = Mail::config();
    $pwInput = (string) post('mail_pass');
    $pass = $pwInput !== '' ? $pwInput : $existing['pass'];

    Mail::save([
        'from'      => $from,
        'from_name' => trim((string) post('mail_from_name')),
        'host'      => trim((string) post('mail_host')),
        'port'      => $port,
        'user'      => trim((string) post('mail_user')),
        'pass'      => $pass,
        'security'  => (string) post('mail_security', 'none'),
    ]);
    flash(t('mail.flash_saved', 'Mail-Einstellungen gespeichert.'));
    redirect_route('admin_system');
}

/**
 * Send a test mail to the admin's own address (or any address the admin
 * supplied in the form). The result goes straight into the next flash so
 * the operator can tell at a glance whether the config works.
 */
function ctrl_mail_test(): void
{
    require_perm('admin.system');
    csrf_check();

    if (!Mail::isConfigured()) {
        flash(t('mail.err_not_configured', 'Bitte zuerst die From-Adresse speichern.'), 'err');
        redirect_route('admin_system');
    }

    $target = trim((string) post('test_to'));
    if ($target === '') {
        $target = (string) (Auth::user()['email'] ?? '');
    }
    if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
        flash(t('mail.err_test_recipient', 'Keine gueltige Ziel-Adresse fuer die Test-Mail.'), 'err');
        redirect_route('admin_system');
    }

    $appName = (string) cfg('app_name', 'Timeminator');
    [$ok, $err] = Mail::send(
        $target,
        sprintf(t('mail.test_subject', 'Test-Mail von %s'), $appName),
        sprintf(t('mail.test_body', "Dies ist eine Test-Mail aus %s.\nWenn du sie liest, funktioniert die SMTP-Konfiguration."), $appName)
    );
    if ($ok) {
        flash(sprintf(t('mail.flash_test_sent', 'Test-Mail an %s gesendet.'), $target));
    } else {
        flash(sprintf(t('mail.flash_test_failed', 'Test-Mail fehlgeschlagen (%s). Details stehen im PHP-Fehlerprotokoll.'), $err ?? 'unknown'), 'err');
    }
    redirect_route('admin_system');
}
