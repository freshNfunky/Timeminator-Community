<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Scheduler (Pro-Sync A.3, #109).
 *
 * Covers registration, due-detection vs interval, status persistence,
 * built-in prune-rollback snapshot sweep, and the web-cron token
 * verifier. The admin controllers are thin shells over this class, so
 * they aren't re-tested here — the HTTP surface is exercised manually.
 */
final class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        Scheduler::resetForTests();
    }

    protected function tearDown(): void
    {
        TestSupport::resetSettings();
        Scheduler::resetForTests();
    }

    public function testRegisterAndStatusForFreshJob(): void
    {
        Scheduler::register('noop', 15, static fn() => 'did nothing', 'does nothing');
        $status = Scheduler::status();
        self::assertCount(1, $status);
        self::assertSame('noop', $status[0]['name']);
        self::assertSame(15, $status[0]['interval_min']);
        self::assertNull($status[0]['last_run_at']);
        self::assertNull($status[0]['last_result']);
    }

    public function testRunOneCapturesResultAndTiming(): void
    {
        Scheduler::register('say_hi', 60, static fn() => 'hi');
        $r = Scheduler::runOne('say_hi');
        self::assertSame('hi', $r['result']);
        self::assertNull($r['error']);
        self::assertGreaterThanOrEqual(0, $r['duration_ms']);

        $status = Scheduler::status();
        self::assertSame('hi', $status[0]['last_result']);
        self::assertNotNull($status[0]['last_run_at']);
    }

    public function testRunOneCapturesExceptionAsErrorState(): void
    {
        Scheduler::register('kaboom', 60, static function (): string {
            throw new RuntimeException('oops');
        });
        $r = Scheduler::runOne('kaboom');
        self::assertNull($r['result']);
        self::assertSame('oops', $r['error']);

        $status = Scheduler::status();
        self::assertSame('oops', $status[0]['last_error']);
    }

    public function testUnknownJobReturnsErrorWithoutThrowing(): void
    {
        $r = Scheduler::runOne('does_not_exist');
        self::assertSame('unknown_job', $r['error']);
    }

    public function testRunDueOnlyFiresJobsPastTheirInterval(): void
    {
        $calls = 0;
        Scheduler::register('every_30', 30, static function () use (&$calls): string {
            $calls++;
            return 'tick';
        });

        // First run: due (never ran).
        Scheduler::runDue();
        self::assertSame(1, $calls);

        // Second run with "now" only 1 minute later: NOT due.
        $soon = new DateTimeImmutable('+1 minute');
        Scheduler::runDue($soon);
        self::assertSame(1, $calls);

        // Third run 31 minutes later: due again.
        $later = new DateTimeImmutable('+31 minutes');
        Scheduler::runDue($later);
        self::assertSame(2, $calls);
    }

    public function testTokenPersistsAndVerifies(): void
    {
        $t1 = Scheduler::token();
        self::assertNotSame('', $t1);
        self::assertSame($t1, Scheduler::token(), 'cached, idempotent');

        self::assertTrue(Scheduler::verifyToken($t1));
        self::assertFalse(Scheduler::verifyToken(''));
        self::assertFalse(Scheduler::verifyToken('nope'));

        $t2 = Scheduler::regenerateToken();
        self::assertNotSame($t1, $t2);
        self::assertFalse(Scheduler::verifyToken($t1), 'old token rejected after regen');
        self::assertTrue(Scheduler::verifyToken($t2));
    }

    public function testPruneRollbackSnapshotsSweepsOnlyOldDirs(): void
    {
        $data = APP_ROOT . '/data';
        if (!is_dir($data)) { mkdir($data, 0775, true); }
        $old = $data . '/backup_19991231_235959';
        $new = $data . '/backup_' . date('Ymd_His');
        try {
            mkdir($old, 0775, true);
            file_put_contents($old . '/x.txt', '1');
            touch($old, time() - 30 * 86400); // 30 days old
            touch($old . '/x.txt', time() - 30 * 86400);
            mkdir($new, 0775, true);
            file_put_contents($new . '/x.txt', '1');

            $msg = Scheduler::pruneRollbackSnapshots(7);
            self::assertStringContainsString('removed 1', $msg);
            self::assertFalse(is_dir($old), 'old snapshot removed');
            self::assertTrue(is_dir($new),  'fresh snapshot kept');
        } finally {
            // Clean up fresh dir so we don't leak into other tests.
            if (is_dir($new)) {
                @unlink($new . '/x.txt');
                @rmdir($new);
            }
            if (is_dir($old)) {
                @unlink($old . '/x.txt');
                @rmdir($old);
            }
        }
    }

    public function testBootJobsRegistersExpectedCommunityJobs(): void
    {
        Scheduler::bootJobs();
        $names = array_keys(Scheduler::jobs());
        self::assertContains('prune_expired_reset_tokens', $names);
        self::assertContains('prune_rollback_snapshots', $names);
    }
}
