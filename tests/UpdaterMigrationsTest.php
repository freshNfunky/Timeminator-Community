<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Exercises the migration runner: it must pick up .sql AND .php files from
 * schema/migrations/<driver>/, apply each once, and skip anything already
 * recorded in Settings['applied_migrations'].
 */
final class UpdaterMigrationsTest extends TestCase
{
    private string $sandboxRoot;

    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        // Stand up a private "schema/migrations/sqlite" directory outside
        // the real repo layout so we do not accidentally re-apply the
        // shipped migrations.
        $this->sandboxRoot = sys_get_temp_dir() . '/tm-mig-' . uniqid();
        mkdir($this->sandboxRoot . '/schema/migrations/sqlite', 0775, true);
        // The runner reads APP_ROOT — swap it via a temp symlink is
        // fragile; instead we drop our test migrations into the real repo
        // layout under a "test-only" filename and clean them up in
        // tearDown. The runner's applied_migrations list is per-basename,
        // so files with a "test-" prefix cannot collide with production
        // migration names.
        rmdir($this->sandboxRoot . '/schema/migrations/sqlite');
        rmdir($this->sandboxRoot . '/schema/migrations');
        rmdir($this->sandboxRoot . '/schema');
        rmdir($this->sandboxRoot);
    }

    protected function tearDown(): void
    {
        foreach (glob(APP_ROOT . '/schema/migrations/sqlite/test-*.sql') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob(APP_ROOT . '/schema/migrations/sqlite/test-*.php') ?: [] as $f) {
            @unlink($f);
        }
        TestSupport::resetSettings();
    }

    public function testRunsSqlMigrationOnceAndRecordsIt(): void
    {
        $file = APP_ROOT . '/schema/migrations/sqlite/test-9999-01-add-note.sql';
        file_put_contents(
            $file,
            "CREATE TABLE IF NOT EXISTS test_notes (id INTEGER PRIMARY KEY, note TEXT);"
        );

        $ran = Updater::runMigrations();
        self::assertGreaterThanOrEqual(1, $ran);

        $tables = DB::all("SELECT name FROM sqlite_master WHERE type='table' AND name='test_notes'");
        self::assertCount(1, $tables);

        $applied = (array) Settings::get('applied_migrations', []);
        self::assertContains('test-9999-01-add-note.sql', $applied);

        // Second run: nothing new to apply.
        $ranAgain = Updater::runMigrations();
        self::assertSame(0, $ranAgain, 'a second run must be a no-op');
    }

    public function testRunsPhpDataMigrationInSortedOrder(): void
    {
        $sql = APP_ROOT . '/schema/migrations/sqlite/test-9999-02-seed.sql';
        $php = APP_ROOT . '/schema/migrations/sqlite/test-9999-03-touch.php';
        file_put_contents(
            $sql,
            "CREATE TABLE IF NOT EXISTS test_marker (id INTEGER PRIMARY KEY, tag TEXT);"
        );
        file_put_contents(
            $php,
            "<?php DB::run('INSERT INTO test_marker (tag) VALUES (?)', ['from-php']);"
        );

        $ran = Updater::runMigrations();
        self::assertGreaterThanOrEqual(2, $ran);

        $row = DB::one("SELECT tag FROM test_marker WHERE tag = 'from-php'");
        self::assertNotNull($row, '.php migration must have inserted the marker row');

        $applied = (array) Settings::get('applied_migrations', []);
        self::assertContains('test-9999-02-seed.sql', $applied);
        self::assertContains('test-9999-03-touch.php', $applied);
    }
}
