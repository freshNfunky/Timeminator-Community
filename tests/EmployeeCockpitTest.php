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
