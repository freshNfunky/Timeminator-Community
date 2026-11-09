<?php
declare(strict_types=1);

/**
 * Arbeitstage (Werktage): which weekdays count as work days (ISO 1 = Monday .. 7 = Sunday).
 * Default Mon-Fri. Basis for daily target hours and vacation-day counting (AccountRepo).
 * Public holidays come additionally from Holidays and take an entire workday's target away.
 *
 * Reads the same `working_days` CSV setting that Holidays::workingDays() uses, so the
 * Admin -> System -> Werktage toggles drive both overlays and the cockpit.
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
}
