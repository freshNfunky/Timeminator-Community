<?php
declare(strict_types=1);

/**
 * Rate-limit login attempts per username and per IP.
 *
 * Failed attempts are recorded in login_attempts. Lockout kicks in when a
 * rolling window has too many failures; a small backoff delay grows with
 * consecutive failures for the same username. Rows older than a day are
 * pruned opportunistically.
 */
final class LoginThrottle
{
    private const WINDOW_MIN        = 15;    // rolling window in minutes
    private const USER_MAX_FAILS    = 5;     // fails per user before lockout
    private const IP_MAX_FAILS      = 20;    // fails per IP before lockout
    private const KEEP_HOURS        = 24;    // house-keeping horizon

    public static function record(?string $username, ?int $userId, string $ip, bool $success): void
    {
        DB::run(
            'INSERT INTO login_attempts (username, user_id, ip_address, success, attempted_at)
             VALUES (?, ?, ?, ?, ?)',
            [$username !== '' ? $username : null, $userId, $ip !== '' ? $ip : null, $success ? 1 : 0, now()]
        );
    }

    /**
     * Return a locked-out reason if the caller must be refused before any
     * password check, or null if they may proceed.
     */
    public static function lockoutReason(string $username, string $ip): ?string
    {
        $cutoff = self::cutoff();
        if ($username !== '' && self::countFails(['username' => $username], $cutoff) >= self::USER_MAX_FAILS) {
            return 'Zu viele Fehlversuche fuer diesen Benutzer. Bitte in 15 Minuten erneut versuchen.';
        }
        if ($ip !== '' && self::countFails(['ip_address' => $ip], $cutoff) >= self::IP_MAX_FAILS) {
            return 'Zu viele Fehlversuche von dieser IP-Adresse. Bitte spaeter erneut versuchen.';
        }
        return null;
    }

    /**
     * Microseconds to sleep after a failed attempt: increases with recent
     * failures for the same username, so a fast credential-stuffer feels the
     * cost even below the lockout threshold.
     */
    public static function backoffMicroseconds(string $username): int
    {
        if ($username === '') {
            return 300_000;
        }
        $fails = self::countFails(['username' => $username], self::cutoff());
        return match (true) {
            $fails >= 4 => 3_000_000,
            $fails >= 3 => 1_500_000,
            $fails >= 2 => 700_000,
            default     => 300_000,
        };
    }

    public static function clearForUser(int $userId): void
    {
        DB::run('DELETE FROM login_attempts WHERE user_id = ? AND success = 0', [$userId]);
    }

    public static function prune(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::KEEP_HOURS * 3600);
        DB::run('DELETE FROM login_attempts WHERE attempted_at < ?', [$cutoff]);
    }

    private static function cutoff(): string
    {
        return date('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
    }

    /** @param array<string,string> $where */
    private static function countFails(array $where, string $cutoff): int
    {
        $conds = ['success = 0', 'attempted_at > ?'];
        $params = [$cutoff];
        foreach ($where as $col => $val) {
            $conds[] = "$col = ?";
            $params[] = $val;
        }
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE ' . implode(' AND ', $conds),
            $params
        );
    }
}
