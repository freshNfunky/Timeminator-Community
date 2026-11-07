<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * v1.0 F1 Mitarbeiter-Cockpit (Issue #158).
 *
 * Pins the core account math — vacation balance, overtime balance, daily
 * target under working-days + holidays + absences — so a careless refactor
 * cannot silently change payroll-adjacent numbers.
 */
final class EmployeeCockpitTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        Workdays::override(null);
    }

    protected function tearDown(): void
    {
        Workdays::override(null);
    }

    public function testWorkdaysDefaultIsMondayFriday(): void
    {
        self::assertSame([1, 2, 3, 4, 5], Workdays::days());
        self::assertTrue(Workdays::isWorkday('2026-11-02'));   // Monday
        self::assertFalse(Workdays::isWorkday('2026-11-08'));  // Sunday
        self::assertSame(5, Workdays::perWeek());
    }

    public function testWorkdaysReadsCommunityWorkingDaysSetting(): void
    {
        Settings::set('working_days', '1,2,3,4');  // 4-day week
        self::assertSame([1, 2, 3, 4], Workdays::days());
        self::assertFalse(Workdays::isWorkday('2026-11-06'));  // Friday now off
        self::assertSame(4, Workdays::perWeek());
    }

    public function testTermsAtReturnsLatestValidEntry(): void
    {
        $uid = TestSupport::seedUser('alice');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0);
        EmployeeRepo::saveTerms($uid, '2026-07-01', EmployeeRepo::TYPE_EMPLOYEE, 32.0, 25.0);

        self::assertSame('2026-01-01', EmployeeRepo::termsAt($uid, '2026-03-15')['valid_from']);
        self::assertSame('2026-07-01', EmployeeRepo::termsAt($uid, '2026-10-28')['valid_from']);
        self::assertNull(EmployeeRepo::termsAt($uid, '2025-12-31'));
    }

    public function testDailyTargetDividesWeeklyHoursByWorkdays(): void
    {
        $uid = TestSupport::seedUser('bob');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0);
        $terms = EmployeeRepo::termsAt($uid, '2026-06-15');
        self::assertEqualsWithDelta(8.0, EmployeeRepo::dailyTarget($terms), 0.001);
    }

    public function testFreelancerHasNoAccountsAndNoOvertime(): void
    {
        $uid = TestSupport::seedUser('fredi');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_FREELANCER, 0.0, 0.0);
        $terms = EmployeeRepo::termsAt($uid, '2026-06-15');
        self::assertFalse(EmployeeRepo::hasAccounts($terms));
        self::assertFalse(EmployeeRepo::hasOvertimeAccount($terms));
    }

    public function testExecHasVacationAccountButNoOvertime(): void
    {
        $uid = TestSupport::seedUser('ellen');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EXEC, 40.0, 30.0);
        $terms = EmployeeRepo::termsAt($uid, '2026-06-15');
        self::assertTrue(EmployeeRepo::hasAccounts($terms));
        self::assertFalse(EmployeeRepo::hasOvertimeAccount($terms));
    }

    public function testVacationRemainingHonorsCarryoverTakenAndPlanned(): void
    {
        $uid = TestSupport::seedUser('vera');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0);
        EmployeeRepo::saveCarryover($uid, 2026, 3.0, 0.0);
        $this->seedAbsenceCategory('urlaub', 'vacation');
        $catId = (int) DB::one("SELECT id FROM absence_categories WHERE ckey = 'urlaub'")['id'];

        // 5 Tage genommen (Mo-Fr), 3 Tage spaeter geplant.
        $this->insertAbsence($uid, $catId, '2026-03-02', '2026-03-06');  // 5 workdays
        $this->insertAbsence($uid, $catId, '2026-12-21', '2026-12-23');  // 3 workdays

        $summary = AccountRepo::summary($uid, '2026-10-28');
        self::assertNotNull($summary['vacation']);
        self::assertEqualsWithDelta(25.0, $summary['vacation']['entitlement'], 0.01);
        self::assertEqualsWithDelta( 3.0, $summary['vacation']['carryover'],   0.01);
        self::assertEqualsWithDelta( 5.0, $summary['vacation']['taken'],       0.01);
        self::assertEqualsWithDelta( 3.0, $summary['vacation']['planned'],     0.01);
        self::assertEqualsWithDelta(20.0, $summary['vacation']['remaining'],   0.01);
    }

    public function testOvertimeOnlyForEmploymentTypeEmployee(): void
    {
        $uid = TestSupport::seedUser('oliver');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EXEC, 40.0, 25.0);
        $summary = AccountRepo::summary($uid, '2026-10-28');
        self::assertNull($summary['overtime'], 'Exec hat kein Ueberstundenkonto.');
    }

    public function testBookedHoursSumProjectAndProcessEntries(): void
    {
        $uid = TestSupport::seedUser('tim');
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)', ['Acme', 'acme', '2026-01-01 00:00:00']);
        $pid = DB::insert('INSERT INTO projects (client_id, name, code, created_at) VALUES (?, ?, ?, ?)', [$cid, 'Proj', 'proj-1', '2026-01-01 00:00:00']);
        $wpid = DB::insert('INSERT INTO work_packages (project_id, code, name, created_at) VALUES (?, ?, ?, ?)', [$pid, 'default', 'Default', '2026-01-01 00:00:00']);
        $tid = DB::insert('INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,?,?,?)', [$pid, $wpid, 'Task', '2026-01-01 00:00:00']);
        DB::insert('INSERT INTO time_entries (user_id, client_id, project_id, task_id, start_ts, end_ts, duration_min, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $cid, $pid, $tid, '2026-10-26 09:00:00', '2026-10-26 17:00:00', 480, '2026-10-26 09:00:00', '2026-10-26 09:00:00']);
        DB::insert('INSERT INTO time_entries (user_id, client_id, project_id, task_id, start_ts, end_ts, duration_min, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $cid, $pid, $tid, '2026-10-27 09:00:00', '2026-10-27 13:00:00', 240, '2026-10-27 09:00:00', '2026-10-27 09:00:00']);
        $pcat = DB::insert('INSERT INTO process_categories (ckey, label, created_at) VALUES (?, ?, ?)', ['admin', 'Admin', '2026-01-01 00:00:00']);
        DB::insert('INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$uid, $pcat, '2026-10-27 13:30:00', '2026-10-27 14:30:00', 60, '2026-10-27 13:30:00', '2026-10-27 13:30:00']);

        self::assertEqualsWithDelta(13.0, AccountRepo::bookedHours($uid, '2026-10-26', '2026-10-27'), 0.01);
    }

    public function testWeeklyStatsReturnsStableStructure(): void
    {
        $uid = TestSupport::seedUser('wendy');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0);
        $out = EmployeeStats::weekly($uid, 13, '2026-10-28');
        self::assertCount(13, $out['weeks']);
        self::assertCount(13, $out['week_starts']);
        self::assertCount(13, $out['target']);
        // All target values populated at 40h for an Angestellt
        foreach ($out['target'] as $t) {
            self::assertSame(40.0, $t);
        }
        self::assertIsArray($out['series']);
    }

    public function testWeeklyStatsStacksMultipleCategoriesAndKeepsTargetLine(): void
    {
        // Felix-Feedback 2026-11-06: der Chart soll mehrere Serien
        // (Projektkategorien, interne Prozesse, Urlaub, Feiertag etc.) als
        // Stapel zeigen, mit einer gestrichelten Soll-Linie auf dem
        // Wochensoll - bei vollstaendiger Buchung liegt der Stapel-Top flach
        // auf der Soll-Linie.
        $uid = TestSupport::seedUser('monty');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0);

        // Zwei Projektkategorien: Kundenprojekt + Internes Projekt
        $cidCustomer = DB::insert(
            'INSERT INTO project_categories (ckey, label, position, needs_client, created_at) VALUES (?,?,?,?,?)',
            ['customer', 'Kundenprojekt', 10, 1, '2026-01-01 00:00:00']
        );
        $cidInternal = DB::insert(
            'INSERT INTO project_categories (ckey, label, position, needs_client, created_at) VALUES (?,?,?,?,?)',
            ['internal', 'Internes Projekt', 20, 0, '2026-01-01 00:00:00']
        );
        $clientId = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']);
        $projCustomer = DB::insert(
            'INSERT INTO projects (client_id, category_id, name, code, created_at) VALUES (?,?,?,?,?)',
            [$clientId, $cidCustomer, 'Pilot', 'p-cust-' . uniqid(), '2026-01-01 00:00:00']
        );
        $projInternal = DB::insert(
            'INSERT INTO projects (client_id, category_id, name, code, created_at) VALUES (?,?,?,?,?)',
            [$clientId, $cidInternal, 'HQ-Umbau', 'p-int-' . uniqid(), '2026-01-01 00:00:00']
        );
        $wpC = DB::insert('INSERT INTO work_packages (project_id, code, name, created_at) VALUES (?,?,?,?)',
            [$projCustomer, 'wp-c', 'WP', '2026-01-01 00:00:00']);
        $wpI = DB::insert('INSERT INTO work_packages (project_id, code, name, created_at) VALUES (?,?,?,?)',
            [$projInternal, 'wp-i', 'WP', '2026-01-01 00:00:00']);
        $tC = DB::insert('INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,?,?,?)',
            [$projCustomer, $wpC, 'T', '2026-01-01 00:00:00']);
        $tI = DB::insert('INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,?,?,?)',
            [$projInternal, $wpI, 'T', '2026-01-01 00:00:00']);
        // 2 Buchungen in KW 43/2026 (26.10.-30.10.) - je 6 h kundenseitig + 2 h intern
        DB::insert(
            'INSERT INTO time_entries (user_id, project_id, task_id, client_id, start_ts, end_ts, duration_min, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $projCustomer, $tC, $clientId, '2026-10-26 09:00:00', '2026-10-26 15:00:00', 360, '2026-10-26 09:00:00', '2026-10-26 09:00:00']
        );
        DB::insert(
            'INSERT INTO time_entries (user_id, project_id, task_id, client_id, start_ts, end_ts, duration_min, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $projInternal, $tI, $clientId, '2026-10-26 15:00:00', '2026-10-26 17:00:00', 120, '2026-10-26 15:00:00', '2026-10-26 15:00:00']
        );

        $out = EmployeeStats::weekly($uid, 13, '2026-10-28');
        $labels = array_map(fn($s) => $s['label'], $out['series']);
        self::assertContains('Kundenprojekt',    $labels, 'Projekt-Kategorie Kundenprojekt muss als eigene Serie geliefert werden');
        self::assertContains('Internes Projekt', $labels, 'Projekt-Kategorie Internes Projekt muss als eigene Serie geliefert werden');
        // Target bleibt 40h in allen Wochen (Vollzeit).
        foreach ($out['target'] as $t) {
            self::assertSame(40.0, $t, 'Wochensoll muss trotz mehrerer Serien erhalten bleiben');
        }
        // Reihenfolge nach project_categories.position (Kundenprojekt pos=10 < Internes pos=20).
        $projSeries = array_values(array_filter($out['series'], fn($s) => in_array($s['label'], ['Kundenprojekt', 'Internes Projekt'], true)));
        self::assertSame('Kundenprojekt',    $projSeries[0]['label']);
        self::assertSame('Internes Projekt', $projSeries[1]['label']);
    }

    public function testEmployeeDetailViewHasProParityElements(): void
    {
        // Felix-Feedback 2026-11-07 (Pro-Screenshot "Sarah Vogel"): die
        // Community-Einzelansicht muss die gleichen Bausteine haben wie Pro:
        //   - Backlink zur Uebersicht
        //   - Infoblock mit Vertragsdaten-Grid (Beschaeftigung, Wochenstunden,
        //     Urlaubsanspruch, Feiertage, gilt-ab)
        //   - vier KPI-Tiles (Ueberstunden, Resturlaub, Krankheit-Jahr, Auslastung)
        //   - Verteilungs-Chart mit "Darstellung"-Dropdown (Stunden/Prozent)
        //   - Donut "Wofuer die Zeit draufging"
        //   - Controller-Pfad ctrl_cockpit_employee + Route cockpit_employee
        $view  = (string) file_get_contents(dirname(__DIR__) . '/views/cockpit/employee_detail.php');
        $ctrl  = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/employees_ctrl.php');
        $index = (string) file_get_contents(dirname(__DIR__) . '/index.php');

        // Route + Controller sind registriert.
        self::assertStringContainsString("'cockpit_employee'", $index, 'Route cockpit_employee fehlt');
        self::assertStringContainsString("'ctrl_cockpit_employee'", $index, 'Controller ctrl_cockpit_employee nicht gemappt');
        self::assertStringContainsString('function ctrl_cockpit_employee', $ctrl, 'Controller-Funktion ctrl_cockpit_employee fehlt');

        // Backlink + Infoblock-Grid.
        self::assertStringContainsString('cockpit.back_to_employees', $view, 'Backlink-Label fehlt');
        self::assertStringContainsString('class="emp-info-grid"', $view, 'Infoblock-Grid fehlt');
        foreach ([
            'cockpit.emp_type',
            'cockpit.emp_weekly_hours',
            'cockpit.emp_vacation_entitlement',
            'cockpit.emp_holiday_region',
            'cockpit.emp_valid_from',
        ] as $key) {
            self::assertStringContainsString($key, $view, "Infoblock-Key $key fehlt");
        }

        // Vier KPI-Tiles (anzahl der Tile-Container). Matcht .emp-tile exakt,
        // nicht die inneren .emp-tile-val/.emp-tile-lbl-Kinder.
        self::assertSame(
            4,
            preg_match_all('/<div class="emp-tile(?: [^"]*)?"/', $view),
            'Mitarbeiter-Cockpit muss genau vier KPI-Tiles rendern'
        );
        foreach ([
            'cockpit.tile_overtime',
            'cockpit.tile_vacation_remaining',
            'cockpit.tile_sick_year',
            'cockpit.tile_utilization',
        ] as $key) {
            self::assertStringContainsString($key, $view, "KPI-Tile-Key $key fehlt");
        }

        // Darstellung-Dropdown (Stunden/Prozent) ersetzt die Checkbox.
        self::assertStringContainsString('id="empWeeksMode"', $view, 'Darstellungs-Dropdown fehlt');
        self::assertStringContainsString('cockpit.chart_mode_hours', $view, 'Mode "Stunden" fehlt');
        self::assertStringContainsString('cockpit.chart_mode_percent', $view, 'Mode "Prozent" fehlt');

        // Donut.
        self::assertStringContainsString('id="chartEmpDonut"', $view, 'Donut-Chart Canvas fehlt');
        self::assertStringContainsString('TmCharts.doughnut(donutCanvas', $view, 'Donut-Render-Call fehlt');

        // Admin-Gate: Nicht-Admins koennen nur die eigene user_id abrufen.
        self::assertMatchesRegularExpression(
            '/if\s*\(!\$all\s*&&\s*\$uid\s*!==\s*\(int\)\s*Auth::id\(\)\)\s*\{\s*\$uid\s*=\s*\(int\)\s*Auth::id\(\)/s',
            $ctrl,
            'Nicht-Admins muessen auf ihre eigene user_id geklemmt werden'
        );
    }

    public function testCarryoverVacationDefaultsToFullEntitlementWhenNotYetStored(): void
    {
        // Felix-Feedback 2026-11-07: im Carryover-Formular soll "Resturlaub
        // (Tage)" fuer neue Jahre den vollen Jahres-Urlaubsanspruch zeigen
        // (Jahresanfang-Zustand), nicht 0. Nur wenn der User schon mal
        // gespeichert hat, zeigt die View den persistierten Wert.
        $uid = TestSupport::seedUser('neena');
        EmployeeRepo::saveTerms($uid, '2024-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 30.0);

        // Noch kein Carryover: Repo liefert null, view default = voller
        // Anspruch aus terms.
        self::assertNull(
            EmployeeRepo::carryoverRow($uid, 2026),
            'Ohne persistierte Zeile muss carryoverRow null liefern - sonst kann die View "unbekannt" nicht erkennen'
        );
        // carryover() (legacy) liefert weiter 0er fuer andere Call-Sites.
        self::assertSame(
            ['vacation_days' => 0.0, 'overtime_hours' => 0.0],
            EmployeeRepo::carryover($uid, 2026),
            'carryover() bleibt rueckwaertskompatibel (kein Schema-Bruch)'
        );

        // Nach dem Speichern: sowohl carryover() als auch carryoverRow()
        // geben die persistierten Werte - auch wenn sie 0 sind.
        EmployeeRepo::saveCarryover($uid, 2026, 0.0, -2.5);
        $row = EmployeeRepo::carryoverRow($uid, 2026);
        self::assertNotNull($row);
        self::assertSame(0.0,  $row['vacation_days']);
        self::assertSame(-2.5, $row['overtime_hours']);
    }

    public function testCarryoverFormDefaultsVacationToTermsEntitlement(): void
    {
        // Felix-Feedback 2026-11-07: der View muss den Fallback auf
        // $current['vacation_days'] expliziert; sonst zeigt das Input-Feld
        // weiter 0.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/partials/forms/employee_terms.php');
        self::assertMatchesRegularExpression(
            "/\\\$carryVacDefault\s*=\s*\\\$carryover\s*!==\s*null\s*\?.*\\\$carryover\['vacation_days'\].*:\s*\(string\)\s*\(\\\$current\['vacation_days'\]/s",
            $view,
            'Carryover-Form muss im "noch nie gespeichert"-Fall auf terms.vacation_days zurueckfallen'
        );
        self::assertStringContainsString(
            'value="<?= h($carryVacDefault) ?>"',
            $view,
            'Default-Variable muss im value-Attribut verwendet werden'
        );
    }

    public function testEmployeeListLinksNameToDetail(): void
    {
        // Felix-Feedback 2026-11-07: Namen in der Tabelle "Alle Mitarbeiter"
        // werden klickbar und fuehren zur Einzelansicht.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/cockpit/employees.php');
        self::assertMatchesRegularExpression(
            '/<a href="[^"]*cockpit_employee[^"]*"[^>]*><strong><\?=\s*h\(\$name\)/',
            $view,
            'Mitarbeiter-Name in der Uebersicht muss auf cockpit_employee verlinken'
        );
    }

    public function testStackedAreaChartHasPercentModeAndTargetDashedLine(): void
    {
        // Felix-Feedback 2026-11-06: Pro-Parity fuer TmCharts.stackedArea.
        // Percent-Mode normiert jede Woche auf 100 %, Target-Linie wird in
        // dem Modus ausgeblendet. View haengt eine Checkbox an die
        // Chart-Controls und reicht spec.percent weiter.
        $js  = (string) file_get_contents(dirname(__DIR__) . '/assets/charts.js');
        $vw  = (string) file_get_contents(dirname(__DIR__) . '/views/cockpit/employees.php');

        // charts.js: percent-Mode-Block in stackedArea.
        self::assertMatchesRegularExpression(
            '/function stackedArea.*?if\s*\(spec\.percent.*?target\s*=\s*\[\].*?unit\s*=\s*\'%\'/s',
            $js,
            'stackedArea muss im percent-Mode auf 100% normieren, target entfaellt'
        );
        // yMax == 100 im percent-Mode.
        self::assertMatchesRegularExpression(
            '/yMax\s*=\s*spec\.percent\s*\?\s*100/',
            $js,
            'yMax muss im percent-Mode fix 100 sein'
        );
        // Target bleibt als gestrichelte Linie gerendert (ohne percent-Mode).
        self::assertStringContainsString('setLineDash([6, 4])', $js, 'gestrichelte Soll-Linie fehlt');

        // View: Checkbox + percent-Wire-up in draw().
        self::assertStringContainsString('id="empWeeksPercent"', $vw, 'percent-Checkbox im Mitarbeiter-Cockpit fehlt');
        self::assertMatchesRegularExpression(
            '/percent:\s*!!\(percentChk\s*&&\s*percentChk\.checked\)/',
            $vw,
            'draw() muss spec.percent aus der Checkbox weiterreichen'
        );
    }

    // -------- fixtures --------

    private function seedAbsenceCategory(string $ckey, string $account): void
    {
        $existing = DB::one('SELECT id FROM absence_categories WHERE ckey = ?', [$ckey]);
        if ($existing) { return; }
        DB::insert('INSERT INTO absence_categories (ckey, label, position, active, account, created_at) VALUES (?,?,10,1,?,?)',
            [$ckey, ucfirst($ckey), $account, '2026-01-01 00:00:00']);
    }

    private function insertAbsence(int $uid, int $catId, string $from, string $to): void
    {
        DB::insert('INSERT INTO absences (user_id, category_id, start_date, end_date, created_at, updated_at) VALUES (?,?,?,?,?,?)',
            [$uid, $catId, $from, $to, '2026-01-01 00:00:00', '2026-01-01 00:00:00']);
    }
}
