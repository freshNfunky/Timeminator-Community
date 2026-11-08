<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Lock the default role catalog + default permission wiring.
 *
 * Felix-Feedback 2026-11-08 (Task #100 Rollen-Matrix): Community kennt
 * jetzt eine feste Rollen-Matrix fuer Admin, Geschaeftsleitung, HR,
 * Projektmanager, Assistenz und Mitarbeiter. Die Defaults in der
 * Rollen-UI muessen genau diese Matrix abbilden. Wer eine Default-
 * Permission wegnimmt, bricht die Erwartungen der Rollen-Views und
 * dieser Test wird rot.
 *
 * Felix-Statement: "in der Rollen-Sicht kann man das ja nochmal
 * entsprechend anpassen" - Default-Overrides durch den Admin bleiben
 * beim Re-Seed erhalten. Auch das pinnt dieser Test.
 */
final class RoleCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testPermissionCatalogContainsEveryExpectedCode(): void
    {
        $catalog = permission_catalog();
        $expected = [
            'entries.manage', 'stats.view', 'stats.view_company',
            'sentiment.record', 'sentiment.view_team', 'sentiment.view_company',
            'cockpit.project.view', 'cockpit.employee.view', 'cockpit.company.view',
            'structure.manage', 'presets.manage', 'scopes.manage',
            'employees.manage',
            'admin.users', 'admin.roles', 'admin.imports', 'admin.system',
            'scheduler.manage',
        ];
        foreach ($expected as $code) {
            self::assertArrayHasKey($code, $catalog, "Permission-Code $code fehlt im Katalog");
            self::assertCount(2, $catalog[$code], "$code muss [label, group] liefern");
        }
    }

    public function testSyncPermissionsIsIdempotent(): void
    {
        sync_permissions();
        $count1 = (int) DB::scalar('SELECT COUNT(*) FROM permissions');
        sync_permissions();
        $count2 = (int) DB::scalar('SELECT COUNT(*) FROM permissions');
        self::assertSame($count1, $count2, 'sync_permissions darf nicht duplizieren');
        self::assertSame(count(permission_catalog()), $count1,
            'DB muss genau so viele Permissions enthalten wie der Katalog deklariert');
    }

    /** @return list<string> */
    private function permsForRole(string $roleName): array
    {
        $rows = DB::all(
            'SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN roles r ON r.id = rp.role_id
             WHERE r.name = ?
             ORDER BY p.code',
            [$roleName]
        );
        return array_map(static fn($r) => (string) $r['code'], $rows);
    }

    public function testSeedRolesCreatesAllDefaultRoles(): void
    {
        seed_roles();
        foreach (array_keys(default_role_catalog()) as $name) {
            $id = DB::scalar('SELECT id FROM roles WHERE name = ?', [$name]);
            self::assertNotEmpty($id, "Default-Rolle '$name' muss nach seed_roles() existieren");
        }
    }

    public function testAdminRoleHoldsEveryPermission(): void
    {
        seed_roles();
        $adminPerms = $this->permsForRole('admin');
        self::assertEqualsCanonicalizing(
            array_keys(permission_catalog()),
            $adminPerms,
            'Admin muss immer saemtliche Permissions aus dem Katalog besitzen'
        );
    }

    public function testGeschaeftsleitungHasAllExceptSysImportScheduler(): void
    {
        seed_roles();
        $perms = $this->permsForRole('geschaeftsleitung');
        // Enthalten:
        foreach (['cockpit.project.view', 'cockpit.employee.view', 'cockpit.company.view',
                  'structure.manage', 'presets.manage', 'scopes.manage',
                  'employees.manage', 'admin.users', 'admin.roles',
                  'stats.view_company', 'sentiment.view_team', 'sentiment.view_company'] as $code) {
            self::assertContains($code, $perms, "GL muss '$code' haben");
        }
        // Nicht enthalten (reine Admin-Angelegenheiten):
        foreach (['admin.system', 'admin.imports', 'scheduler.manage'] as $code) {
            self::assertNotContains($code, $perms, "GL darf '$code' NICHT haben (Admin-only)");
        }
    }

    public function testHrSeesEmployeeCockpitAndCompanyStatsButNoProjectCockpit(): void
    {
        seed_roles();
        $perms = $this->permsForRole('hr');
        self::assertContains('cockpit.employee.view', $perms);
        self::assertContains('employees.manage', $perms);
        self::assertContains('admin.users', $perms);
        self::assertContains('stats.view_company', $perms);
        self::assertContains('sentiment.view_company', $perms);
        self::assertNotContains('cockpit.project.view', $perms);
        self::assertNotContains('cockpit.company.view', $perms);
        self::assertNotContains('structure.manage', $perms);
        self::assertNotContains('presets.manage', $perms);
        self::assertNotContains('admin.roles', $perms);
    }

    public function testProjektmanagerSeesProjectCockpitAndStructureNotPresets(): void
    {
        seed_roles();
        $perms = $this->permsForRole('projektmanager');
        self::assertContains('cockpit.project.view', $perms);
        self::assertContains('structure.manage', $perms);
        self::assertContains('sentiment.view_team', $perms);
        self::assertNotContains('presets.manage', $perms, 'Felix: PM darf Presets NICHT');
        self::assertNotContains('cockpit.employee.view', $perms);
        self::assertNotContains('cockpit.company.view', $perms);
        self::assertNotContains('scopes.manage', $perms);
        self::assertNotContains('admin.users', $perms);
        self::assertNotContains('admin.roles', $perms);
        self::assertNotContains('stats.view_company', $perms);
        self::assertNotContains('sentiment.view_company', $perms);
    }

    public function testAssistenzHasBroadOperationalAccessButNoSystemicRights(): void
    {
        // Felix-Feedback 2026-11-08 (Anpassung): "Assistenz hat weitestgehend
        // Rechte der Geschaeftsleitung, aber eben nicht alle. ... alles was
        // systemisch ist oder irgendwelche Voreinstellungen, das darf auch
        // die Assistenz nicht."
        seed_roles();
        $perms = $this->permsForRole('assistenz');

        // Operativer Alltag / Team-Meeting-Sichten: ja.
        foreach (['sentiment.view_team', 'sentiment.view_company', 'stats.view_company',
                  'cockpit.project.view', 'cockpit.employee.view', 'cockpit.company.view',
                  'structure.manage', 'scopes.manage'] as $code) {
            self::assertContains($code, $perms, "Assistenz muss '$code' haben (operativer Alltag)");
        }

        // Systemisch oder sensible Voreinstellungen / Admin-nah: nein.
        foreach (['presets.manage', 'admin.users', 'admin.roles', 'admin.system',
                  'admin.imports', 'scheduler.manage', 'employees.manage'] as $code) {
            self::assertNotContains($code, $perms, "Assistenz darf '$code' NICHT haben (systemisch/sensible Voreinstellung)");
        }
    }

    public function testMitarbeiterKeepsMinimalPerms(): void
    {
        seed_roles();
        $perms = $this->permsForRole('user');
        self::assertEqualsCanonicalizing(
            ['entries.manage', 'stats.view', 'sentiment.record'],
            $perms,
            'Mitarbeiter-Rolle darf nur die Minimal-Permissions haben'
        );
    }

    public function testSeedRolesIsIdempotentAndDoesNotResetAdminCustomizations(): void
    {
        seed_roles();
        $pmId = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', ['projektmanager']);

        // Admin loescht structure.manage von Projektmanager in der UI.
        DB::run(
            'DELETE FROM role_permissions
             WHERE role_id = ? AND permission_id = (SELECT id FROM permissions WHERE code = ?)',
            [$pmId, 'structure.manage']
        );
        $before = $this->permsForRole('projektmanager');
        self::assertNotContains('structure.manage', $before);

        // Zweiter Seed-Durchlauf darf die Anpassung NICHT ueberschreiben.
        seed_roles();
        $after = $this->permsForRole('projektmanager');
        self::assertNotContains('structure.manage', $after,
            'Re-Seed darf Admin-Anpassungen an bestehenden Rollen nicht zurueckdrehen');
    }

    public function testSeedRolesGrantsAdminNewCatalogPermsAfterCatalogExtension(): void
    {
        seed_roles();
        $adminId = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', ['admin']);
        // Simuliere eine neue Permission, die direkt in die DB geschrieben
        // wird (als wuerde ein Patch den Katalog erweitern).
        DB::run('INSERT INTO permissions (code, label, grp) VALUES (?,?,?)',
            ['feature.new', 'Testweise neue Permission', 'Sonstiges']);
        $newId = (int) DB::scalar('SELECT id FROM permissions WHERE code = ?', ['feature.new']);

        // Vor dem Re-Seed hat Admin die neue Permission NICHT.
        $has = (int) DB::scalar('SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$adminId, $newId]);
        self::assertSame(0, $has);

        seed_roles();
        $hasAfter = (int) DB::scalar('SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$adminId, $newId]);
        self::assertSame(1, $hasAfter,
            'Admin-Rolle muss neue Katalog-Permissions beim Re-Seed automatisch erhalten');
    }
}
