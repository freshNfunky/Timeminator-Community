<?php
declare(strict_types=1);

/**
 * Team-Sentiment-Tracking (fixes #92, split into Mood + Flow by #122).
 *
 * The Community edition landing page promises "team sentiment tracking", so
 * this is table-stakes rather than optional. Each user records **two
 * independent 1..5 scores per day**:
 *   - `mood` — personal state (fröhlich / neutral / schlecht)
 *   - `flow` — how work went today (im Fluss / zäh / blockiert)
 *
 * The split lets a project manager spot the pattern that matters most:
 * someone who is personally fine but perpetually frustrated in the
 * project ("innerlich gekündigt"). One row per (user_id, as_of) via a
 * unique index; either column may be NULL so a user can log just one of
 * the two dimensions.
 *
 * Team aggregates are intentionally coarse: average + response count +
 * distinct contributors per dimension, grouped by day. No individual
 * score and no username ever leaves the aggregate.
 */
final class Sentiment
{
    public const MIN_SCORE = 1;
    public const MAX_SCORE = 5;
    public const DIMENSIONS = ['mood', 'flow'];

    /**
     * Calendar/dashboard display + input granularity (v1.0 F4, portiert
     * aus Pro #323/#331). Steuert, ob der Nutzer jeden Tag einzeln buchen
     * soll oder ein Wochen-Preset vorbelegt werden kann.
     */
    public const GRANULARITIES = ['day' => 'Taeglich', 'week' => 'Woechentlich'];

    public static function granularity(): string
    {
        $g = (string) Settings::get('sentiment_granularity', 'week');
        return array_key_exists($g, self::GRANULARITIES) ? $g : 'week';
    }

    /** Clamp a user-supplied value to the valid 1..5 range, or null if garbage. */
    public static function normalizeScore(mixed $v): ?int
    {
        if ($v === null || $v === '') { return null; }
        $i = (int) $v;
        if ($i < self::MIN_SCORE || $i > self::MAX_SCORE) { return null; }
        return $i;
    }

    /** Strict YYYY-MM-DD (a real calendar date) or null. */
    public static function parseDay(string $s): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', trim($s));
        return $dt && $dt->format('Y-m-d') === trim($s) ? trim($s) : null;
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('today'))->format('Y-m-d');
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
     * Insert or update today's (or $asOf's) sentiment for $userId. UPSERT
     * via (user_id, as_of) uniqueness, so a repeat save on the same day
     * replaces the earlier value. Both $mood and $flow are optional
     * (NULL = user did not set this dimension) — at least one must be
     * provided, or the call is a no-op that returns false.
     */
    public static function record(int $userId, string $asOf, ?int $mood, ?int $flow, ?string $note): bool
    {
        $mood = $mood === null ? null : max(self::MIN_SCORE, min(self::MAX_SCORE, $mood));
        $flow = $flow === null ? null : max(self::MIN_SCORE, min(self::MAX_SCORE, $flow));
        if ($mood === null && $flow === null) {
            return false;
        }
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
                'UPDATE sentiment_entries SET mood = ?, flow = ?, note = ?, updated_at = ? WHERE id = ?',
                [$mood, $flow, $note, $now, (int) $existing['id']]
            );
        } else {
            DB::run(
                'INSERT INTO sentiment_entries (user_id, as_of, mood, flow, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $asOf, $mood, $flow, $note, $now]
            );
        }
        return true;
    }

    /** Latest entry for a user, or null. */
    public static function latestForUser(int $userId): ?array
    {
        return DB::one(
            'SELECT * FROM sentiment_entries WHERE user_id = ? ORDER BY as_of DESC LIMIT 1',
            [$userId]
        );
    }

    /** Today's entry for a user, or null. Lets the UI pre-fill the smiley pickers. */
    public static function todayForUser(int $userId): ?array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        return DB::one(
            'SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?',
            [$userId, $today]
        );
    }

    /** @return array<int,array{as_of:string,mood:?int,flow:?int,note:?string}> */
    public static function historyForUser(int $userId, int $days = 60): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        $rows = DB::all(
            'SELECT as_of, mood, flow, note FROM sentiment_entries
              WHERE user_id = ? AND as_of >= ?
              ORDER BY as_of ASC',
            [$userId, $cutoff]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'as_of' => (string) $r['as_of'],
                'mood'  => $r['mood'] !== null ? (int) $r['mood'] : null,
                'flow'  => $r['flow'] !== null ? (int) $r['flow'] : null,
                'note'  => $r['note'] !== null ? (string) $r['note'] : null,
            ];
        }
        return $out;
    }

    /**
     * Team-wide daily aggregate for the last $days. One row per day that
     * has at least one Mood OR one Flow response. The two dimensions get
     * independent averages + counts so a day with 5 moods and 2 flows is
     * surfaced correctly.
     *
     * @return array<int,array{as_of:string,avg_mood:?float,n_mood:int,avg_flow:?float,n_flow:int}>
     */
    public static function teamDaily(int $days = 60): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        $rows = DB::all(
            'SELECT as_of,
                    AVG(mood) AS avg_mood, SUM(CASE WHEN mood IS NOT NULL THEN 1 ELSE 0 END) AS n_mood,
                    AVG(flow) AS avg_flow, SUM(CASE WHEN flow IS NOT NULL THEN 1 ELSE 0 END) AS n_flow
               FROM sentiment_entries
              WHERE as_of >= ?
           GROUP BY as_of
           ORDER BY as_of ASC',
            [$cutoff]
        );
        $out = [];
        foreach ($rows as $r) {
            $nMood = (int) $r['n_mood'];
            $nFlow = (int) $r['n_flow'];
            if ($nMood === 0 && $nFlow === 0) { continue; }
            $out[] = [
                'as_of'    => (string) $r['as_of'],
                'avg_mood' => $nMood > 0 ? (float) $r['avg_mood'] : null,
                'n_mood'   => $nMood,
                'avg_flow' => $nFlow > 0 ? (float) $r['avg_flow'] : null,
                'n_flow'   => $nFlow,
            ];
        }
        return $out;
    }

    /**
     * Team summary for a rolling window: per-dimension average + response
     * count, plus the total distinct contributors across both dimensions.
     * The "contributors" field is what justifies the "team" label — one
     * person logging alone is personal, not team.
     *
     * @return array{avg_mood:?float,n_mood:int,avg_flow:?float,n_flow:int,contributors:int,window_days:int}
     */
    public static function teamSummary(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $cutoff = (new DateTimeImmutable('today'))->modify('-' . $days . ' day')->format('Y-m-d');
        $row = DB::one(
            'SELECT AVG(mood) AS avg_mood, SUM(CASE WHEN mood IS NOT NULL THEN 1 ELSE 0 END) AS n_mood,
                    AVG(flow) AS avg_flow, SUM(CASE WHEN flow IS NOT NULL THEN 1 ELSE 0 END) AS n_flow,
                    COUNT(DISTINCT user_id) AS contributors
               FROM sentiment_entries
              WHERE as_of >= ?',
            [$cutoff]
        );
        $nMood = $row ? (int) $row['n_mood'] : 0;
        $nFlow = $row ? (int) $row['n_flow'] : 0;
        return [
            'avg_mood'     => $nMood > 0 ? (float) $row['avg_mood'] : null,
            'n_mood'       => $nMood,
            'avg_flow'     => $nFlow > 0 ? (float) $row['avg_flow'] : null,
            'n_flow'       => $nFlow,
            'contributors' => $row ? (int) $row['contributors'] : 0,
            'window_days'  => $days,
        ];
    }

    /** Mood emoji 1..5 — the classic smiley scale. */
    public static function moodEmoji(int $score): string
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

    /** Flow emoji 1..5 — a work-pace / blockage scale, visually distinct from Mood. */
    public static function flowEmoji(int $score): string
    {
        return match (max(1, min(5, $score))) {
            1 => '🛑',  // blockiert
            2 => '🐢',  // zäh
            3 => '😐',  // neutral
            4 => '🏃',  // flott
            5 => '🚀',  // im Flow
            default => '😐',
        };
    }

    /** Mood label resolved against the current locale (with German fallback). */
    public static function moodLabel(int $score): string
    {
        $key = 'sent.mood.score.' . max(1, min(5, $score));
        return function_exists('t')
            ? t($key, self::moodLabelFallback($score))
            : self::moodLabelFallback($score);
    }

    /** Flow label resolved against the current locale (with German fallback). */
    public static function flowLabel(int $score): string
    {
        $key = 'sent.flow.score.' . max(1, min(5, $score));
        return function_exists('t')
            ? t($key, self::flowLabelFallback($score))
            : self::flowLabelFallback($score);
    }

    private static function moodLabelFallback(int $score): string
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

    private static function flowLabelFallback(int $score): string
    {
        return match (max(1, min(5, $score))) {
            1 => 'blockiert',
            2 => 'zaeh',
            3 => 'neutral',
            4 => 'flott',
            5 => 'im Fluss',
            default => '',
        };
    }

    // ---------- v1.0 F4: Granularity-UX helpers (portiert aus Pro) --------

    /** The user's entry for one day, or null. */
    public static function forDay(int $userId, string $asOf): ?array
    {
        return DB::one('SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?', [$userId, $asOf]);
    }

    /** Set a single dimension of the day, leaving the other dimension and the note untouched. */
    public static function setDimension(int $userId, string $asOf, string $dim, int $score): void
    {
        $col = $dim === 'flow' ? 'flow' : 'mood';
        $now = now();
        $existing = DB::one('SELECT id FROM sentiment_entries WHERE user_id = ? AND as_of = ?', [$userId, $asOf]);
        if ($existing) {
            DB::run("UPDATE sentiment_entries SET $col = ?, updated_at = ? WHERE id = ?",
                [$score, $now, (int) $existing['id']]);
        } else {
            DB::run("INSERT INTO sentiment_entries (user_id, as_of, $col, created_at, updated_at) VALUES (?,?,?,?,?)",
                [$userId, $asOf, $score, $now, $now]);
        }
    }

    /**
     * Bulk-fill one dimension across [$from, $to] inclusive. Conservative:
     * a day that already carries this dimension is left alone — individual
     * entries always win over a later bulk fill.
     *
     * @return array<string,int> day => score for every day actually filled
     */
    public static function fillRange(int $userId, string $from, string $to, string $dim, int $score): array
    {
        $filled = [];
        $d = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        while ($d <= $end) {
            $day = $d->format('Y-m-d');
            $existing = self::forDay($userId, $day);
            $col = $dim === 'flow' ? 'flow' : 'mood';
            if ($existing === null || $existing[$col] === null) {
                self::setDimension($userId, $day, $dim, $score);
                $filled[$day] = $score;
            }
            $d = $d->modify('+1 day');
        }
        return $filled;
    }
}
