<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginThrottle::class)]
final class LoginThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        tm_boot_sqlite();
    }

    public function testFreshStateHasNoLockout(): void
    {
        self::assertNull(LoginThrottle::lockoutReason('alice', '203.0.113.5'));
    }

    public function testUserLockoutAfterFiveFails(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('alice', null, '203.0.113.5', false);
        }
        $reason = LoginThrottle::lockoutReason('alice', '203.0.113.5');
        self::assertNotNull($reason);
        self::assertStringContainsString('Benutzer', $reason);
    }

    public function testUserLockoutDoesNotAffectDifferentUsername(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('alice', null, '203.0.113.5', false);
        }
        // Bob from a different IP: must be free.
        self::assertNull(LoginThrottle::lockoutReason('bob', '198.51.100.10'));
    }

    public function testIpLockoutTripsAtTwenty(): void
    {
        for ($i = 0; $i < 20; $i++) {
            // Different usernames so user-lockout doesn't trip first.
            LoginThrottle::record('u' . $i, null, '203.0.113.9', false);
        }
        $reason = LoginThrottle::lockoutReason('somebody-else', '203.0.113.9');
        self::assertNotNull($reason);
        self::assertStringContainsString('IP', $reason);
    }

    public function testClearForUserResetsLockout(): void
    {
        DB::run('INSERT INTO users (id, username, password_hash, display_name, role_id, active, created_at) VALUES (?,?,?,?,?,?,?)',
            [42, 'carol', 'x', 'Carol', null, 1, now()]);
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('carol', 42, '10.0.0.1', false);
        }
        self::assertNotNull(LoginThrottle::lockoutReason('carol', '10.0.0.1'));

        LoginThrottle::clearForUser(42);
        self::assertNull(LoginThrottle::lockoutReason('carol', '10.0.0.1'));
    }

    public function testBackoffGrowsWithFails(): void
    {
        $base = LoginThrottle::backoffMicroseconds('nobody');
        self::assertGreaterThan(0, $base);

        LoginThrottle::record('growing', null, '10.0.0.2', false);
        LoginThrottle::record('growing', null, '10.0.0.2', false);
        $two = LoginThrottle::backoffMicroseconds('growing');
        self::assertGreaterThan($base, $two);

        LoginThrottle::record('growing', null, '10.0.0.2', false);
        LoginThrottle::record('growing', null, '10.0.0.2', false);
        $four = LoginThrottle::backoffMicroseconds('growing');
        self::assertGreaterThan($two, $four);
    }

    public function testPruneDropsOldRows(): void
    {
        DB::run(
            'INSERT INTO login_attempts (username, user_id, ip_address, success, attempted_at) VALUES (?,?,?,?,?)',
            ['ancient', null, '10.0.0.3', 0, date('Y-m-d H:i:s', time() - 3 * 86400)]
        );
        LoginThrottle::record('recent', null, '10.0.0.3', false);
        self::assertSame(2, (int) DB::scalar('SELECT COUNT(*) FROM login_attempts'));

        LoginThrottle::prune();
        self::assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM login_attempts'));
    }
}
