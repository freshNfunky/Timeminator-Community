<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Password-Reset-Flow (#107).
 *
 * Verifies the token lifecycle: created → found → consumed → invalid, plus
 * expiry, hash-not-leak, rate-limiting, prune, and sibling-invalidation
 * (one successful reset locks every other outstanding token for the same
 * user, so a race between two sent mails can only redeem once).
 */
final class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testCreateReturnsPlainTokenAndStoresOnlyHash(): void
    {
        $uid = TestSupport::seedUser('alice');
        $plain = PasswordReset::create($uid, '192.0.2.1');

        self::assertSame(64, strlen($plain), 'token is 32 bytes hex');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $plain);

        $row = DB::one('SELECT * FROM password_reset_tokens WHERE user_id = ?', [$uid]);
        self::assertNotEmpty($row);
        self::assertNotSame($plain, $row['token_hash'], 'plain token must not be stored');
        self::assertSame(hash('sha256', $plain), $row['token_hash']);
        self::assertSame('192.0.2.1', $row['requester_ip']);
        self::assertNull($row['used_at']);
    }

    public function testFindReturnsRowForValidTokenAndNullForUnknown(): void
    {
        $uid = TestSupport::seedUser('alice');
        $plain = PasswordReset::create($uid, '');

        $found = PasswordReset::find($plain);
        self::assertNotNull($found);
        self::assertSame($uid, $found['user_id']);

        self::assertNull(PasswordReset::find(str_repeat('0', 64)), 'unknown digest -> null');
        self::assertNull(PasswordReset::find('short'),            'malformed -> null');
        self::assertNull(PasswordReset::find(''),                 'empty -> null');
        self::assertNull(PasswordReset::find('ZZZZ' . substr($plain, 4)), 'non-hex -> null');
    }

    public function testFindRejectsExpiredToken(): void
    {
        $uid = TestSupport::seedUser('alice');
        $plain = PasswordReset::create($uid, '');
        // Force the row into the past.
        DB::run('UPDATE password_reset_tokens SET expires_at = ? WHERE user_id = ?',
            [(new DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'), $uid]);

        self::assertNull(PasswordReset::find($plain));
    }

    public function testConsumeChangesPasswordAndMarksUsedOnce(): void
    {
        $uid = TestSupport::seedUser('alice');
        $plain = PasswordReset::create($uid, '');

        $oldHash = DB::one('SELECT password_hash FROM users WHERE id = ?', [$uid])['password_hash'];

        self::assertTrue(PasswordReset::consume($plain, 'brand-new-pw'));
        $newHash = DB::one('SELECT password_hash FROM users WHERE id = ?', [$uid])['password_hash'];
        self::assertNotSame($oldHash, $newHash);
        self::assertTrue(password_verify('brand-new-pw', (string) $newHash));

        // Second consume with the same token must fail.
        self::assertFalse(PasswordReset::consume($plain, 'another'));
        $row = DB::one('SELECT * FROM password_reset_tokens WHERE user_id = ?', [$uid]);
        self::assertNotNull($row['used_at']);
    }

    public function testConsumeInvalidatesSiblingTokensForSameUser(): void
    {
        $uid = TestSupport::seedUser('alice');
        $t1 = PasswordReset::create($uid, '');
        $t2 = PasswordReset::create($uid, '');

        self::assertTrue(PasswordReset::consume($t1, 'pw1'));
        // The second (unused) token must be rejected after the first consume,
        // since siblings get used_at set so they can never redeem later.
        self::assertNull(PasswordReset::find($t2), 'sibling must be invalidated');
        self::assertFalse(PasswordReset::consume($t2, 'pw2'));
    }

    public function testRateLimitKicksInAtTheConfiguredCount(): void
    {
        $uid = TestSupport::seedUser('alice');
        for ($i = 0; $i < PasswordReset::RATE_LIMIT_PER_HOUR; $i++) {
            PasswordReset::create($uid, '');
        }
        self::assertTrue(PasswordReset::isRateLimited($uid));

        $bob = TestSupport::seedUser('bob');
        self::assertFalse(PasswordReset::isRateLimited($bob), 'per-user, not global');
    }

    public function testPruneDeletesExpiredUnusedRowsOnly(): void
    {
        $uid = TestSupport::seedUser('alice');

        // Fresh row: stays.
        $alive = PasswordReset::create($uid, '');

        // Expired unused row: gets pruned.
        PasswordReset::create($uid, '');
        DB::run('UPDATE password_reset_tokens SET expires_at = ? WHERE token_hash <> ?',
            [(new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'), hash('sha256', $alive)]);

        self::assertSame(2, (int) DB::scalar('SELECT COUNT(*) FROM password_reset_tokens'));
        $pruned = PasswordReset::prune();
        self::assertSame(1, $pruned);
        self::assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM password_reset_tokens'));
        self::assertNotNull(PasswordReset::find($alive));
    }
}
