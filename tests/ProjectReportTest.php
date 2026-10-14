<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Projekt-Report: Ist vs Plan (Pro-Sync B.3, #112).
 *
 * Exercises `ProjectReport::rows()` end-to-end against a seeded DB: the
 * query must only return active+planned projects, must sum
 * `time_entries.duration_min`, classify ok/warn/over correctly, and
 * honour client + date-range filters.
 */
final class ProjectReportTest extends TestCase
{
    private int $clientA;
    private int $clientB;
    private int $uid;
    /** @var array<int,int> */
    private array $taskCache = [];

    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        $this->clientA   = $this->seedClient('Client A');
        $this->clientB   = $this->seedClient('Client B');
        $this->uid       = TestSupport::seedUser('alice');
        $this->taskCache = [];
    }

    public function testClassifyMapsPercentageBuckets(): void
    {
        self::assertSame('ok',   ProjectReport::classify(0));
        self::assertSame('ok',   ProjectReport::classify(79));
        self::assertSame('warn', ProjectReport::classify(80));
        self::assertSame('warn', ProjectReport::classify(100));
        self::assertSame('over', ProjectReport::classify(101));
        self::assertSame('over', ProjectReport::classify(250));
    }

    public function testRowsReturnsOnlyActivePlannedProjects(): void
    {
        $this->seedProject($this->clientA, 'Planned',   true,  100.0);
        $this->seedProject($this->clientA, 'Unplanned', true,  null);
        $this->seedProject($this->clientA, 'Archived',  false, 50.0);

        $rows = ProjectReport::rows();
        self::assertCount(1, $rows);
        self::assertSame('Planned', $rows[0]['name']);
    }

    public function testThreeProjectsCoverAllStatuses(): void
    {
        $okId   = $this->seedProject($this->clientA, 'OK',   true, 100.0);
        $warnId = $this->seedProject($this->clientA, 'Warn', true, 100.0);
        $overId = $this->seedProject($this->clientA, 'Over', true, 100.0);

        $this->seedEntryMinutes($okId,   30 * 60); // 30h / 100 => 30%  => ok
        $this->seedEntryMinutes($warnId, 90 * 60); // 90h / 100 => 90%  => warn
        $this->seedEntryMinutes($overId, 150 * 60); // 150h / 100 => 150% => over

        $rows   = ProjectReport::rows();
        $byName = [];
        foreach ($rows as $r) { $byName[$r['name']] = $r; }

        self::assertSame('ok',   $byName['OK']['status']);
        self::assertSame( 30,    $byName['OK']['percent']);
        self::assertSame('warn', $byName['Warn']['status']);
        self::assertSame( 90,    $byName['Warn']['percent']);
        self::assertSame('over', $byName['Over']['status']);
        self::assertSame(150,    $byName['Over']['percent']);

        self::assertSame(-70 * 60, $byName['OK']['diff_minutes'],   'under-plan diff is negative');
        self::assertSame( 50 * 60, $byName['Over']['diff_minutes'], 'over-plan diff is positive');
    }

    public function testClientFilterNarrowsToOneClient(): void
    {
        $this->seedProject($this->clientA, 'A1', true, 10.0);
        $this->seedProject($this->clientB, 'B1', true, 10.0);
        self::assertCount(2, ProjectReport::rows(), 'both without filter');
        self::assertCount(1, ProjectReport::rows($this->clientA));
    }

    public function testDateRangeOnlyCountsEntriesWithinWindow(): void
    {
        $pid = $this->seedProject($this->clientA, 'Windowed', true, 10.0);

        $this->seedEntryAt($pid, '2026-09-30 10:00:00', 60);
        $this->seedEntryAt($pid, '2026-10-01 10:00:00', 120);
        $this->seedEntryAt($pid, '2026-10-02 10:00:00',  30);
        $this->seedEntryAt($pid, '2026-10-10 10:00:00', 240);

        $all     = ProjectReport::rows(null, null, null);
        $onlyOct = ProjectReport::rows(null, '2026-10-01', '2026-10-05');

        self::assertSame(60 + 120 + 30 + 240, $all[0]['actual_minutes']);
        self::assertSame(120 + 30,            $onlyOct[0]['actual_minutes']);
    }

    public function testBogusDateInFilterIsIgnoredNotFatal(): void
    {
        $pid = $this->seedProject($this->clientA, 'Bogus', true, 10.0);
        $this->seedEntryAt($pid, '2026-10-01 10:00:00', 60);
        // "not-a-date" fails the regex and is passed as null — must NOT
        // produce a SQL error, must NOT silently include everything.
        $rows = ProjectReport::rows(null, 'not-a-date', 'nope');
        self::assertSame(60, $rows[0]['actual_minutes']);
    }

    /* ---- helpers ---- */

    private function seedClient(string $name): int
    {
        return DB::insert(
            'INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            [$name, 'c-' . uniqid(), '2026-01-01 00:00:00']
        );
    }

    private function seedProject(int $clientId, string $name, bool $active, ?float $targetH): int
    {
        return Repo::saveProject([
            'client_id'    => $clientId,
            'name'         => $name,
            'code'         => 'p-' . uniqid(),
            'track'        => null,
            'color'        => null,
            'active'       => $active ? 1 : 0,
            'target_hours' => $targetH,
        ]);
    }

    private function seedEntryMinutes(int $pid, int $minutes): void
    {
        $this->seedEntryAt($pid, '2026-10-14 10:00:00', $minutes);
    }

    private function seedEntryAt(int $pid, string $startTs, int $minutes): void
    {
        if (!isset($this->taskCache[$pid])) {
            $this->taskCache[$pid] = DB::insert(
                'INSERT INTO tasks (project_id, name, created_at) VALUES (?, ?, ?)',
                [$pid, 'Build', '2026-01-01 00:00:00']
            );
        }
        $tid = $this->taskCache[$pid];
        $proj = Repo::project($pid);
        DB::run(
            'INSERT INTO time_entries
                (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min,
                 note, source, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$this->uid, $tid, $pid, (int) $proj['client_id'],
             $startTs, $startTs, $minutes,
             '', 'manual', $startTs, $startTs]
        );
    }
}
