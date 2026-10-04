<?php
declare(strict_types=1);

/**
 * Team-Sentiment-Tracking (fixes #92).
 *
 * The Community edition landing page promises "team sentiment tracking", so
 * this is table-stakes rather than optional. Each user records a daily mood
 * score 1..5 with an optional short note. One row per (user_id, as_of) via
 * a unique index; a second write on the same day updates the first.
 *
 * The team summary groups by day and returns the arithmetic mean + a response
 * count — the number stays usable even when individual rows are sparse. No
 * user name leaks into the team view; the per-day aggregate is intentionally
 * coarse so a single response in a day stays reasonably anonymous.
 */
final class Sentiment
{
    public const MIN_SCORE = 1;
    public const MAX_SCORE = 5;

    /** Clamp a user-supplied value to the valid 1..5 range, or null if garbage. */
    public static function normalizeScore(mixed $v): ?int
    {
        if ($v === null || $v === '') { return null; }
        $i = (int) $v;
        if ($i < self::MIN_SCORE || $i > self::MAX_SCORE) { return null; }
        return $i;
    }

    /** ISO date string (YYYY-MM-DD) for a user-supplied date, or today. */
    public static function normalizeDate(?string $s): string
    {
        if ($s === null || $s === '') {
            return (new DateTimeImmutable('today'))->format('Y-m-d');
        }
        try {
            return (new DateTimeImmutable($s))->format('Y-m-d');
        } catch (Throwable) {
            return (new DateTimeImmutable('today'))->format('Y-m-d');
        }
    }

    /**
     * Insert or update today's (or $asOf's) sentiment for $userId. UPSERT:
     * the (user_id, as_of) uniqueness is enforced by the schema, so a repeat
     * save on the same day replaces the earlier value rather than erroring.
     */
    public static function record(int $userId, string $asOf, int $score, ?string $note): void
    {
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && $note === '') { $note = null; }
        if ($note !== null && mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $now = now();

        $existing = DB::one(
            'SELECT id FROM sentiment_entries WHERE user_id = ? AND as_of = ?',
            [$userId, $asOf]
        );
        if ($existing) {
            DB::run(
                'UPDATE sentiment_entries SET score = ?, note = ?, updated_at = ? WHERE id = ?',
                [$score, $note, $now, (int) $existing['id']]
            );
        } else {
            DB::run(
                'INSERT INTO sentiment_entries (user_id, as_of, score, note, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$userId, $asOf, $score, $note, $now]
            );
        }
    }

    /** Latest entry for a user, or null. */
    public static function latestForUser(int $userId): ?array
    {
        return DB::one(
            'SELECT * FROM sentiment_entries WHERE user_id = ? ORDER BY as_of DESC LIMIT 1',
            [$userId]
        );
    }

    /** Today's entry for a user, or null. Lets the UI pre-fill the smiley picker. */
    public static function todayForUser(int $userId): ?array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        return DB::one(
            'SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?',
            [$userId, $today]
        );
    }

    /** @return array<int,array{as_of:string,score:int,note:?string}> */
    public static function historyForUser(int $userId, int $days = 60): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        return DB::all(
            'SELECT as_of, score, note FROM sentiment_entries
              WHERE user_id = ? AND as_of >= ?
              ORDER BY as_of ASC',
            [$userId, $cutoff]
        );
    }

    /**
     * Team-wide daily aggregate for the last $days. Returns one row per day
     * that has at least one response; days without responses are omitted
     * (the view decides whether to fill gaps).
     *
     * @return array<int,array{as_of:string,avg_score:float,n:int}>
     */
    public static function teamDaily(int $days = 60): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        $rows = DB::all(
            'SELECT as_of, AVG(score) AS avg_score, COUNT(*) AS n
               FROM sentiment_entries
              WHERE as_of >= ?
           GROUP BY as_of
           ORDER BY as_of ASC',
            [$cutoff]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'as_of'     => (string) $r['as_of'],
                'avg_score' => (float) $r['avg_score'],
                'n'         => (int) $r['n'],
            ];
        }
        return $out;
    }

    /**
     * Team summary for a rolling window: average score, response count, and
     * the number of distinct contributors. The last field is what justifies
     * the "team" label — one person logging alone is personal, not team.
     *
     * @return array{avg:?float,responses:int,contributors:int,window_days:int}
     */
    public static function teamSummary(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        $row = DB::one(
            'SELECT AVG(score) AS avg_score, COUNT(*) AS n,
                    COUNT(DISTINCT user_id) AS contributors
               FROM sentiment_entries
              WHERE as_of >= ?',
            [$cutoff]
        );
        return [
            'avg'          => $row && $row['avg_score'] !== null ? (float) $row['avg_score'] : null,
            'responses'    => $row ? (int) $row['n'] : 0,
            'contributors' => $row ? (int) $row['contributors'] : 0,
            'window_days'  => $days,
        ];
    }

    /** Human-readable emoji for a given score; used by views and chart labels. */
    public static function emoji(int $score): string
    {
        return match (max(1, min(5, $score))) {
            1 => '😞',
            2 => '😕',
            3 => '😐',
            4 => '🙂',
            5 => '😄',
            default => '😐',
        };
    }

    /** Human-readable label (German) for a given score. */
    public static function label(int $score): string
    {
        return match (max(1, min(5, $score))) {
            1 => 'ganz schlecht',
            2 => 'nicht gut',
            3 => 'okay',
            4 => 'gut',
            5 => 'sehr gut',
            default => '',
        };
    }
}
