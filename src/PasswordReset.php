<?php
declare(strict_types=1);

/**
 * Password-Reset-Flow (Pro-Sync A.1, #107).
 *
 * The server only ever stores a SHA-256 hex digest of the token. The plain
 * token is returned by `create()` once (so the controller can mail it) and
 * is never persisted. Tokens are single-use (marked `used_at`) and expire
 * after one hour. The Scheduler (A.3) prunes expired rows; until then they
 * sit idle and refuse to redeem.
 */
final class PasswordReset
{
    public const TTL_SECONDS = 3600;        // one hour
    public const RATE_LIMIT_PER_HOUR = 3;   // per user; silent cap

    /** Issue a fresh token for a user. Returns the plain token (shown only here). */
    public static function create(int $userId, string $requesterIp): string
    {
        $plain = bin2hex(random_bytes(32)); // 64 hex chars
        $hash  = hash('sha256', $plain);
        $expires = (new DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds'))
            ->format('Y-m-d H:i:s');
        DB::run(
            'INSERT INTO password_reset_tokens
                (user_id, token_hash, expires_at, created_at, requester_ip)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $hash, $expires, now(), $requesterIp !== '' ? $requesterIp : null]
        );
        return $plain;
    }

    /** True when the user has already received at least RATE_LIMIT_PER_HOUR tokens in the last hour. */
    public static function isRateLimited(int $userId): bool
    {
        $cutoff = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
        $n = (int) DB::scalar(
            'SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ? AND created_at >= ?',
            [$userId, $cutoff]
        );
        return $n >= self::RATE_LIMIT_PER_HOUR;
    }

    /**
     * Look up a token row by its plain-text form, returning null when it is
     * unknown, expired, or already used. Timing is intentionally constant on
     * the lookup — we hash first and then query by the digest, so a visitor
     * guessing tokens can't distinguish "no such row" from "row exists" via
     * query timing.
     *
     * @return ?array{id:int,user_id:int,expires_at:string,used_at:?string}
     */
    public static function find(string $plainToken): ?array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !ctype_xdigit($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }
        $hash = hash('sha256', $plainToken);
        $row = DB::one(
            'SELECT id, user_id, expires_at, used_at FROM password_reset_tokens WHERE token_hash = ?',
            [$hash]
        );
        if (!$row) { return null; }
        if (!empty($row['used_at'])) { return null; }
        if (strtotime((string) $row['expires_at']) < time()) { return null; }
        return [
            'id'         => (int) $row['id'],
            'user_id'    => (int) $row['user_id'],
            'expires_at' => (string) $row['expires_at'],
            'used_at'    => $row['used_at'] !== null ? (string) $row['used_at'] : null,
        ];
    }

    /**
     * Redeem a token: validate, update the user's password_hash, mark the
     * token used, and invalidate every other outstanding token for the same
     * user. Returns true on success.
     */
    public static function consume(string $plainToken, string $newPassword): bool
    {
        $row = self::find($plainToken);
        if ($row === null) { return false; }
        $uid = $row['user_id'];
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $uid]);
        DB::run('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?', [now(), $row['id']]);
        // Invalidate siblings so a second mail (if the user clicked "forgot" twice)
        // still only lets one chain through, and after a successful reset no stale
        // token can hijack the account.
        DB::run(
            'UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL',
            [now(), $uid]
        );
        return true;
    }

    /** Delete expired rows. Called by the scheduler job in A.3; safe to run anytime. */
    public static function prune(): int
    {
        $cutoff = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
        // Keep used tokens around for a week as an audit tail, then drop.
        DB::run('DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL AND used_at < ?', [$cutoff]);
        $before = (int) DB::scalar('SELECT COUNT(*) FROM password_reset_tokens WHERE expires_at < ? AND used_at IS NULL', [now()]);
        DB::run('DELETE FROM password_reset_tokens WHERE expires_at < ? AND used_at IS NULL', [now()]);
        return $before;
    }
}
