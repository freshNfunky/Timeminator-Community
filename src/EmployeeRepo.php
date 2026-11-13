<?php
declare(strict_types=1);

/**
 * Mitarbeiter-Vertragsdaten (v1.0 F1): Beschaeftigungsart, Wochenarbeitszeit
 * und Urlaubsanspruch mit Gueltigkeit ab Datum (historisiert — Vertragsaenderungen
 * ueberschreiben keine alten Konten) sowie die Jahresuebertraege (Resturlaub,
 * Ueberstundensaldo aus dem Vorjahr).
 *
 * Portiert aus Pro ohne Preis-/Rate-Felder (Community = reine Zeiterfassung).
 */
final class EmployeeRepo
{
    public const TYPE_EMPLOYEE   = 'employee';    // Wochenkontingent + Ueberstundenkonto + Urlaubskonto
    public const TYPE_EXEC       = 'exec';        // Urlaubskonto, aber kein Ueberstundenkonto
    public const TYPE_FREELANCER = 'freelancer';  // keine Konten

    public const TYPES = [
        self::TYPE_EMPLOYEE   => 'Angestellt',
        self::TYPE_EXEC       => 'Angestellt, aussertariflich',
        self::TYPE_FREELANCER => 'Freelancer',
    ];

    public static function typeLabel(string $key): string
    {
        return match ($key) {
            self::TYPE_EMPLOYEE   => t('employee.type_employee',   'Angestellt'),
            self::TYPE_EXEC       => t('employee.type_exec',       'Angestellt, aussertariflich'),
            self::TYPE_FREELANCER => t('employee.type_freelancer', 'Freelancer'),
            default => $key,
        };
    }

    /** Gueltige Vertragsdaten zum Stichtag, null wenn fuer den Nutzer nichts hinterlegt ist. */
    public static function termsAt(int $userId, string $date): ?array
    {
        return DB::one(
            'SELECT * FROM employee_terms WHERE user_id = ? AND valid_from <= ? ORDER BY valid_from DESC, id DESC LIMIT 1',
            [$userId, $date]
        );
    }

    /** @return list<array<string,mixed>> alle Vertragsstaende, neueste zuerst */
    public static function history(int $userId): array
    {
        return DB::all('SELECT * FROM employee_terms WHERE user_id = ? ORDER BY valid_from DESC, id DESC', [$userId]);
    }

    /**
     * @param ?array<mixed> $workdayMask ISO-Dow Liste (z.B. [1,2,3,4,5]). null/leer => globaler Default.
     */
    public static function saveTerms(int $userId, string $validFrom, string $type, float $weeklyHours, float $vacationDays, ?string $holidayRegion = null, ?array $workdayMask = null): void
    {
        if (class_exists('Holidays') && $holidayRegion !== null && !Holidays::isValidRegion($holidayRegion)) {
            $holidayRegion = null;
        }
        if (!isset(self::TYPES[$type])) {
            $type = self::TYPE_EMPLOYEE;
        }
        $maskCsv = $workdayMask === null ? null : Workdays::maskToCsv($workdayMask);
        $existing = DB::one('SELECT id FROM employee_terms WHERE user_id = ? AND valid_from = ?', [$userId, $validFrom]);
        if ($existing) {
            DB::run('UPDATE employee_terms SET employment_type = ?, weekly_hours = ?, vacation_days = ?, holiday_region = ?, workday_mask = ? WHERE id = ?',
                [$type, $weeklyHours, $vacationDays, $holidayRegion, $maskCsv, $existing['id']]);
            return;
        }
        DB::insert('INSERT INTO employee_terms (user_id, valid_from, employment_type, weekly_hours, vacation_days, holiday_region, workday_mask, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$userId, $validFrom, $type, $weeklyHours, $vacationDays, $holidayRegion, $maskCsv, now()]);
    }

    public static function deleteTerms(int $id): void
    {
        DB::run('DELETE FROM employee_terms WHERE id = ?', [$id]);
    }

    public static function carryover(int $userId, int $year): array
    {
        $row = DB::one('SELECT vacation_days, overtime_hours FROM account_carryovers WHERE user_id = ? AND year = ?', [$userId, $year]);
        return ['vacation_days' => (float) ($row['vacation_days'] ?? 0), 'overtime_hours' => (float) ($row['overtime_hours'] ?? 0)];
    }

    /**
     * Wie carryover(), aber liefert null wenn in der DB fuer diese Kombination
     * (user, year) noch keine Zeile existiert. Fuer die Admin-View, die beim
     * noch-nicht-gespeicherten Carryover den Jahres-Urlaubsanspruch als Default
     * zeigen muss (Felix-Feedback 2026-11-07).
     * @return array{vacation_days:float,overtime_hours:float}|null
     */
    public static function carryoverRow(int $userId, int $year): ?array
    {
        $row = DB::one('SELECT vacation_days, overtime_hours FROM account_carryovers WHERE user_id = ? AND year = ?', [$userId, $year]);
        if (!$row) { return null; }
        return [
            'vacation_days'  => (float) $row['vacation_days'],
            'overtime_hours' => (float) $row['overtime_hours'],
        ];
    }

    public static function saveCarryover(int $userId, int $year, float $vacationDays, float $overtimeHours): void
    {
        $existing = DB::one('SELECT id FROM account_carryovers WHERE user_id = ? AND year = ?', [$userId, $year]);
        if ($existing) {
            DB::run('UPDATE account_carryovers SET vacation_days = ?, overtime_hours = ? WHERE id = ?', [$vacationDays, $overtimeHours, $existing['id']]);
        } else {
            DB::insert('INSERT INTO account_carryovers (user_id, year, vacation_days, overtime_hours) VALUES (?,?,?,?)',
                [$userId, $year, $vacationDays, $overtimeHours]);
        }
    }

    public static function hasAccounts(?array $terms): bool
    {
        return $terms !== null && $terms['employment_type'] !== self::TYPE_FREELANCER;
    }

    public static function hasOvertimeAccount(?array $terms): bool
    {
        return $terms !== null && $terms['employment_type'] === self::TYPE_EMPLOYEE;
    }

    /**
     * Tagessoll in Stunden (Wochenarbeitszeit / Arbeitstage je Woche).
     * Wenn der Vertragsstand eine eigene workday_mask hat, zaehlen deren
     * Tage; sonst globaler Default.
     */
    public static function dailyTarget(array $terms): float
    {
        $mask = Workdays::parseMask($terms['workday_mask'] ?? null);
        $perWeek = $mask !== null ? max(1, count($mask)) : Workdays::perWeek();
        return (float) $terms['weekly_hours'] / $perWeek;
    }

    /**
     * Tagessoll in Minuten fuer einen User an einem bestimmten Tag.
     *
     * Felix-Feedback 2026-11-08 (#99): die Tagessoll-Klammer im Kalender
     * braucht genau diesen Wert. Freelancer (kein employee_terms-Row,
     * employment_type != EMPLOYEE) bekommen `null`, dann wird keine
     * Klammer gerendert.
     *
     * Felix-Feedback 2026-11-09 (#119): Wochenenden (!working_days) und
     * gesetzliche Feiertage (holiday_region des Nutzers) sind FREIE Tage
     * -> Rueckgabe 0. Die View unterscheidet: null=kein Vertrag (kein Badge),
     * 0=freier Tag (kein Badge, zaehlt nicht), >0=Werktag mit Sollwert.
     */
    public static function dailyTargetMinutes(int $userId, string $date): ?int
    {
        $terms = self::termsAt($userId, $date);
        if (!$terms || $terms['employment_type'] !== self::TYPE_EMPLOYEE) {
            return null;
        }
        // Wochenende: ISO-Dow (1..7) nicht in den Werktagen des Users.
        // Pro-User-Mask (workday_mask) gewinnt vor globalem Default.
        try {
            $iso = (int) (new DateTimeImmutable($date))->format('N');
        } catch (Throwable) {
            return null;
        }
        $userMask = Workdays::parseMask($terms['workday_mask'] ?? null);
        $workdays = $userMask ?? Holidays::workingDays();
        if (!in_array($iso, $workdays, true)) {
            return 0;
        }
        // Feiertag in der Region des Users.
        $region = (string) ($terms['holiday_region'] ?? '');
        if ($region !== '' && Holidays::on($region, $date) !== null) {
            return 0;
        }
        return (int) round(self::dailyTarget($terms) * 60);
    }
}
