<?php
declare(strict_types=1);

/** Canonical permission catalog. Extend here; run a resync to apply. */
function permission_catalog(): array
{
    return [
        'entries.manage'   => ['Zeiteintraege buchen und bearbeiten', 'Zeiterfassung'],
        'stats.view'       => ['Statistik und Auswertungen ansehen',   'Zeiterfassung'],
        'structure.manage' => ['Kunden, Projekte und Aufgaben verwalten', 'Struktur'],
        'scopes.manage'    => ['Nachweis-Sichten verwalten',           'Struktur'],
        'admin.users'      => ['Benutzer verwalten',                    'Administration'],
        'admin.roles'      => ['Rollen und Rechte verwalten',          'Administration'],
        'admin.system'     => ['System, Updates und Registrierung',     'Administration'],
    ];
}

/** Insert any missing permissions from the catalog. */
function sync_permissions(): void
{
    foreach (permission_catalog() as $code => [$label, $grp]) {
        $exists = DB::scalar('SELECT id FROM permissions WHERE code = ?', [$code]);
        if (!$exists) {
            DB::run('INSERT INTO permissions (code, label, grp) VALUES (?,?,?)', [$code, $label, $grp]);
        } else {
            DB::run('UPDATE permissions SET label = ?, grp = ? WHERE code = ?', [$label, $grp, $code]);
        }
    }
}

/** Create default roles (admin, user) and their permissions. Idempotent. */
function seed_roles(): void
{
    sync_permissions();
    $ts = now();

    $adminId = (int) (DB::scalar('SELECT id FROM roles WHERE name = ?', ['admin']) ?: 0);
    if (!$adminId) {
        $adminId = DB::insert('INSERT INTO roles (name, label, is_system, created_at) VALUES (?,?,?,?)',
            ['admin', 'Administrator', 1, $ts]);
    }
    $userId = (int) (DB::scalar('SELECT id FROM roles WHERE name = ?', ['user']) ?: 0);
    if (!$userId) {
        $userId = DB::insert('INSERT INTO roles (name, label, is_system, created_at) VALUES (?,?,?,?)',
            ['user', 'Benutzer', 1, $ts]);
    }

    // Admin gets every permission (can() also bypasses, this is for clarity).
    $allPerms = DB::all('SELECT id FROM permissions');
    foreach ($allPerms as $p) {
        DB::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)
                 ' . (DB::driver() === 'mysql' ? 'ON DUPLICATE KEY UPDATE role_id = role_id'
                                               : 'ON CONFLICT DO NOTHING'),
            [$adminId, (int) $p['id']]);
    }
    // Default user role: book time + view stats.
    foreach (['entries.manage', 'stats.view'] as $code) {
        $pid = (int) DB::scalar('SELECT id FROM permissions WHERE code = ?', [$code]);
        if ($pid) {
            DB::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)
                     ' . (DB::driver() === 'mysql' ? 'ON DUPLICATE KEY UPDATE role_id = role_id'
                                                   : 'ON CONFLICT DO NOTHING'),
                [$userId, $pid]);
        }
    }
}

/** Create the first admin user. Returns the new user id. */
function seed_admin_user(string $username, string $displayName, string $password): int
{
    $adminRole = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', ['admin']);
    return DB::insert(
        'INSERT INTO users (username, password_hash, display_name, role_id, active, created_at)
         VALUES (?,?,?,?,?,?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), $displayName, $adminRole, 1, now()]
    );
}
