<?php
declare(strict_types=1);

/**
 * Canonical permission catalog.
 *
 * Extend here; `sync_permissions()` rolls new entries into existing
 * installations. The Admin UI (Systemverwaltung -> System) exposes a
 * "Rechte-Katalog synchronisieren"-Button, der denselben Pfad triggert.
 *
 * Felix-Feedback 2026-11-08 (Rollen-Matrix, Task #100/#108): die
 * Permissions-Gruppen und -Codes decken jetzt die kompletten Rollen-
 * Sichten der Community-1.0 ab. Default-Rollen (Geschaeftsleitung, HR,
 * Projektmanager, Assistenz, Mitarbeiter) werden darueber vorverkabelt
 * und koennen vom Admin in der Rollen-UI uebersteuert werden.
 */
function permission_catalog(): array
{
    return [
        // Zeiterfassung (jeder darf eigene Zeiten + eigene Stimmung).
        'entries.manage'        => ['Zeiteintraege buchen und bearbeiten',                 'Zeiterfassung'],
        'stats.view'            => ['Statistik und Auswertungen (eigene) ansehen',         'Zeiterfassung'],
        'stats.view_company'    => ['Statistik unternehmensweit ansehen',                  'Zeiterfassung'],
        'sentiment.record'      => ['Eigene Stimmung erfassen und ansehen',                'Zeiterfassung'],
        'sentiment.view_team'   => ['Team-Stimmung aggregiert ansehen',                    'Zeiterfassung'],
        'sentiment.view_company'=> ['Belegschafts-Stimmung aggregiert ansehen',            'Zeiterfassung'],

        // Cockpits.
        'cockpit.project.view'  => ['Projekt-Cockpit ansehen',                             'Cockpit'],
        'cockpit.employee.view' => ['Mitarbeiter-Cockpit ansehen',                         'Cockpit'],
        'cockpit.company.view'  => ['Firmen-Cockpit ansehen',                              'Cockpit'],

        // Struktur / Stammdaten.
        'structure.manage'      => ['Kunden, Projekte und Aufgaben verwalten',             'Struktur'],
        'presets.manage'        => ['Presets (Default-Einstellungen) verwalten',           'Struktur'],
        'scopes.manage'         => ['Nachweis-Sichten verwalten',                          'Struktur'],

        // Personal (HR-naher Edit der Vertragsdaten).
        'employees.manage'      => ['Mitarbeiter-Vertragsdaten (Stunden, Urlaub) verwalten','Personal'],

        // Administration.
        'admin.users'           => ['Benutzer verwalten',                                   'Administration'],
        'admin.roles'           => ['Rollen und Rechte verwalten',                          'Administration'],
        'admin.imports'         => ['CSV-Importe hochladen und zuruecknehmen',              'Administration'],
        'admin.system'          => ['System, Updates und Registrierung',                    'Administration'],
        'scheduler.manage'      => ['Scheduler-Jobs ansehen und ausloesen',                 'Administration'],
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

/**
 * Default-Rollen + Default-Permission-Zuweisungen.
 *
 * Felix-Feedback 2026-11-08 (Task #100 Rollen-Matrix): Community kennt
 * jetzt sechs Standard-Rollen, die der Admin in der Rollen-UI weiter
 * anpassen kann. Idempotent:
 *   - fehlt eine Rolle -> wird mit ihren Default-Permissions angelegt.
 *   - existiert die Rolle bereits -> bestehende Zuweisungen bleiben
 *     unveraendert, damit Admin-Overrides nicht platt gemacht werden.
 *
 * @return array<string,int> name => role id
 */
function default_role_catalog(): array
{
    return [
        'admin' => [
            'label' => 'Administrator',
            'perms' => array_keys(permission_catalog()), // alles
        ],
        'geschaeftsleitung' => [
            'label' => 'Geschaeftsleitung',
            'perms' => [
                'entries.manage', 'stats.view', 'stats.view_company',
                'sentiment.record', 'sentiment.view_team', 'sentiment.view_company',
                'cockpit.project.view', 'cockpit.employee.view', 'cockpit.company.view',
                'structure.manage', 'presets.manage', 'scopes.manage',
                'employees.manage',
                'admin.users', 'admin.roles',
            ],
        ],
        'hr' => [
            'label' => 'Personalabteilung',
            'perms' => [
                'entries.manage', 'stats.view', 'stats.view_company',
                'sentiment.record', 'sentiment.view_company',
                'cockpit.employee.view',
                'employees.manage',
                'admin.users',
            ],
        ],
        'projektmanager' => [
            'label' => 'Projektmanager',
            'perms' => [
                'entries.manage', 'stats.view',
                'sentiment.record', 'sentiment.view_team',
                'cockpit.project.view',
                'structure.manage',
            ],
        ],
        'assistenz' => [
            // Felix-Feedback 2026-11-08 (Anpassung): "Assistenz hat weitestgehend
            // Rechte der Geschaeftsleitung, aber eben nicht alle. ... alles was
            // systemisch ist oder irgendwelche Voreinstellungen, das darf auch
            // die Assistenz nicht." -> Operatives Alltagsgeschaeft ja, Systemisches
            // und sensible Voreinstellungen (Presets, Rollen, Users, Vertragsdaten)
            // nein.
            'label' => 'Assistenz der Geschaeftsleitung',
            'perms' => [
                'entries.manage', 'stats.view', 'stats.view_company',
                'sentiment.record', 'sentiment.view_team', 'sentiment.view_company',
                'cockpit.project.view', 'cockpit.employee.view', 'cockpit.company.view',
                'structure.manage',
                'scopes.manage',
            ],
        ],
        'user' => [
            'label' => 'Mitarbeiter',
            'perms' => ['entries.manage', 'stats.view', 'sentiment.record'],
        ],
    ];
}

/** Create default roles and their default permissions. Idempotent. */
function seed_roles(): void
{
    sync_permissions();
    $ts = now();

    $driver = DB::driver();
    $insertIgnore = $driver === 'mysql'
        ? 'INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?) ON DUPLICATE KEY UPDATE role_id = role_id'
        : 'INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?) ON CONFLICT DO NOTHING';

    foreach (default_role_catalog() as $name => $def) {
        $roleId = (int) (DB::scalar('SELECT id FROM roles WHERE name = ?', [$name]) ?: 0);
        $isNew = $roleId === 0;
        if ($isNew) {
            $roleId = DB::insert(
                'INSERT INTO roles (name, label, is_system, created_at) VALUES (?,?,?,?)',
                [$name, $def['label'], 1, $ts]
            );
        }

        // Nur bei brandneuen Rollen die Default-Permissions setzen. Bei
        // bestehenden Rollen bleiben Admin-Anpassungen aus der UI erhalten.
        if ($isNew) {
            foreach ($def['perms'] as $code) {
                $pid = (int) DB::scalar('SELECT id FROM permissions WHERE code = ?', [$code]);
                if ($pid) {
                    DB::run($insertIgnore, [$roleId, $pid]);
                }
            }
        }
    }

    // Safety net: der Admin bekommt immer ALLE Permissions, auch bei neuen
    // Katalog-Eintraegen. Verhindert dass eine nachgezogene Permission nur
    // noch via Auth::can-Bypass greift.
    $adminId = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', ['admin']);
    if ($adminId) {
        foreach (DB::all('SELECT id FROM permissions') as $p) {
            DB::run($insertIgnore, [$adminId, (int) $p['id']]);
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
