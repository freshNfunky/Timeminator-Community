<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Repeat-offender escalation: 15 min → 1 h → 4 h → 24 h based on how many
 * distinct 15-minute windows already crossed the threshold in the past 24 h.
 * And the Retry-After hint that ctrl_login uses on the 429.
 */
final class LoginThrottleEscalationTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    /** Insert $n failures for $username / $ip at exactly $ts (as 'Y-m-d H:i:s'). */
    private function seedFailsAt(string $ts, string $username, string $ip, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::run(
                'INSERT INTO login_attempts (username, user_id, ip_address, success, attempted_at)
                 VALUES (?, NULL, ?, 0, ?)',
                [$username, $ip, $ts]
            );
        }
    }

    public function testRetryAfterReturnsPositiveWhenLocked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LoginThrottle::record('carol', null, '203.0.113.20', false);
        }
        self::assertNotNull(LoginThrottle::lockoutReason('carol', '203.0.113.20'));
        $ra = LoginThrottle::retryAfterSeconds('carol', '203.0.113.20');
        self::assertGreaterThan(0, $ra);
        // Baseline window is 15 min; the header must be within (0, 15*60].
        self::assertLessThanOrEqual(15 * 60, $ra);
    }

    public function testRetryAfterIsAtLeastOne(): void
    {
        // No failures at all: retryAfter must still be at least 1 so a
        // client that respects the header actually backs off if we ever
        // send it.
        self::assertGreaterThanOrEqual(1, LoginThrottle::retryAfterSeconds('nobody', '198.51.100.30'));
    }

    public function testFirstLockoutKeepsBaselineWindow(): void
    {
        // 5 failures 20 minutes ago — only one prior locked bucket, baseline
        // window is 15 min, so the failures have already rolled off. Not a
        // repeat offender yet, so this is the intended behaviour: unlock.
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 20 * 60), 'dave', '203.0.113.21', 5);
        self::assertNull(LoginThrottle::lockoutReason('dave', '203.0.113.21'));
    }

    public function testSecondLockoutEscalatesTo60Minutes(): void
    {
        // Bucket A at t = -30 min (already aged past 15 min baseline).
        // Bucket B at t = -10 min (fresh). Two prior locked buckets means
        // the second lockout event has happened — escalate to 60 min, so
        // the bucket B failures still count and the user stays locked.
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 30 * 60), 'dave', '203.0.113.21', 5);
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 10 * 60), 'dave', '203.0.113.21', 5);
        $reason = LoginThrottle::lockoutReason('dave', '203.0.113.21');
        self::assertIsString($reason, 'second-time offender should be locked under the escalated 60 min window');
    }

    public function testEscalationCleansAfterHorizon(): void
    {
        // 30 hours ago — outside the 24 h escalation horizon and outside
        // the max ladder step (24 h). Must not lock.
        $thirtyHoursAgo = date('Y-m-d H:i:s', time() - 30 * 3600);
        $this->seedFailsAt($thirtyHoursAgo, 'eve', '203.0.113.22', 5);
        self::assertNull(LoginThrottle::lockoutReason('eve', '203.0.113.22'));
    }

    public function testThirdLockoutEscalatesTo4Hours(): void
    {
        // Three prior locked buckets — 5 fails each at t = -3h30, -2h,
        // and -10 min. Ladder index 2 → 4 h window, so retryAfter must
        // be past the 60 min rung but no more than the 4 h step.
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 3 * 3600 - 30 * 60), 'frank', '203.0.113.23', 5);
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 2 * 3600), 'frank', '203.0.113.23', 5);
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 10 * 60), 'frank', '203.0.113.23', 5);

        $reason = LoginThrottle::lockoutReason('frank', '203.0.113.23');
        self::assertIsString($reason);

        $ra = LoginThrottle::retryAfterSeconds('frank', '203.0.113.23');
        self::assertGreaterThan(60 * 60, $ra, 'escalated window should push retry-after past 60 min');
        self::assertLessThanOrEqual(4 * 3600, $ra, 'and stay inside the 4 h ladder step');
    }

    public function testLockoutMessageMentionsEscalatedWindow(): void
    {
        // Two locked buckets → 60 min rung. Message unit should be hours.
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 30 * 60), 'gina', '203.0.113.24', 5);
        $this->seedFailsAt(date('Y-m-d H:i:s', time() - 5 * 60), 'gina', '203.0.113.24', 5);

        $reason = LoginThrottle::lockoutReason('gina', '203.0.113.24');
        self::assertIsString($reason);
        self::assertMatchesRegularExpression('/\bStunde|Stunden\b/', $reason);
    }
}
