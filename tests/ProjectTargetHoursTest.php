<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Projekt-Soll-Stunden (Pro-Sync B.2, #111).
 *
 * Covers:
 *   - `projects.target_hours` column exists on fresh installs (base schema).
 *   - `Repo::saveProject()` round-trips the value, including null.
 *   - `Repo::projectPlanningSnapshot()` only returns active projects with
 *     a target, sums time_entries correctly, and ignores inactive /
 *     unplanned projects.
 */
final class ProjectTargetHoursTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testProjectsHaveTargetHoursColumn(): void
    {
        $cols = DB::all('PRAGMA table_info(projects)');
        $names = array_column($cols, 'name');
        self::assertContains('target_hours', $names);
    }

    public function testSaveProjectRoundTripsTargetHoursAndNullable(): void
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme', '2026-01-01 00:00:00']);

        $pid = Repo::saveProject([
            'client_id' => $cid, 'name' => 'A', 'code' => 'a-' . uniqid(),
            'track' => null, 'color' => null, 'active' => 1, 'target_hours' => 120.5,
        ]);
        $p = Repo::project($pid);
        self::assertSame(120.5, (float) $p['target_hours']);

        Repo::saveProject([
            'client_id' => $cid, 'name' => 'A', 'code' => $p['code'],
            'track' => null, 'color' => null, 'active' => 1, 'target_hours' => null,
        ], $pid);
        $p = Repo::project($pid);
        self::assertNull($p['target_hours']);
    }

    public function testPlanningSnapshotOnlyReturnsActivePlannedProjects(): void
    {
        [$cid, $pidPlanned, $pidUnplanned, $pidInactive] = $this->seedThree();

        $snap = Repo::projectPlanningSnapshot();
        self::assertCount(1, $snap);
        self::assertSame($pidPlanned, $snap[0]['id']);
        self::assertSame(100.0, $snap[0]['target_hours']);
    }

    public function testPlanningSnapshotSumsEntryMinutes(): void
    {
        [$cid, $pidPlanned] = $this->seedThree();
        $uid = TestSupport::seedUser('alice');
        $tid = DB::insert('INSERT INTO tasks (project_id, name, created_at) VALUES (?, ?, ?)',
            [$pidPlanned, 'Build', '2026-01-01 00:00:00']);
        $this->seedEntry($uid, $cid, $pidPlanned, $tid, 180);  // 3h
        $this->seedEntry($uid, $cid, $pidPlanned, $tid,  90);  // 1.5h
        // Entry with NULL duration must not NPE the sum.
        DB::run('INSERT INTO time_entries (user_id, task_id, project_id, client_id, start_ts, note, source, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$uid, $tid, $pidPlanned, $cid, '2026-10-14 10:00:00', '', 'manual', '2026-10-14 10:00:00', '2026-10-14 10:00:00']);

        $snap = Repo::projectPlanningSnapshot();
        self::assertSame(270, $snap[0]['actual_minutes']);
    }

    /** @return array{0:int,1:int,2:int,3:int} client, planned-active, unplanned, inactive-planned */
    private function seedThree(): array
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']);
        $planned = Repo::saveProject([
            'client_id' => $cid, 'name' => 'Planned', 'code' => 'plan-' . uniqid(),
            'track' => null, 'color' => null, 'active' => 1, 'target_hours' => 100.0,
        ]);
        $unplanned = Repo::saveProject([
            'client_id' => $cid, 'name' => 'Unplanned', 'code' => 'noplan-' . uniqid(),
            'track' => null, 'color' => null, 'active' => 1, 'target_hours' => null,
        ]);
        $inactive = Repo::saveProject([
            'client_id' => $cid, 'name' => 'Archived', 'code' => 'arch-' . uniqid(),
            'track' => null, 'color' => null, 'active' => 0, 'target_hours' => 50.0,
        ]);
        return [$cid, $planned, $unplanned, $inactive];
    }

    private function seedEntry(int $uid, int $cid, int $pid, int $tid, int $durationMin): void
    {
        DB::run(
            'INSERT INTO time_entries
                (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min,
                 note, source, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$uid, $tid, $pid, $cid, '2026-10-14 09:00:00', '2026-10-14 10:00:00', $durationMin,
             '', 'manual', '2026-10-14 10:00:00', '2026-10-14 10:00:00']
        );
    }
}
