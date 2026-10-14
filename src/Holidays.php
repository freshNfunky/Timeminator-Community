<?php
declare(strict_types=1);

/**
 * Static holiday overlay (Pro-Sync B.1, #110).
 *
 * Community intentionally does **not** support CalDAV / iCal subscriptions
 * — that stays Pro. Instead the week calendar highlights public holidays
 * from a built-in list, picked by the active UI locale:
 *
 *   `de` → DACH set (DE + AT + CH union; each entry carries a scope
 *          string so the hover-title can say where the day is a holiday)
 *   `en` → US federal holidays
 *
 * Entries are either fixed-date (`m-d`), Easter-relative (offset in days
 * from Easter Sunday computed with PHP's `easter_date`), or an nth-weekday
 * rule (e.g. "4th Thursday in November" for Thanksgiving).
 *
 * The scope string ("DE · AT · CH") is deliberately short — the view
 * surfaces it on hover; the catalog label stays locale-friendly.
 */
final class Holidays
{
    /**
     * @return array<int,array{date:string,key:string,scope:string}>
     *   One entry per holiday that falls in `$year`, sorted ascending.
     */
    public static function forYear(int $year, string $locale): array
    {
        $locale = strtolower($locale);
        $defs = $locale === 'en' ? self::US_DEFS : self::DACH_DEFS;

        $easter = self::easterSundayTimestamp($year);
        $out = [];
        foreach ($defs as $def) {
            $date = self::resolveDate($def, $year, $easter);
            if ($date === null) { continue; }
            $out[] = [
                'date'  => $date,
                'key'   => $def['key'],
                'scope' => $def['scope'],
            ];
        }
        usort($out, static fn(array $a, array $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /** Convenience: just the entries that fall within a given date range. */
    public static function between(string $fromYmd, string $toYmd, string $locale): array
    {
        $years = [];
        $from = (int) substr($fromYmd, 0, 4);
        $to   = (int) substr($toYmd,   0, 4);
        for ($y = $from; $y <= $to; $y++) { $years[] = $y; }
        $out = [];
        foreach ($years as $y) {
            foreach (self::forYear($y, $locale) as $h) {
                if (strcmp($h['date'], $fromYmd) >= 0 && strcmp($h['date'], $toYmd) <= 0) {
                    $out[] = $h;
                }
            }
        }
        return $out;
    }

    /** @return array<string, array{date:string,key:string,scope:string}> keyed by ISO date */
    public static function byDate(string $fromYmd, string $toYmd, string $locale): array
    {
        $out = [];
        foreach (self::between($fromYmd, $toYmd, $locale) as $h) {
            $out[$h['date']] = $h;
        }
        return $out;
    }

    private static function resolveDate(array $def, int $year, int $easter): ?string
    {
        if (isset($def['md'])) {
            [$m, $d] = $def['md'];
            try {
                return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $m, $d)))->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        if (isset($def['easter_offset'])) {
            $ts = $easter + ((int) $def['easter_offset']) * 86400;
            return date('Y-m-d', $ts);
        }
        if (isset($def['nth_weekday'])) {
            [$n, $dow, $month] = $def['nth_weekday'];
            return self::nthWeekdayOfMonth($year, $month, $dow, $n);
        }
        if (isset($def['last_weekday'])) {
            [$dow, $month] = $def['last_weekday'];
            return self::lastWeekdayOfMonth($year, $month, $dow);
        }
        return null;
    }

    /** PHP's easter_date() needs ext/calendar; fall back to a deterministic Meeus-based computation. */
    private static function easterSundayTimestamp(int $year): int
    {
        if (function_exists('easter_date')) {
            return easter_date($year);
        }
        // Anonymous Gregorian algorithm.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $ts = mktime(0, 0, 0, $month, $day, $year);
        return $ts === false ? 0 : $ts;
    }

    /** 1 ≤ $n ≤ 5. Returns null if the Nth weekday doesn't exist in that month. */
    private static function nthWeekdayOfMonth(int $year, int $month, int $dow, int $n): ?string
    {
        $first = mktime(0, 0, 0, $month, 1, $year);
        if ($first === false) { return null; }
        $firstDow = (int) date('w', $first); // 0 = Sunday
        $offset = ($dow - $firstDow + 7) % 7;
        $day = 1 + $offset + ($n - 1) * 7;
        if ($day > (int) date('t', $first)) { return null; }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function lastWeekdayOfMonth(int $year, int $month, int $dow): string
    {
        $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $lastTs  = mktime(0, 0, 0, $month, $lastDay, $year) ?: 0;
        $lastDow = (int) date('w', $lastTs);
        $offset  = ($lastDow - $dow + 7) % 7;
        $day = $lastDay - $offset;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * DACH set (DE + AT + CH union). Scope says which countries treat the
     * day as a public holiday. Regional holidays within a single country
     * are summarised ("DE regional") rather than listing every Bundesland.
     */
    private const DACH_DEFS = [
        ['md' => [1, 1],   'key' => 'holiday.de.new_year',           'scope' => 'DE · AT · CH'],
        ['md' => [1, 6],   'key' => 'holiday.de.epiphany',           'scope' => 'AT · DE (regional) · CH (regional)'],
        ['easter_offset' => -2,  'key' => 'holiday.de.good_friday',  'scope' => 'DE · AT · CH'],
        ['easter_offset' =>  1,  'key' => 'holiday.de.easter_monday','scope' => 'DE · AT · CH'],
        ['md' => [5, 1],   'key' => 'holiday.de.labour_day',         'scope' => 'DE · AT · CH (regional)'],
        ['easter_offset' => 39,  'key' => 'holiday.de.ascension',    'scope' => 'DE · AT · CH'],
        ['easter_offset' => 50,  'key' => 'holiday.de.whit_monday',  'scope' => 'DE · AT · CH (regional)'],
        ['easter_offset' => 60,  'key' => 'holiday.de.corpus_christi','scope' => 'AT · DE (regional) · CH (regional)'],
        ['md' => [8, 1],   'key' => 'holiday.de.swiss_national_day', 'scope' => 'CH'],
        ['md' => [8, 15],  'key' => 'holiday.de.assumption',         'scope' => 'AT · DE (regional) · CH (regional)'],
        ['md' => [10, 3],  'key' => 'holiday.de.german_unity',       'scope' => 'DE'],
        ['md' => [10, 26], 'key' => 'holiday.de.austrian_national',  'scope' => 'AT'],
        ['md' => [11, 1],  'key' => 'holiday.de.all_saints',         'scope' => 'AT · DE (regional) · CH (regional)'],
        ['md' => [12, 8],  'key' => 'holiday.de.immaculate',         'scope' => 'AT'],
        ['md' => [12, 25], 'key' => 'holiday.de.christmas_day',      'scope' => 'DE · AT · CH'],
        ['md' => [12, 26], 'key' => 'holiday.de.st_stephen',         'scope' => 'DE · AT · CH'],
    ];

    /**
     * US federal holidays. Days ending on a weekend are not shifted to
     * the preceding Friday / following Monday here — the overlay marks
     * the holiday itself, not any observance day.
     */
    private const US_DEFS = [
        ['md' => [1, 1],              'key' => 'holiday.us.new_year',       'scope' => 'US federal'],
        ['nth_weekday' => [3, 1, 1],  'key' => 'holiday.us.mlk_day',        'scope' => 'US federal'],
        ['nth_weekday' => [3, 1, 2],  'key' => 'holiday.us.presidents_day', 'scope' => 'US federal'],
        ['last_weekday' => [1, 5],    'key' => 'holiday.us.memorial_day',   'scope' => 'US federal'],
        ['md' => [6, 19],             'key' => 'holiday.us.juneteenth',     'scope' => 'US federal'],
        ['md' => [7, 4],              'key' => 'holiday.us.independence',   'scope' => 'US federal'],
        ['nth_weekday' => [1, 1, 9],  'key' => 'holiday.us.labor_day',      'scope' => 'US federal'],
        ['nth_weekday' => [2, 1, 10], 'key' => 'holiday.us.columbus_day',   'scope' => 'US federal'],
        ['md' => [11, 11],            'key' => 'holiday.us.veterans_day',   'scope' => 'US federal'],
        ['nth_weekday' => [4, 4, 11], 'key' => 'holiday.us.thanksgiving',   'scope' => 'US federal'],
        ['md' => [12, 25],            'key' => 'holiday.us.christmas',      'scope' => 'US federal'],
    ];
}
