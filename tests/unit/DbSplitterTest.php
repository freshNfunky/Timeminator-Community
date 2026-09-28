<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DB::class)]
final class DbSplitterTest extends TestCase
{
    /** @return string[] */
    private function split(string $sql): array
    {
        $r = new ReflectionClass(DB::class);
        $m = $r->getMethod('splitSqlStatements');
        return $m->invoke(null, $sql);
    }

    public function testIgnoresSemicolonsInsideStringLiterals(): void
    {
        $sql = "INSERT INTO t (note) VALUES ('a; b; c'); INSERT INTO t (note) VALUES ('two');";
        $stmts = $this->split($sql);
        self::assertCount(2, $stmts);
        self::assertStringContainsString("'a; b; c'", $stmts[0]);
    }

    public function testStripsLineComments(): void
    {
        $sql = <<<'SQL'
-- comment; with a semicolon that must not split
CREATE TABLE a (id INT);
# mysql-style comment; also here
CREATE TABLE b (id INT);
SQL;
        $stmts = $this->split($sql);
        self::assertCount(2, $stmts);
        self::assertStringStartsWith('CREATE TABLE a', $stmts[0]);
    }

    public function testStripsBlockComments(): void
    {
        $sql = "/* multi ; line\n comment */ CREATE TABLE a (id INT); CREATE TABLE b (id INT);";
        $stmts = $this->split($sql);
        self::assertCount(2, $stmts);
    }

    public function testHandlesEscapedQuotes(): void
    {
        $sql = "INSERT INTO t VALUES ('it\\'s'); INSERT INTO t VALUES ('next');";
        $stmts = $this->split($sql);
        self::assertCount(2, $stmts);
    }

    public function testShippedSchemaApplies(): void
    {
        tm_boot_sqlite();
        $tables = DB::all("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $names = array_column($tables, 'name');
        foreach (['users', 'clients', 'projects', 'tasks', 'time_entries', 'login_attempts'] as $required) {
            self::assertContains($required, $names, "expected table $required after applying schema");
        }
    }
}
