<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: Nav-Sichtbarkeit pro Rolle (#105/#106/#107/#108).
 *
 * Felix-Feedback 2026-11-08: "diese Sachen alle dann persistiert werden
 * muessen ueber Regressionstests, dass die dann auch nicht wieder
 * verschwinden." Dieser Test rendert die Nav-Partial fuer jede Default-
 * Rolle und friert die erlaubten / verbotenen Nav-Items ein.
 *
 * Die Permission-Defaults wiederum sind in RoleCatalogTest gepinnt.
 */
final class RoleNavVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    private function nav(string $roleName): string
    {
        TestSupport::loginAsRole($roleName);
        return TestSupport::renderPartial('views/partials/nav.php');
    }

    /**
     * Felix-Feedback 2026-11-08: die Nav verwendet route-Keys als Query-Param,
     * nicht als separate Pfade. Deshalb matchen wir auf `?r=<slug>`, damit
     * ein Teilstring wie 'stats' nicht faelschlich 'statsx' matcht.
     */
    private function assertNavHas(string $nav, string $routeKey, string $roleName): void
    {
        self::assertStringContainsString('?r=' . $routeKey . '"', $nav,
            "$roleName muss Nav-Eintrag '$routeKey' sehen");
    }

    private function assertNavMissing(string $nav, string $routeKey, string $roleName): void
    {
        self::assertStringNotContainsString('?r=' . $routeKey . '"', $nav,
            "$roleName darf Nav-Eintrag '$routeKey' NICHT sehen");
    }

    public function testAdminSeesEverything(): void
    {
        $nav = $this->nav('admin');
        foreach ([
            'cockpit', 'cockpit_employees', 'cockpit_company',
            'stats', 'sentiment', 'scopes',
            'clients', 'projects', 'workpackages', 'tasks', 'presets',
            'admin_users', 'admin_roles', 'imports', 'admin_system', 'admin_cron',
        ] as $slug) {
            $this->assertNavHas($nav, $slug, 'Admin');
        }
    }

    public function testGeschaeftsleitungHidesSystemImportAndScheduler(): void
    {
        $nav = $this->nav('geschaeftsleitung');
        foreach ([
            'cockpit', 'cockpit_employees', 'cockpit_company',
            'stats', 'sentiment', 'scopes',
            'clients', 'projects', 'workpackages', 'tasks', 'presets',
            'admin_users', 'admin_roles',
        ] as $slug) {
            $this->assertNavHas($nav, $slug, 'GL');
        }
        foreach (['imports', 'admin_system', 'admin_cron'] as $slug) {
            $this->assertNavMissing($nav, $slug, 'GL');
        }
    }

    public function testHrSeesEmployeeCockpitAndUsersButNoProjectStructure(): void
    {
        $nav = $this->nav('hr');
        foreach (['cockpit_employees', 'stats', 'sentiment', 'admin_users'] as $slug) {
            $this->assertNavHas($nav, $slug, 'HR');
        }
        foreach ([
            'cockpit', 'cockpit_company',
            'clients', 'projects', 'workpackages', 'tasks', 'presets',
            'scopes', 'admin_roles', 'imports', 'admin_system', 'admin_cron',
        ] as $slug) {
            $this->assertNavMissing($nav, $slug, 'HR');
        }
    }

    public function testProjektmanagerSeesProjectCockpitStructureAndTeamSentiment(): void
    {
        $nav = $this->nav('projektmanager');
        foreach ([
            'cockpit', 'stats', 'sentiment',
            'clients', 'projects', 'workpackages', 'tasks',
        ] as $slug) {
            $this->assertNavHas($nav, $slug, 'PM');
        }
        foreach ([
            'cockpit_employees', 'cockpit_company',
            'presets', 'scopes',
            'admin_users', 'admin_roles', 'imports', 'admin_system', 'admin_cron',
        ] as $slug) {
            $this->assertNavMissing($nav, $slug, 'PM');
        }
    }

    public function testAssistenzSeesAllCockpitsAndScopesButNoAdmin(): void
    {
        $nav = $this->nav('assistenz');
        foreach ([
            'cockpit', 'cockpit_employees', 'cockpit_company',
            'stats', 'sentiment', 'scopes',
            'clients', 'projects', 'workpackages', 'tasks',
        ] as $slug) {
            $this->assertNavHas($nav, $slug, 'Assistenz');
        }
        foreach ([
            'presets',
            'admin_users', 'admin_roles', 'imports', 'admin_system', 'admin_cron',
        ] as $slug) {
            $this->assertNavMissing($nav, $slug, 'Assistenz');
        }
    }

    public function testMitarbeiterSeesOnlyDashboardEntriesStatsAndOwnSentiment(): void
    {
        $nav = $this->nav('user');
        foreach (['stats', 'sentiment'] as $slug) {
            $this->assertNavHas($nav, $slug, 'Mitarbeiter');
        }
        foreach ([
            'cockpit', 'cockpit_employees', 'cockpit_company',
            'scopes',
            'clients', 'projects', 'workpackages', 'tasks', 'presets',
            'admin_users', 'admin_roles', 'imports', 'admin_system', 'admin_cron',
        ] as $slug) {
            $this->assertNavMissing($nav, $slug, 'Mitarbeiter');
        }
    }
}
