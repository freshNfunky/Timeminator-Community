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

    public static function saveTerms(int $userId, string $validFrom, string $type, float $weeklyHours, float $vacationDays, ?string $holidayRegion = null): void
    {
        if (class_exists('Holidays') && $holidayRegion !== null && !Holidays::isValidRegion($holidayRegion)) {
            $holidayRegion = null;
        }
        if (!isset(self::TYPES[$type])) {
            $type = self::TYPE_EMPLOYEE;
        }
        $existing = DB::one('SELECT id FROM employee_terms WHERE user_id = ? AND valid_from = ?', [$userId, $validFrom]);
        if ($existing) {
            DB::run('UPDATE employee_terms SET employment_type = ?, weekly_hours = ?, vacation_days = ?, holiday_region = ? WHERE id = ?',
                [$type, $weeklyHours, $vacationDays, $holidayRegion, $existing['id']]);
            return;
        }
        DB::insert('INSERT INTO employee_terms (user_id, valid_from, employment_type, weekly_hours, vacation_days, holiday_region, created_at) VALUES (?,?,?,?,?,?,?)',
            [$userId, $validFrom, $type, $weeklyHours, $vacationDays, $holidayRegion, now()]);
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

    /** Tagessoll in Stunden (Wochenarbeitszeit / Arbeitstage je Woche laut Preset). */
    public static function dailyTarget(array $terms): float
    {
        return (float) $terms['weekly_hours'] / Workdays::perWeek();
    }
}
