<?php
declare(strict_types=1);

/**
 * Arbeitstage (Werktage): which weekdays count as work days (ISO 1 = Monday .. 7 = Sunday).
 * Default Mon-Fri. Basis for daily target hours and vacation-day counting (AccountRepo).
 * Public holidays come additionally from Holidays and take an entire workday's target away.
 *
 * Zwei Ebenen:
 *   - GLOBAL: `days()` / `isWorkday()` / `perWeek()` lesen die systemweite
 *     `working_days` CSV (Admin -> System). Faellt zurueck auf Mo-Fr.
 *   - PRO USER (M1.1 #192): `daysForUser()` / `isWorkdayForUser()` /
 *     `perWeekForUser()` lesen `employee_terms.workday_mask` zum Stichtag.
 *     NULL-Mask -> Fallback auf GLOBAL. So kann HR/GL fuer einen Mitarbeiter
 *     z.B. Mi-Sa setzen, ohne den Firmen-Default zu beruehren.
 */
final class Workdays
{
    public const DEFAULT = [1, 2, 3, 4, 5];
    public const LABELS = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

    /** @var ?list<int> */
    private static ?array $override = null;

    /** @return list<int> */
    public static function days(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }
        if (class_exists('Holidays')) {
            return Holidays::workingDays();
        }
        return self::DEFAULT;
    }

    /** For tests only. Pass null to reset. */
    public static function override(?array $days): void
    {
        self::$override = $days === null ? null : array_values($days);
    }

    public static function isWorkday(string|DateTimeInterface $date): bool
    {
        $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        return in_array((int) $d->format('N'), self::days(), true);
    }

    /** Number of workdays per week (divisor for daily target). */
    public static function perWeek(): int
    {
        return max(1, count(self::days()));
    }

    /**
     * Arbeitstage fuer einen konkreten User an einem Stichtag.
     * Liest employee_terms.workday_mask; NULL (oder kein Vertragsstand) => global.
     *
     * @return list<int> ISO-Dow 1..7
     */
    public static function daysForUser(int $userId, string $date): array
    {
        if (!class_exists('EmployeeRepo')) {
            return self::days();
        }
        $terms = EmployeeRepo::termsAt($userId, $date);
        $mask  = self::parseMask($terms['workday_mask'] ?? null);
        return $mask ?? self::days();
    }

    public static function isWorkdayForUser(int $userId, string|DateTimeInterface $date): bool
    {
        $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $iso = (int) $d->format('N');
        $dateStr = $d->format('Y-m-d');
        return in_array($iso, self::daysForUser($userId, $dateStr), true);
    }

    public static function perWeekForUser(int $userId, string $date): int
    {
        return max(1, count(self::daysForUser($userId, $date)));
    }

    /**
     * Terms-basierte Workday-Pruefung ohne DB-Hit. Fuer Schleifen in AccountRepo /
     * EmployeeStats die die Vertragshistorie bereits vorhalten. `$terms` darf
     * null sein (dann globaler Fallback).
     */
    public static function isWorkdayFromTerms(?array $terms, string|DateTimeInterface $date): bool
    {
        $d = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $iso = (int) $d->format('N');
        $mask = $terms === null ? null : self::parseMask($terms['workday_mask'] ?? null);
        $days = $mask ?? self::days();
        return in_array($iso, $days, true);
    }

    /** Terms-basierter Wochen-Divisor fuer dailyTarget-Berechnungen. */
    public static function perWeekFromTerms(?array $terms): int
    {
        $mask = $terms === null ? null : self::parseMask($terms['workday_mask'] ?? null);
        $days = $mask ?? self::days();
        return max(1, count($days));
    }

    /**
     * CSV '1,2,3,4,5' -> [1,2,3,4,5]. Validiert, dedupliziert, sortiert.
     * Leer/NULL/kein gueltiger Tag => null (Caller faellt auf Global zurueck).
     *
     * @return ?list<int>
     */
    public static function parseMask(?string $csv): ?array
    {
        if ($csv === null || $csv === '') {
            return null;
        }
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $n = (int) trim($part);
            if ($n >= 1 && $n <= 7) {
                $out[$n] = true;
            }
        }
        if ($out === []) {
            return null;
        }
        $out = array_keys($out);
        sort($out);
        return array_values($out);
    }

    /**
     * Normalisiert ein eingehendes Mask-Array (z.B. aus POST-Checkboxen) zu
     * einer CSV '1,2,3,4,5'. Leer => null (bedeutet globaler Default).
     * @param array<mixed> $days
     */
    public static function maskToCsv(array $days): ?string
    {
        $out = [];
        foreach ($days as $d) {
            $n = (int) $d;
            if ($n >= 1 && $n <= 7) {
                $out[$n] = true;
            }
        }
        if ($out === []) {
            return null;
        }
        $out = array_keys($out);
        sort($out);
        return implode(',', $out);
    }
}
