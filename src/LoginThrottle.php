<?php
declare(strict_types=1);

/**
 * Login throttling (issue #7).
 *
 * Two independent counters per attempt: one keyed by client IP, one by
 * lowercased username. Both must be under the threshold for a login to be
 * allowed. Once either bucket exceeds MAX_ATTEMPTS within WINDOW_SECONDS the
 * login is blocked for LOCKOUT_SECONDS, computed as an exponential backoff on
 * the number of failed attempts recorded for that bucket in the window.
 *
 * Successful logins clear the counters for the (ip, user) pair. A best-effort
 * prune removes old rows on write so the table stays bounded without a cron.
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS   = 5;      // failures within WINDOW_SECONDS before we lock
    public const WINDOW_SECONDS = 900;    // 15 minutes
    public const BASE_LOCKOUT   = 60;     // 1 minute at MAX_ATTEMPTS
    public const MAX_LOCKOUT    = 14400;  // clamp to 4 hours

    /**
     * Decide whether a login attempt from (ip, username) may proceed.
     *
     * @return array{allowed:bool, retry_after:int, reason:string}
     */
    public static function check(string $ip, string $username): array
    {
        $now = time();
        $ipBucket   = self::ipBucket($ip);
        $userBucket = self::userBucket($username);

        $ipCount   = self::countRecent($ipBucket, $now);
        $userCount = self::countRecent($userBucket, $now);
        $worstCount = max($ipCount, $userCount);

        if ($worstCount < self::MAX_ATTEMPTS) {
            return ['allowed' => true, 'retry_after' => 0, 'reason' => ''];
        }

        $lastAttempt = max(
            self::latestAt($ipBucket),
            self::latestAt($userBucket)
        );
        $unlockAt = $lastAttempt + self::lockoutSeconds($worstCount);
        if ($now >= $unlockAt) {
            return ['allowed' => true, 'retry_after' => 0, 'reason' => ''];
        }

        return [
            'allowed'     => false,
            'retry_after' => $unlockAt - $now,
            'reason'      => $ipCount >= self::MAX_ATTEMPTS ? 'ip' : 'user',
        ];
    }

    /** Record a failed attempt against both buckets and log it. */
    public static function recordFailure(string $ip, string $username): void
    {
        $ts = self::nowStr();
        DB::run(
            'INSERT INTO auth_login_attempts (bucket, attempted_at) VALUES (?, ?)',
            [self::ipBucket($ip), $ts]
        );
        DB::run(
            'INSERT INTO auth_login_attempts (bucket, attempted_at) VALUES (?, ?)',
            [self::userBucket($username), $ts]
        );
        error_log(sprintf(
            '[auth] failed login for user=%s ip=%s',
            $username !== '' ? $username : '(empty)',
            $ip !== '' ? $ip : '(unknown)'
        ));
        self::prune();
    }

    /** Clear counters for a successful login. */
    public static function recordSuccess(string $ip, string $username): void
    {
        DB::run(
            'DELETE FROM auth_login_attempts WHERE bucket IN (?, ?)',
            [self::ipBucket($ip), self::userBucket($username)]
        );
    }

    /** Best-effort GC of rows outside the current window. */
    public static function prune(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS * 4);
        DB::run('DELETE FROM auth_login_attempts WHERE attempted_at < ?', [$cutoff]);
    }

    public static function clientIp(): string
    {
        // Prefer proxy-forwarded, but only the leftmost entry. Operators
        // terminating TLS in front must configure the proxy to send this
        // header; do not trust a raw request without it.
        $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function ipBucket(string $ip): string
    {
        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
    }

    private static function userBucket(string $username): string
    {
        return 'user:' . strtolower(trim($username));
    }

    private static function countRecent(string $bucket, int $now): int
    {
        $cutoff = date('Y-m-d H:i:s', $now - self::WINDOW_SECONDS);
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM auth_login_attempts
               WHERE bucket = ? AND attempted_at >= ?',
            [$bucket, $cutoff]
        );
    }

    private static function latestAt(string $bucket): int
    {
        $row = DB::one(
            'SELECT attempted_at FROM auth_login_attempts
               WHERE bucket = ? ORDER BY attempted_at DESC LIMIT 1',
            [$bucket]
        );
        if (!$row) {
            return 0;
        }
        $ts = strtotime((string) $row['attempted_at']);
        return $ts !== false ? $ts : 0;
    }

    private static function lockoutSeconds(int $failures): int
    {
        // 5 fails -> BASE, then double each further fail. Clamped.
        $steps = max(0, $failures - self::MAX_ATTEMPTS);
        $seconds = self::BASE_LOCKOUT * (2 ** $steps);
        return (int) min($seconds, self::MAX_LOCKOUT);
    }

    private static function nowStr(): string
    {
        return date('Y-m-d H:i:s');
    }
}
