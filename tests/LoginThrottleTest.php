<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Drives LoginThrottle against a fresh in-memory SQLite database.
 */
final class LoginThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testLockoutReasonIsNullWithoutFailures(): void
    {
        self::assertNull(LoginThrottle::lockoutReason('alice', '203.0.113.10'));
    }

    public function testFiveUserFailuresLockThatUser(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('alice', null, '203.0.113.10', false);
        }
        $reason = LoginThrottle::lockoutReason('alice', '203.0.113.10');
        self::assertIsString($reason);
        self::assertStringContainsStringIgnoringCase('Fehlversuche', $reason);
    }

    public function testFourUserFailuresDoNotLock(): void
    {
        for ($i = 0; $i < 4; $i++) {
            LoginThrottle::record('alice', null, '203.0.113.10', false);
        }
        self::assertNull(LoginThrottle::lockoutReason('alice', '203.0.113.10'));
    }

    public function testSuccessfulAttemptDoesNotCountAsFailure(): void
    {
        // Add 4 failures and one success — still below the 5-fails threshold.
        for ($i = 0; $i < 4; $i++) {
            LoginThrottle::record('alice', null, '203.0.113.10', false);
        }
        LoginThrottle::record('alice', null, '203.0.113.10', true); // success
        self::assertNull(LoginThrottle::lockoutReason('alice', '203.0.113.10'));
    }

    public function testClearForUserResetsTheLockout(): void
    {
        $userId = TestSupport::seedUser('bob');
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('bob', $userId, '203.0.113.11', false);
        }
        self::assertNotNull(LoginThrottle::lockoutReason('bob', '203.0.113.11'));

        LoginThrottle::clearForUser($userId);
        self::assertNull(LoginThrottle::lockoutReason('bob', '203.0.113.11'));
    }

    public function testTwentyIpFailuresLockThatIp(): void
    {
        // Distinct usernames so the per-user counter never trips first.
        for ($i = 0; $i < 20; $i++) {
            LoginThrottle::record('user' . $i, null, '203.0.113.12', false);
        }
        $reason = LoginThrottle::lockoutReason('user_new', '203.0.113.12');
        self::assertIsString($reason);
        self::assertStringContainsStringIgnoringCase('IP-Adresse', $reason);
    }

    public function testPruneDropsRowsOlderThanHorizon(): void
    {
        // Insert a row 2 days ago and a fresh one; prune should keep only fresh.
        $twoDaysAgo = date('Y-m-d H:i:s', time() - 2 * 86400);
        DB::run(
            'INSERT INTO login_attempts (username, user_id, ip_address, success, attempted_at)
             VALUES (?, NULL, ?, 0, ?)',
            ['stale', '203.0.113.13', $twoDaysAgo]
        );
        LoginThrottle::record('fresh', null, '203.0.113.13', false);
        self::assertSame(2, (int) DB::scalar('SELECT COUNT(*) FROM login_attempts'));

        LoginThrottle::prune();
        self::assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM login_attempts'));
    }
}
