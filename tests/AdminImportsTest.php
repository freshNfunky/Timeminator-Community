<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Admin → Importe-Übersicht (Pro-Sync A.2, #108).
 *
 * Verifies the DB-visible contract: new batches carry user_id, the index
 * query joins the user name correctly, and the filter clauses apply the
 * expected restrictions. The CSV-parse branch is exercised by the existing
 * imports controller (unchanged in A.2), so it is not re-tested here.
 */
final class AdminImportsTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testImportBatchesHaveUserIdColumn(): void
    {
        // The column must exist on both fresh installs (base schema) and
        // on upgraded installs via the migration — this test runs the base
        // schema, so if the base is missing the column, we fail here.
        $cols = DB::all('PRAGMA table_info(import_batches)');
        $names = array_column($cols, 'name');
        self::assertContains('user_id', $names);
    }

    public function testBatchInsertKeepsUserIdAndJoinsBack(): void
    {
        $uid = TestSupport::seedUser('alice');
        $batchId = DB::insert(
            'INSERT INTO import_batches (label, source, note, user_id, created_at)
             VALUES (?, ?, ?, ?, ?)',
            ['test.csv', 'csv', null, $uid, '2026-10-14 10:00:00']
        );
        $row = DB::one(
            'SELECT b.*, u.username AS user_username
               FROM import_batches b
               LEFT JOIN users u ON u.id = b.user_id
              WHERE b.id = ?',
            [$batchId]
        );
        self::assertSame($uid, (int) $row['user_id']);
        self::assertSame('alice', $row['user_username']);
    }

    public function testLegacyBatchWithoutUserIdSurvives(): void
    {
        // Rows imported before A.2 have user_id = NULL. They must not
        // disappear from the overview, just render "unknown" in the view.
        $batchId = DB::insert(
            'INSERT INTO import_batches (label, source, created_at) VALUES (?, ?, ?)',
            ['legacy.csv', 'csv', '2026-01-01 10:00:00']
        );
        $row = DB::one(
            'SELECT b.*, u.username AS user_username
               FROM import_batches b
               LEFT JOIN users u ON u.id = b.user_id
              WHERE b.id = ?',
            [$batchId]
        );
        self::assertNull($row['user_id']);
        self::assertNull($row['user_username']);
    }

    public function testIndexFiltersByUser(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        self::seedBatch('a1.csv', $alice, '2026-10-14 10:00:00');
        self::seedBatch('a2.csv', $alice, '2026-10-14 11:00:00');
        self::seedBatch('b1.csv', $bob,   '2026-10-14 12:00:00');

        $rows = DB::all('SELECT * FROM import_batches WHERE user_id = ?', [$alice]);
        self::assertCount(2, $rows);
        $rows = DB::all('SELECT * FROM import_batches WHERE user_id = ?', [$bob]);
        self::assertCount(1, $rows);
    }

    public function testIndexFiltersByDateRange(): void
    {
        $uid = TestSupport::seedUser('alice');
        self::seedBatch('old.csv',   $uid, '2026-09-30 23:59:59');
        self::seedBatch('new.csv',   $uid, '2026-10-14 00:00:00');
        self::seedBatch('newer.csv', $uid, '2026-10-15 12:00:00');

        $rows = DB::all(
            'SELECT * FROM import_batches WHERE created_at >= ? AND created_at <= ?',
            ['2026-10-14 00:00:00', '2026-10-14 23:59:59']
        );
        self::assertCount(1, $rows);
        self::assertSame('new.csv', $rows[0]['label']);
    }

    public function testBatchDeleteCascadesTimeEntries(): void
    {
        $uid = TestSupport::seedUser('alice');
        $batchId = self::seedBatch('with-entries.csv', $uid, '2026-10-14 10:00:00');

        // Seed structure for the FKs
        [$cid, $pid, $tid] = self::seedStructure();
        for ($i = 0; $i < 3; $i++) {
            DB::run(
                'INSERT INTO time_entries
                    (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min,
                     note, source, batch_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$uid, $tid, $pid, $cid, '2026-10-14 09:00:00', '2026-10-14 10:00:00', 60,
                 '', 'import', $batchId, '2026-10-14 10:00:00', '2026-10-14 10:00:00']
            );
        }
        self::assertSame(3, (int) DB::scalar('SELECT COUNT(*) FROM time_entries WHERE batch_id = ?', [$batchId]));

        DB::run('DELETE FROM time_entries WHERE batch_id = ?', [$batchId]);
        DB::run('DELETE FROM import_batches WHERE id = ?', [$batchId]);

        self::assertSame(0, (int) DB::scalar('SELECT COUNT(*) FROM import_batches WHERE id = ?', [$batchId]));
        self::assertSame(0, (int) DB::scalar('SELECT COUNT(*) FROM time_entries WHERE batch_id = ?', [$batchId]));
    }

    private static function seedBatch(string $label, int $uid, string $when): int
    {
        return DB::insert(
            'INSERT INTO import_batches (label, source, user_id, created_at) VALUES (?, ?, ?, ?)',
            [$label, 'csv', $uid, $when]
        );
    }

    /** @return array{0:int,1:int,2:int} client/project/task ids */
    private static function seedStructure(): array
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme', '2026-01-01 00:00:00']);
        $pid = DB::insert('INSERT INTO projects (client_id, name, code, created_at) VALUES (?, ?, ?, ?)',
            [$cid, 'Site', 'site', '2026-01-01 00:00:00']);
        $tid = DB::insert('INSERT INTO tasks (project_id, name, created_at) VALUES (?, ?, ?)',
            [$pid, 'Build', '2026-01-01 00:00:00']);
        return [$cid, $pid, $tid];
    }
}
