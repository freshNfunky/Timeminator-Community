<?php
declare(strict_types=1);

/**
 * Rate-limit login attempts per username and per IP.
 *
 * Failed attempts are recorded in `login_attempts`. Lockout kicks in when a
 * rolling window has too many failures. The window escalates for repeat
 * offenders in the past `ESCALATION_HORIZON_HOURS`: 15 min → 1 h → 4 h → 24 h.
 * A small back-off delay grows with recent failures for the same username
 * even before the lockout trips. Rows older than a day are pruned
 * opportunistically.
 */
final class LoginThrottle
{
    private const BASE_WINDOW_MIN          = 15;    // rolling window for the first lockout
    private const USER_MAX_FAILS           = 5;     // fails per user before lockout
    private const IP_MAX_FAILS             = 20;    // fails per IP before lockout
    private const KEEP_HOURS               = 24;    // house-keeping horizon
    private const ESCALATION_HORIZON_HOURS = 24;    // look-back for repeat-offender counting

    /**
     * Escalation ladder in minutes. Index (1-based) = number of distinct
     * base-window buckets that crossed the threshold in the horizon, so the
     * first lockout stays at 15 min and only the second and beyond escalate.
     * Anything past the last entry clamps to it.
     */
    private const ESCALATION_LADDER = [15, 60, 240, 1440];

    public static function record(?string $username, ?int $userId, string $ip, bool $success): void
    {
        DB::run(
            'INSERT INTO login_attempts (username, user_id, ip_address, success, attempted_at)
             VALUES (?, ?, ?, ?, ?)',
            [$username !== '' ? $username : null, $userId, $ip !== '' ? $ip : null, $success ? 1 : 0, now()]
        );
    }

    /**
     * Return a locked-out reason string, or null if the caller may proceed.
     * The window used for the check escalates per bucket (see ladder above).
     */
    public static function lockoutReason(string $username, string $ip): ?string
    {
        if ($username !== '') {
            $win = self::escalatedWindowMin(['username' => $username], self::USER_MAX_FAILS);
            if (self::countFailsIn(['username' => $username], $win) >= self::USER_MAX_FAILS) {
                return 'Zu viele Fehlversuche fuer diesen Benutzer. Bitte in '
                    . self::formatWait($win) . ' erneut versuchen.';
            }
        }
        if ($ip !== '') {
            $win = self::escalatedWindowMin(['ip_address' => $ip], self::IP_MAX_FAILS);
            if (self::countFailsIn(['ip_address' => $ip], $win) >= self::IP_MAX_FAILS) {
                return 'Zu viele Fehlversuche von dieser IP-Adresse. Bitte in '
                    . self::formatWait($win) . ' erneut versuchen.';
            }
        }
        return null;
    }

    /**
     * Seconds until whichever locked bucket clears first. Used to fill the
     * `Retry-After` response header on the 429. Always at least 1 so a
     * client that respects the header actually backs off.
     */
    public static function retryAfterSeconds(string $username, string $ip): int
    {
        $out = 0;
        foreach ([
            ['username',   $username, self::USER_MAX_FAILS],
            ['ip_address', $ip,       self::IP_MAX_FAILS],
        ] as [$col, $val, $threshold]) {
            if ($val === '') {
                continue;
            }
            $winMin = self::escalatedWindowMin([$col => $val], $threshold);
            $cutoff = date('Y-m-d H:i:s', time() - $winMin * 60);
            $row = DB::one(
                'SELECT attempted_at FROM login_attempts
                  WHERE success = 0 AND ' . $col . ' = ? AND attempted_at > ?
                  ORDER BY attempted_at DESC LIMIT 1 OFFSET ' . ($threshold - 1),
                [$val, $cutoff]
            );
            if (!$row) {
                continue;
            }
            $rollsOff = strtotime((string) $row['attempted_at']) + $winMin * 60;
            $secs = max(0, $rollsOff - time());
            if ($secs > $out) {
                $out = $secs;
            }
        }
        return max(1, $out);
    }

    /**
     * Micro-seconds to sleep after a failed attempt: grows with recent
     * failures for the same username, so a fast credential-stuffer feels
     * the cost even below the lockout threshold.
     */
    public static function backoffMicroseconds(string $username): int
    {
        if ($username === '') {
            return 300_000;
        }
        $fails = self::countFailsIn(['username' => $username], self::BASE_WINDOW_MIN);
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

    // ---------- Internals -------------------------------------------------

    /**
     * How many base-window buckets have crossed $threshold within the past
     * $horizonHours, for the given (single-column) selector. This is the
     * "how many prior lockouts" count that drives the escalation ladder.
     *
     * @param array<string,string> $where
     */
    private static function distinctLockedBuckets(array $where, int $threshold, int $horizonHours): int
    {
        $conds = ['success = 0', 'attempted_at > ?'];
        $params = [date('Y-m-d H:i:s', time() - $horizonHours * 3600)];
        foreach ($where as $col => $val) {
            $conds[] = "$col = ?";
            $params[] = $val;
        }
        $rows = DB::all(
            'SELECT attempted_at FROM login_attempts WHERE ' . implode(' AND ', $conds),
            $params
        );
        $buckets = [];
        $bucketSeconds = self::BASE_WINDOW_MIN * 60;
        foreach ($rows as $r) {
            $ts = strtotime((string) $r['attempted_at']);
            if ($ts === false) {
                continue;
            }
            $b = intdiv($ts, $bucketSeconds);
            $buckets[$b] = ($buckets[$b] ?? 0) + 1;
        }
        return count(array_filter($buckets, static fn(int $c) => $c >= $threshold));
    }

    /**
     * @param array<string,string> $where
     */
    private static function escalatedWindowMin(array $where, int $threshold): int
    {
        $locked = self::distinctLockedBuckets($where, $threshold, self::ESCALATION_HORIZON_HOURS);
        // Ladder is 1-based: the first lockout keeps the baseline window,
        // the second bumps to the next rung, and so on.
        $idx = max(0, min($locked - 1, count(self::ESCALATION_LADDER) - 1));
        return self::ESCALATION_LADDER[$idx];
    }

    /**
     * @param array<string,string> $where
     */
    private static function countFailsIn(array $where, int $windowMinutes): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowMinutes * 60);
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

    private static function formatWait(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' Minuten';
        }
        if ($minutes < 1440) {
            $h = intdiv($minutes, 60);
            return $h . ($h === 1 ? ' Stunde' : ' Stunden');
        }
        $d = intdiv($minutes, 1440);
        return $d . ($d === 1 ? ' Tag' : ' Tagen');
    }
}
