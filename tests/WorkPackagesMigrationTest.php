<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Issue #65 — the Arbeitspaket layer. Covers both the schema-additive
 * side of the migration (idempotent on a fresh install whose master
 * schema already carries the columns) and the data backfill side
 * (every project gets a default WP, existing tasks + time_entries are
 * remapped).
 */
final class WorkPackagesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
    }

    private static function hasColumn(string $table, string $column): bool
    {
        foreach (DB::all("PRAGMA table_info($table)", []) as $c) {
            if (($c['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }

    public function testWorkPackagesTableExistsAfterBaseSchemaApplied(): void
    {
        // Fresh install: schema master already includes work_packages +
        // task.work_package_id + time_entries.work_package_id.
        $rows = DB::all("SELECT name FROM sqlite_master WHERE type='table' AND name='work_packages'", []);
        self::assertCount(1, $rows);
        self::assertTrue(self::hasColumn('tasks', 'work_package_id'));
        self::assertTrue(self::hasColumn('time_entries', 'work_package_id'));
    }

    public function testBackfillCreatesDefaultWorkPackageAndRemapsRows(): void
    {
        $uid = TestSupport::seedUser();
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)', ['Acme', 'ACM', '2026-10-01 00:00:00']);
        $pid = DB::insert('INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$cid, 'Website', 'WEB', 1, '2026-10-01 00:00:00']);
        // Simulate a legacy task + entry that pre-dates the WP layer.
        $tid = DB::insert('INSERT INTO tasks (project_id, name, active, created_at) VALUES (?,?,?,?)',
            [$pid, 'Konzept', 1, '2026-10-01 00:00:00']);
        DB::insert('INSERT INTO time_entries (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$uid, $tid, $pid, $cid, '2026-10-01 09:00:00', '2026-10-01 10:00:00', 60, '', 'manual', '2026-10-01 10:00:00', '2026-10-01 10:00:00']);

        $ran = Updater::runMigrations();
        self::assertGreaterThanOrEqual(1, $ran, 'at least the WP migration should have fired on first run');

        $wpId = (int) DB::scalar('SELECT id FROM work_packages WHERE project_id = ? AND code = ?', [$pid, 'default']);
        self::assertGreaterThan(0, $wpId);
        self::assertSame('Allgemein', DB::scalar('SELECT name FROM work_packages WHERE id = ?', [$wpId]));

        self::assertSame($wpId, (int) DB::scalar('SELECT work_package_id FROM tasks WHERE id = ?', [$tid]));
        self::assertSame($wpId, (int) DB::scalar('SELECT work_package_id FROM time_entries WHERE task_id = ?', [$tid]));
    }

    public function testMigrationIsIdempotent(): void
    {
        $first = Updater::runMigrations();
        self::assertGreaterThanOrEqual(1, $first);
        self::assertSame(0, Updater::runMigrations(), 'second run must be a no-op');
    }

    public function testRepoTaskSaveCarriesWorkPackageId(): void
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)', ['Acme', 'ACM', '2026-10-01 00:00:00']);
        $pid = DB::insert('INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$cid, 'Website', 'WEB', 1, '2026-10-01 00:00:00']);
        Updater::runMigrations();
        // Allgemein was auto-created in the backfill; use its id.
        $defaultWp = (int) DB::scalar('SELECT id FROM work_packages WHERE project_id = ? AND code = ?', [$pid, 'default']);

        $newWp = Repo::saveWorkPackage([
            'project_id' => $pid, 'code' => 'phase-1', 'name' => 'Phase 1', 'active' => 1,
        ]);
        self::assertGreaterThan(0, $newWp);
        self::assertNotSame($defaultWp, $newWp);

        $tid = Repo::saveTask([
            'project_id' => $pid, 'work_package_id' => $newWp,
            'name' => 'Konzept', 'kind' => null, 'active' => 1,
        ]);
        $task = Repo::task($tid);
        self::assertSame($newWp, (int) $task['work_package_id']);
        self::assertSame('Phase 1', $task['work_package_name']);
    }

    public function testCreateEntryDenormalizesWorkPackageOntoTimeEntry(): void
    {
        $uid = TestSupport::seedUser();
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)', ['Acme', 'ACM', '2026-10-01 00:00:00']);
        $pid = DB::insert('INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$cid, 'Website', 'WEB', 1, '2026-10-01 00:00:00']);
        Updater::runMigrations();
        $wp = Repo::saveWorkPackage(['project_id' => $pid, 'code' => 'phase-1', 'name' => 'Phase 1', 'active' => 1]);
        $tid = Repo::saveTask(['project_id' => $pid, 'work_package_id' => $wp, 'name' => 'Konzept', 'kind' => null, 'active' => 1]);

        $eid = Repo::createEntry($uid, $tid, '2026-10-01 09:00:00', '2026-10-01 10:00:00', '');
        $entry = Repo::entry($eid);
        self::assertSame($wp, (int) $entry['work_package_id']);
        self::assertSame('Phase 1', $entry['work_package_name']);
    }
}

