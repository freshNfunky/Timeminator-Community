<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * v1.0 F2 Projekt-Cockpit (Issue #160).
 *
 * Smoke-Tests fuer die Kern-Mathematik: Health-Flags, Review-Due, Plan-Matrix
 * Soll/Ist-Aggregation und DateShiftRepo Log-Verhalten.
 */
final class ProjectCockpitTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
    }

    private function seedProject(string $name = 'A', ?float $targetHours = 100.0, ?string $plannedEnd = null): int
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']);
        return DB::insert(
            'INSERT INTO projects (client_id, name, code, target_hours, planned_end, created_at) VALUES (?,?,?,?,?,?)',
            [$cid, $name, 'p-' . uniqid(), $targetHours, $plannedEnd, '2026-01-01 00:00:00']
        );
    }

    public function testFlagsDeadlineOverdueWhenPastAndProgressIncomplete(): void
    {
        $row = ['planned_end' => '2026-01-01', 'progress' => 50.0];
        $flags = cockpit_flags_for($row);
        self::assertTrue($flags['deadline_overdue']);
    }

    public function testFlagsDeadlineNotOverdueWhenProgress100(): void
    {
        $row = ['planned_end' => '2026-01-01', 'progress' => 100.0];
        $flags = cockpit_flags_for($row);
        self::assertFalse($flags['deadline_overdue']);
    }

    public function testFlagsKickoffFromProjectField(): void
    {
        $row = ['kickoff_at' => '2026-05-01', 'closeout_at' => null];
        $flags = cockpit_flags_for($row);
        self::assertTrue($flags['kickoff']);
        self::assertFalse($flags['closeout']);
    }

    public function testReviewDueProjectWithNoProgressTracker(): void
    {
        $pid = $this->seedProject();
        $due = cockpit_review_due_projects();
        $ids = array_column($due, 'project_id');
        self::assertContains($pid, $ids);
    }

    public function testReviewNotDueAfterRecentProgress(): void
    {
        $pid = $this->seedProject();
        Repo::saveProjectTracker($pid, 'progress', 'Erste Meldung', [
            'value' => 50.0, 'date_value' => (new DateTimeImmutable('-5 days'))->format('Y-m-d'),
        ], null);
        $due = cockpit_review_due_projects();
        $ids = array_column($due, 'project_id');
        self::assertNotContains($pid, $ids);
    }

    public function testSetProjectPlannedEndRoundTrips(): void
    {
        $pid = $this->seedProject();
        Repo::setProjectPlannedEnd($pid, '2026-12-31');
        $p = Repo::project($pid);
        self::assertSame('2026-12-31', $p['planned_end']);
    }

    public function testSetProjectHealthFieldOnlyAcceptsWhitelistedFields(): void
    {
        $pid = $this->seedProject();
        Repo::setProjectHealthField($pid, 'kickoff_at', '2026-02-01 10:00:00');
        Repo::setProjectHealthField($pid, 'invalid_field', 'should_not_land');
        $p = Repo::project($pid);
        self::assertSame('2026-02-01 10:00:00', $p['kickoff_at']);
    }

    public function testPlanAllocationRoundTrip(): void
    {
        $pid = $this->seedProject();
        $uid = TestSupport::seedUser('alex');
        $id = PlanRepo::saveAllocation([
            'project_id' => $pid, 'user_id' => $uid, 'period_kind' => 'week',
            'period_start' => '2026-10-26', 'planned_minutes' => 240,
        ]);
        self::assertGreaterThan(0, $id);
        $all = PlanRepo::allocations($pid);
        self::assertCount(1, $all);
        self::assertSame(240, (int) $all[0]['planned_minutes']);

        PlanRepo::deleteAllocation($id, $pid);
        self::assertCount(0, PlanRepo::allocations($pid));
    }

    public function testDeleteAllocationScopedByProject(): void
    {
        $p1 = $this->seedProject('A');
        $p2 = $this->seedProject('B');
        $id = PlanRepo::saveAllocation([
            'project_id' => $p1, 'user_id' => null, 'period_kind' => 'total',
            'period_start' => null, 'planned_minutes' => 100,
        ]);
        // delete with wrong project_id: nothing deleted.
        PlanRepo::deleteAllocation($id, $p2);
        self::assertCount(1, PlanRepo::allocations($p1));
    }

    public function testSollByProjectUserWeekAggregates(): void
    {
        $pid = $this->seedProject();
        $u1 = TestSupport::seedUser('a');
        $u2 = TestSupport::seedUser('b');
        PlanRepo::saveAllocation(['project_id' => $pid, 'user_id' => $u1, 'period_kind' => 'week', 'period_start' => '2026-10-26', 'planned_minutes' => 60]);
        PlanRepo::saveAllocation(['project_id' => $pid, 'user_id' => $u1, 'period_kind' => 'week', 'period_start' => '2026-11-02', 'planned_minutes' => 120]);
        PlanRepo::saveAllocation(['project_id' => $pid, 'user_id' => $u2, 'period_kind' => 'week', 'period_start' => '2026-10-26', 'planned_minutes' => 30]);
        $soll = PlanRepo::sollByProjectUserWeek('2026-10-26', '2026-11-30');
        self::assertSame(60,  (int) $soll[$pid][$u1]['2026-W44']);
        self::assertSame(120, (int) $soll[$pid][$u1]['2026-W45']);
        self::assertSame(30,  (int) $soll[$pid][$u2]['2026-W44']);
    }

    public function testWeekKeysSpanInclusive(): void
    {
        $keys = PlanRepo::weekKeys('2026-10-26', '2026-11-10');
        self::assertNotEmpty($keys);
        self::assertSame('2026-W44', $keys[0]);
        self::assertTrue(in_array('2026-W46', $keys, true));
    }

    public function testPlannedTotalSumsAllAllocations(): void
    {
        $pid = $this->seedProject();
        PlanRepo::saveAllocation(['project_id' => $pid, 'user_id' => null, 'period_kind' => 'total',
            'period_start' => null, 'planned_minutes' => 300]);
        PlanRepo::saveAllocation(['project_id' => $pid, 'user_id' => null, 'period_kind' => 'total',
            'period_start' => null, 'planned_minutes' => 200]);
        self::assertSame(500, PlanRepo::plannedTotal($pid));
    }

    public function testDateShiftLogIfShiftedCreatesEntryWithReason(): void
    {
        $pid = $this->seedProject('A', 100.0, '2026-12-01');
        // Simulate Auth::id() path — unauthenticated returns null, which is
        // acceptable for the log (user_id nullable).
        $result = DateShiftRepo::logIfShifted(
            DateShiftRepo::KIND_PROJECT, $pid, $pid, '2026-12-01', '2026-12-15', 'Scope expanded'
        );
        self::assertSame('logged', $result);
        $log = DateShiftRepo::forProject($pid);
        self::assertCount(1, $log);
        self::assertSame('Scope expanded', $log[0]['reason']);
        self::assertSame(14, DateShiftRepo::diffDays($log[0]['old_end'], $log[0]['new_end']));
    }

    public function testDateShiftFirstSetDoesNotLog(): void
    {
        $pid = $this->seedProject('A', 100.0, null);
        $result = DateShiftRepo::logIfShifted(
            DateShiftRepo::KIND_PROJECT, $pid, $pid, null, '2026-12-15', 'Initial'
        );
        self::assertSame('first_set', $result);
        self::assertCount(0, DateShiftRepo::forProject($pid));
    }

    public function testDateShiftReasonRequired(): void
    {
        $pid = $this->seedProject('A', 100.0, '2026-12-01');
        $this->expectException(RuntimeException::class);
        DateShiftRepo::logIfShifted(DateShiftRepo::KIND_PROJECT, $pid, $pid, '2026-12-01', '2026-12-15', '');
    }

    public function testProgressTrackerWithMoodRoundTrips(): void
    {
        $pid = $this->seedProject();
        $uid = TestSupport::seedUser('alex');
        Repo::saveProjectTracker($pid, 'progress', 'Alles gut', [
            'value'          => 42.0,
            'value_forecast' => 60.0,
            'date_value'     => '2026-10-28',
            'project_mood'   => 4,
            'customer_mood'  => 5,
        ], $uid);
        $trackers = Repo::projectTrackers($pid, 10);
        self::assertCount(1, $trackers);
        self::assertSame(4, $trackers[0]['project_mood']);
        self::assertSame(5, $trackers[0]['customer_mood']);
        self::assertSame(42.0, $trackers[0]['value']);
    }

    public function testCockpitRowComputesZeitPct(): void
    {
        $pid = $this->seedProject('X', 10.0);
        $uid = TestSupport::seedUser('tim');
        $cid = (int) DB::one('SELECT client_id FROM projects WHERE id = ?', [$pid])['client_id'];
        // 300 min = 5h on a 10h target -> 50%
        $tid = DB::insert('INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,?,?,?)',
            [$pid, null, 'T', '2026-01-01 00:00:00']);
        DB::insert(
            'INSERT INTO time_entries (user_id, client_id, project_id, task_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $cid, $pid, $tid, '2026-10-20 09:00:00', '2026-10-20 14:00:00', 300, '2026-10-20 09:00:00', '2026-10-20 09:00:00']
        );
        $rows = cockpit_rows();
        $row = null;
        foreach ($rows as $r) {
            if ($r['project_id'] === $pid) { $row = $r; break; }
        }
        self::assertNotNull($row);
        self::assertSame(5.0, $row['ist_hours']);
        self::assertSame(50.0, $row['zeit_pct']);
    }
}
