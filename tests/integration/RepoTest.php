<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Repo::class)]
final class RepoTest extends TestCase
{
    protected function setUp(): void
    {
        tm_boot_sqlite();
        $this->seedFixtures();
    }

    private function seedFixtures(): void
    {
        DB::run(
            'INSERT INTO users (id, username, password_hash, display_name, role_id, active, created_at) VALUES (?,?,?,?,?,?,?)',
            [1, 'alice', 'x', 'Alice', null, 1, now()]
        );
        DB::run(
            'INSERT INTO users (id, username, password_hash, display_name, role_id, active, created_at) VALUES (?,?,?,?,?,?,?)',
            [2, 'bob', 'x', 'Bob', null, 1, now()]
        );
        DB::run('INSERT INTO clients (id, name, code, active, created_at) VALUES (1, "ACME", "acme", 1, ?)', [now()]);
        DB::run('INSERT INTO projects (id, client_id, name, code, active, created_at) VALUES (1, 1, "P", "p", 1, ?)', [now()]);
        DB::run('INSERT INTO tasks (id, project_id, name, active, created_at) VALUES (1, 1, "T", 1, ?)', [now()]);
    }

    public function testMinutesBetweenRoundsToNearestMinute(): void
    {
        self::assertSame(60, Repo::minutesBetween('2026-09-27 09:00:00', '2026-09-27 10:00:00'));
        self::assertSame(1, Repo::minutesBetween('2026-09-27 09:00:00', '2026-09-27 09:01:00'));
        self::assertSame(0, Repo::minutesBetween('2026-09-27 09:00:00', '2026-09-27 09:00:00'));
    }

    public function testCreateEntryStoresDenormalisedProjectAndClient(): void
    {
        $id = Repo::createEntry(1, 1, '2026-09-27 09:00:00', '2026-09-27 10:00:00', 'note');
        $row = Repo::entry($id);
        self::assertSame(1, (int) $row['user_id']);
        self::assertSame(1, (int) $row['project_id']);
        self::assertSame(1, (int) $row['client_id']);
        self::assertSame(60, (int) $row['duration_min']);
    }

    public function testDeleteEntryUserPredicate(): void
    {
        $id = Repo::createEntry(1, 1, '2026-09-27 09:00:00', '2026-09-27 10:00:00', '');
        // Bob (uid=2) is NOT admin and NOT the owner — must not delete.
        Repo::deleteEntry($id, 2, false);
        self::assertNotNull(Repo::entry($id));
        // Alice (uid=1) is the owner — deletes normally.
        Repo::deleteEntry($id, 1, false);
        self::assertNull(Repo::entry($id));
    }

    public function testDeleteEntryAdminBypassesOwnership(): void
    {
        $id = Repo::createEntry(1, 1, '2026-09-27 09:00:00', '2026-09-27 10:00:00', '');
        // Bob (uid=2) is admin — must delete Alice's entry.
        Repo::deleteEntry($id, 2, true);
        self::assertNull(Repo::entry($id));
    }

    public function testTimerStartAndStop(): void
    {
        $id = Repo::startTimer(1, 1, 'working');
        $running = Repo::running(1);
        self::assertNotNull($running);
        self::assertSame($id, (int) $running['id']);
        self::assertNull($running['end_ts']);

        // Simulate some elapsed time by rewinding start_ts one hour.
        DB::run('UPDATE time_entries SET start_ts = ? WHERE id = ?', ['2026-09-27 08:00:00', $id]);
        self::assertTrue(Repo::stopTimer(1));

        $stopped = Repo::entry($id);
        self::assertNotNull($stopped['end_ts']);
        self::assertGreaterThan(0, (int) $stopped['duration_min']);
    }

    public function testEntriesFilterByProject(): void
    {
        Repo::createEntry(1, 1, '2026-09-27 09:00:00', '2026-09-27 10:00:00', 'a');
        DB::run('INSERT INTO projects (id, client_id, name, code, active, created_at) VALUES (2, 1, "P2", "p2", 1, ?)', [now()]);
        DB::run('INSERT INTO tasks (id, project_id, name, active, created_at) VALUES (2, 2, "T2", 1, ?)', [now()]);
        Repo::createEntry(1, 2, '2026-09-27 11:00:00', '2026-09-27 12:00:00', 'b');

        $rows = Repo::entries(['project_id' => 1]);
        self::assertCount(1, $rows);
        self::assertSame('a', $rows[0]['note']);
    }
}
