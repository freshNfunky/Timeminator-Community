<?php
declare(strict_types=1);

/**
 * Static holiday overlay (region-based; Pro-Sync B.1, #110 + v1.0.0 refit).
 *
 * The user picks one region code (e.g. `DE-BY`, `AT`, `CH-ZH`, `US-FED`)
 * in Admin -> System. The week calendar then renders markers in the
 * day-head only for holidays that are statutory in that region.
 *
 * Pro (TM-Pro #217) will add multi-region subscription + CalDAV; see
 * that issue for the richer sidebar-style UI.
 *
 * Each definition carries an explicit `regions` array of region codes
 * where the day is a statutory holiday. Region codes follow ISO 3166-2
 * (DE-BY, AT-W, CH-ZH, US-CA, ...), plus synthetic *-ALL / *-FED codes
 * for the country-level feed (DE-ALL = holidays statutory in every
 * Bundesland; US-FED = federal only).
 */
final class Holidays
{
    /**
     * @return array<int,array{date:string,key:string,regions:array<int,string>}>
     *   One entry per holiday that falls in `$year` for `$region`.
     */
    public static function forRegion(string $region, int $year): array
    {
        $region = self::normalizeRegion($region);
        if ($region === '' || $region === '__none__') { return []; }

        $easter = self::easterSundayTimestamp($year);
        $out = [];
        foreach (self::DEFS as $def) {
            if (!self::matchesRegion($def['regions'], $region)) { continue; }
            $date = self::resolveDate($def, $year, $easter);
            if ($date === null) { continue; }
            $out[] = [
                'date'    => $date,
                'key'     => $def['key'],
                'regions' => $def['regions'],
            ];
        }
        usort($out, static fn(array $a, array $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /** Convenience: just the entries that fall within a given date range. */
    public static function between(string $fromYmd, string $toYmd, string $region): array
    {
        $from = (int) substr($fromYmd, 0, 4);
        $to   = (int) substr($toYmd,   0, 4);
        $out = [];
        for ($y = $from; $y <= $to; $y++) {
            foreach (self::forRegion($region, $y) as $h) {
                if (strcmp($h['date'], $fromYmd) >= 0 && strcmp($h['date'], $toYmd) <= 0) {
                    $out[] = $h;
                }
            }
        }
        return $out;
    }

    /** @return array<string,array{date:string,key:string,regions:array<int,string>}> */
    public static function byDate(string $fromYmd, string $toYmd, string $region): array
    {
        $out = [];
        foreach (self::between($fromYmd, $toYmd, $region) as $h) {
            $out[$h['date']] = $h;
        }
        return $out;
    }

    /**
     * Translated name of the holiday on `$date` in `$region`, or null if the
     * day is not a statutory holiday there. Used by the Mitarbeiter-Cockpit
     * (v1.0 F1) to flag "heute: Pfingstmontag" and by AccountRepo to zero out
     * the daily target. A `null` or invalid region yields `null`.
     */
    public static function on(?string $region, string $date): ?string
    {
        if ($region === null || !self::isValidRegion($region)) {
            return null;
        }
        $year = (int) substr($date, 0, 4);
        foreach (self::forRegion($region, $year) as $h) {
            if ($h['date'] === substr($date, 0, 10)) {
                return t($h['key'], $h['key']);
            }
        }
        return null;
    }

    /**
     * Catalog for the Admin -> System dropdown, grouped by country.
     * Returns ordered groups so the UI can render <optgroup>s.
     *
     * @return array<int,array{country:string, label_key:string, options:array<int,array{code:string,label_key:string}>}>
     */
    public static function catalog(): array
    {
        return [
            [
                'country'   => 'DE',
                'label_key' => 'holiday.country.de',
                'options'   => [
                    ['code' => 'DE-ALL', 'label_key' => 'holiday.region.de_all'],
                    ['code' => 'DE-BW',  'label_key' => 'holiday.region.de_bw'],
                    ['code' => 'DE-BY',  'label_key' => 'holiday.region.de_by'],
                    ['code' => 'DE-BE',  'label_key' => 'holiday.region.de_be'],
                    ['code' => 'DE-BB',  'label_key' => 'holiday.region.de_bb'],
                    ['code' => 'DE-HB',  'label_key' => 'holiday.region.de_hb'],
                    ['code' => 'DE-HH',  'label_key' => 'holiday.region.de_hh'],
                    ['code' => 'DE-HE',  'label_key' => 'holiday.region.de_he'],
                    ['code' => 'DE-MV',  'label_key' => 'holiday.region.de_mv'],
                    ['code' => 'DE-NI',  'label_key' => 'holiday.region.de_ni'],
                    ['code' => 'DE-NW',  'label_key' => 'holiday.region.de_nw'],
                    ['code' => 'DE-RP',  'label_key' => 'holiday.region.de_rp'],
                    ['code' => 'DE-SL',  'label_key' => 'holiday.region.de_sl'],
                    ['code' => 'DE-SN',  'label_key' => 'holiday.region.de_sn'],
                    ['code' => 'DE-ST',  'label_key' => 'holiday.region.de_st'],
                    ['code' => 'DE-SH',  'label_key' => 'holiday.region.de_sh'],
                    ['code' => 'DE-TH',  'label_key' => 'holiday.region.de_th'],
                ],
            ],
            [
                'country'   => 'AT',
                'label_key' => 'holiday.country.at',
                'options'   => [
                    ['code' => 'AT-ALL', 'label_key' => 'holiday.region.at_all'],
                ],
            ],
            [
                'country'   => 'CH',
                'label_key' => 'holiday.country.ch',
                'options'   => [
                    ['code' => 'CH-ALL', 'label_key' => 'holiday.region.ch_all'],
                    ['code' => 'CH-ZH',  'label_key' => 'holiday.region.ch_zh'],
                    ['code' => 'CH-BE',  'label_key' => 'holiday.region.ch_be'],
                    ['code' => 'CH-BS',  'label_key' => 'holiday.region.ch_bs'],
                    ['code' => 'CH-GE',  'label_key' => 'holiday.region.ch_ge'],
                ],
            ],
            [
                'country'   => 'US',
                'label_key' => 'holiday.country.us',
                'options'   => [
                    ['code' => 'US-FED', 'label_key' => 'holiday.region.us_fed'],
                ],
            ],
        ];
    }

    /** All valid region codes (plus the sentinel `__none__`). */
    public static function regionCodes(): array
    {
        $out = ['__none__'];
        foreach (self::catalog() as $group) {
            foreach ($group['options'] as $opt) { $out[] = $opt['code']; }
        }
        return $out;
    }

    public static function isValidRegion(string $region): bool
    {
        return in_array($region, self::regionCodes(), true);
    }

    /** Default when nothing is set yet. German installation most common. */
    public static function defaultRegion(): string { return 'DE-ALL'; }

    /**
     * ISO weekday numbers that count as working days (Mon=1 … Sun=7).
     * Default: Mon–Fri. Can be overridden per instance (e.g. retail: +Sat).
     * Stored as CSV in Settings; invalid values are discarded, an empty
     * result falls back to Mon–Fri so the UI never ends up with 7 "free"
     * days by accident.
     *
     * @return array<int,int> sorted ascending
     */
    public static function workingDays(): array
    {
        $raw = (string) Settings::get('working_days', '1,2,3,4,5');
        $out = [];
        foreach (explode(',', $raw) as $token) {
            $n = (int) trim($token);
            if ($n >= 1 && $n <= 7) { $out[$n] = true; }
        }
        $days = array_keys($out);
        if (!$days) { $days = [1, 2, 3, 4, 5]; }
        sort($days);
        return $days;
    }

    public static function isWorkingDay(int $isoDow): bool
    {
        return in_array($isoDow, self::workingDays(), true);
    }

    /**
     * The *currently active* region taken from Settings; falls back to default.
     * Pulled from `holiday_region` so the admin can switch without a redeploy.
     */
    public static function currentRegion(): string
    {
        $r = (string) Settings::get('holiday_region', self::defaultRegion());
        return self::isValidRegion($r) ? $r : self::defaultRegion();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function normalizeRegion(string $r): string
    {
        return strtoupper(trim($r)) === '__NONE__' ? '__none__' : strtoupper(trim($r));
    }

    /**
     * A region matches if either:
     *  - the exact code is listed, or
     *  - the country-wide *-ALL / US-FED sentinel is listed and the given
     *    region belongs to that country.
     *
     * Picking `DE-ALL` therefore shows only the days that are statutory in
     * EVERY Bundesland — the correct basis for the v1.1 40h-target reduction.
     * Who wants regional days such as Allerheiligen must pick a concrete
     * state (DE-BY, DE-NW, …).
     */
    private static function matchesRegion(array $regions, string $region): bool
    {
        if (in_array($region, $regions, true)) { return true; }
        $country = substr($region, 0, 2);
        $allCode = $country === 'US' ? 'US-FED' : $country . '-ALL';
        return in_array($allCode, $regions, true);
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

    private static function easterSundayTimestamp(int $year): int
    {
        if (function_exists('easter_date')) { return easter_date($year); }
        $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100;
        $d = intdiv($b, 4); $e = $b % 4;
        $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return mktime(0, 0, 0, $month, $day, $year) ?: 0;
    }

    private static function nthWeekdayOfMonth(int $year, int $month, int $dow, int $n): ?string
    {
        $first = mktime(0, 0, 0, $month, 1, $year);
        if ($first === false) { return null; }
        $firstDow = (int) date('w', $first);
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
        return sprintf('%04d-%02d-%02d', $year, $month, $lastDay - $offset);
    }

    /**
     * Holiday catalog.
     *
     * `regions` lists exact ISO 3166-2 codes AND/OR country-wide sentinels
     * (DE-ALL / AT-ALL / CH-ALL / US-FED). A day that is statutory in every
     * Bundesland uses DE-ALL; a regional day lists only the states where it
     * applies.
     */
    private const DEFS = [
        // ---- Germany ----
        ['md' => [1, 1],              'key' => 'holiday.de.new_year',           'regions' => ['DE-ALL']],
        ['md' => [1, 6],              'key' => 'holiday.de.epiphany',           'regions' => ['DE-BW','DE-BY','DE-ST']],
        ['md' => [3, 8],              'key' => 'holiday.de.womens_day',         'regions' => ['DE-BE','DE-MV']],
        ['easter_offset' => -2,       'key' => 'holiday.de.good_friday',        'regions' => ['DE-ALL']],
        ['easter_offset' =>  1,       'key' => 'holiday.de.easter_monday',      'regions' => ['DE-ALL']],
        ['md' => [5, 1],              'key' => 'holiday.de.labour_day',         'regions' => ['DE-ALL']],
        ['easter_offset' => 39,       'key' => 'holiday.de.ascension',          'regions' => ['DE-ALL']],
        ['easter_offset' => 50,       'key' => 'holiday.de.whit_monday',        'regions' => ['DE-ALL']],
        ['easter_offset' => 60,       'key' => 'holiday.de.corpus_christi',     'regions' => ['DE-BW','DE-BY','DE-HE','DE-NW','DE-RP','DE-SL']],
        ['md' => [8, 15],             'key' => 'holiday.de.assumption',         'regions' => ['DE-SL','DE-BY']],
        ['md' => [9, 20],             'key' => 'holiday.de.childrens_day',      'regions' => ['DE-TH']],
        ['md' => [10, 3],             'key' => 'holiday.de.german_unity',       'regions' => ['DE-ALL']],
        ['md' => [10, 31],            'key' => 'holiday.de.reformation',        'regions' => ['DE-BB','DE-HB','DE-HH','DE-MV','DE-NI','DE-SN','DE-ST','DE-SH','DE-TH']],
        ['md' => [11, 1],             'key' => 'holiday.de.all_saints',         'regions' => ['DE-BW','DE-BY','DE-NW','DE-RP','DE-SL']],
        ['md' => [12, 25],            'key' => 'holiday.de.christmas_day',      'regions' => ['DE-ALL']],
        ['md' => [12, 26],            'key' => 'holiday.de.st_stephen',         'regions' => ['DE-ALL']],

        // ---- Austria ----
        ['md' => [1, 1],              'key' => 'holiday.at.new_year',           'regions' => ['AT-ALL']],
        ['md' => [1, 6],              'key' => 'holiday.at.epiphany',           'regions' => ['AT-ALL']],
        ['easter_offset' =>  1,       'key' => 'holiday.at.easter_monday',      'regions' => ['AT-ALL']],
        ['md' => [5, 1],              'key' => 'holiday.at.labour_day',         'regions' => ['AT-ALL']],
        ['easter_offset' => 39,       'key' => 'holiday.at.ascension',          'regions' => ['AT-ALL']],
        ['easter_offset' => 50,       'key' => 'holiday.at.whit_monday',        'regions' => ['AT-ALL']],
        ['easter_offset' => 60,       'key' => 'holiday.at.corpus_christi',     'regions' => ['AT-ALL']],
        ['md' => [8, 15],             'key' => 'holiday.at.assumption',         'regions' => ['AT-ALL']],
        ['md' => [10, 26],            'key' => 'holiday.at.national_day',       'regions' => ['AT-ALL']],
        ['md' => [11, 1],             'key' => 'holiday.at.all_saints',         'regions' => ['AT-ALL']],
        ['md' => [12, 8],             'key' => 'holiday.at.immaculate',         'regions' => ['AT-ALL']],
        ['md' => [12, 25],            'key' => 'holiday.at.christmas_day',      'regions' => ['AT-ALL']],
        ['md' => [12, 26],            'key' => 'holiday.at.st_stephen',         'regions' => ['AT-ALL']],

        // ---- Switzerland ----
        ['md' => [1, 1],              'key' => 'holiday.ch.new_year',           'regions' => ['CH-ALL']],
        ['easter_offset' => -2,       'key' => 'holiday.ch.good_friday',        'regions' => ['CH-ZH','CH-BE','CH-GE']],
        ['easter_offset' =>  1,       'key' => 'holiday.ch.easter_monday',      'regions' => ['CH-ZH','CH-BE']],
        ['md' => [5, 1],              'key' => 'holiday.ch.labour_day',         'regions' => ['CH-ZH','CH-BS']],
        ['easter_offset' => 39,       'key' => 'holiday.ch.ascension',          'regions' => ['CH-ALL']],
        ['easter_offset' => 50,       'key' => 'holiday.ch.whit_monday',        'regions' => ['CH-ZH','CH-BE']],
        ['md' => [8, 1],              'key' => 'holiday.ch.national_day',       'regions' => ['CH-ALL']],
        ['md' => [12, 25],            'key' => 'holiday.ch.christmas_day',      'regions' => ['CH-ALL']],
        ['md' => [12, 26],            'key' => 'holiday.ch.st_stephen',         'regions' => ['CH-ZH','CH-BE']],

        // ---- US federal ----
        ['md' => [1, 1],              'key' => 'holiday.us.new_year',           'regions' => ['US-FED']],
        ['nth_weekday' => [3, 1, 1],  'key' => 'holiday.us.mlk_day',            'regions' => ['US-FED']],
        ['nth_weekday' => [3, 1, 2],  'key' => 'holiday.us.presidents_day',     'regions' => ['US-FED']],
        ['last_weekday' => [1, 5],    'key' => 'holiday.us.memorial_day',       'regions' => ['US-FED']],
        ['md' => [6, 19],             'key' => 'holiday.us.juneteenth',         'regions' => ['US-FED']],
        ['md' => [7, 4],              'key' => 'holiday.us.independence',       'regions' => ['US-FED']],
        ['nth_weekday' => [1, 1, 9],  'key' => 'holiday.us.labor_day',          'regions' => ['US-FED']],
        ['nth_weekday' => [2, 1, 10], 'key' => 'holiday.us.columbus_day',       'regions' => ['US-FED']],
        ['md' => [11, 11],            'key' => 'holiday.us.veterans_day',       'regions' => ['US-FED']],
        ['nth_weekday' => [4, 4, 11], 'key' => 'holiday.us.thanksgiving',       'regions' => ['US-FED']],
        ['md' => [12, 25],            'key' => 'holiday.us.christmas',          'regions' => ['US-FED']],
    ];
}
