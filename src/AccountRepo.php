<?php
declare(strict_types=1);

/**
 * Mitarbeiterkonten (v1.0 F1), rein berechnet aus Buchungen, Abwesenheiten
 * und Feiertagen — es gibt keine Saldo-Tabelle, die auseinanderlaufen koennte.
 *
 * - Urlaubskonto (Tage): Jahresanspruch + Resturlaub aus dem Vorjahr -
 *   genommene Urlaubstage (nur Arbeitstage laut Preset, Feiertage und
 *   Wochenenden zaehlen nicht als Urlaubstag).
 * - Ueberstundenkonto (Stunden), nur Beschaeftigungsart "Angestellt":
 *   Uebertrag + je Tag (gebuchte Stunden - Tagessoll). Gebucht zaehlt
 *   Projektzeit und interne Prozesse.
 *     * Tagessoll = Wochenstunden / Arbeitstage je Woche an Arbeitstagen,
 *       0 an freien Tagen und Feiertagen.
 *     * Urlaub und Krankheit setzen das Tagessoll auf 0 (bezahlter Ausfall).
 *     * Ueberstundenabbau zieht Stunden vom Konto ab und reduziert das
 *       Tagessoll um dieselben Stunden.
 * - Krankheitskonto: nur Statistik (Tage), ohne Wirkung.
 *
 * Portiert aus Pro — keine Geldbetraege, keine Stundensaetze.
 */
final class AccountRepo
{
    /**
     * @return array{terms:?array,year:int,as_of:string,vacation:?array,overtime:?array,sick_days:float,
     *               week_hours:float,week_credit:float,month_hours:float,holiday:?string}
     */
    public static function summary(int $userId, ?string $asOf = null): array
    {
        $asOf = $asOf ?: (new DateTimeImmutable('today'))->format('Y-m-d');
        $year = (int) substr($asOf, 0, 4);
        $terms = EmployeeRepo::termsAt($userId, $asOf);
        $region = $terms['holiday_region'] ?? null;
        $weekStart = self::weekStart($asOf);

        $out = [
            'terms' => $terms, 'year' => $year, 'as_of' => $asOf,
            'vacation' => null, 'overtime' => null,
            'sick_days' => self::absenceDays($userId, 'sick', $year . '-01-01', $asOf),
            'week_hours' => self::bookedHours($userId, $weekStart, $asOf),
            'week_credit' => self::weekCredit($userId, $terms, $weekStart),
            'month_hours' => self::bookedHours($userId, substr($asOf, 0, 7) . '-01', $asOf),
            'holiday' => Holidays::on($region, $asOf),
        ];
        if (!EmployeeRepo::hasAccounts($terms)) {
            return $out;
        }

        $carry = EmployeeRepo::carryover($userId, $year);
        $taken = self::absenceDays($userId, 'vacation', $year . '-01-01', $asOf);
        $planned = self::absenceDays($userId, 'vacation', (new DateTimeImmutable($asOf))->modify('+1 day')->format('Y-m-d'), $year . '-12-31');
        $entitlement = (float) $terms['vacation_days'];
        $out['vacation'] = [
            'entitlement' => $entitlement,
            'carryover' => $carry['vacation_days'],
            'taken' => $taken,
            'planned' => $planned,
            'remaining' => $entitlement + $carry['vacation_days'] - $taken - $planned,
        ];

        if (EmployeeRepo::hasOvertimeAccount($terms)) {
            $out['overtime'] = self::overtime($userId, $year, $asOf, $carry['overtime_hours']);
        }
        return $out;
    }

    /**
     * Ueberstundensaldo des Jahres bis gestern.
     * @return array{carryover:float,earned:float,reduced:float,balance:float}
     */
    public static function overtime(int $userId, int $year, string $asOf, float $carryover = 0.0): array
    {
        $from = $year . '-01-01';
        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $asOf = min($asOf, $yesterday);
        $history = array_reverse(EmployeeRepo::history($userId));
        $booked = self::bookedHoursByDay($userId, $from, $asOf);
        $absence = self::absencesByDay($userId, $from, $asOf, $history);

        $earned = 0.0;
        $reduced = 0.0;
        $day = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($asOf);
        for (; $day <= $end; $day = $day->modify('+1 day')) {
            $d = $day->format('Y-m-d');
            $terms = self::termsFrom($history, $d);
            if (!EmployeeRepo::hasOvertimeAccount($terms)) {
                continue;
            }
            [$target, $take] = self::effectiveTarget($terms, $d, $absence[$d] ?? []);
            $reduced += $take;
            $earned += ($booked[$d] ?? 0.0) - $target;
        }
        $earned = round($earned, 2);
        $reduced = round($reduced, 2);
        return [
            'carryover' => $carryover,
            'earned' => $earned,
            'reduced' => $reduced,
            'balance' => round($carryover + $earned - $reduced, 2),
        ];
    }

    /**
     * Soll des Tages nach Abwesenheiten.
     * @return array{0:float,1:float} [effektives Soll in Stunden, abgebaute Ueberstunden]
     */
    public static function effectiveTarget(array $terms, string $date, array $absDay): array
    {
        $target = self::dayTarget($terms, $date);
        if (isset($absDay['vacation']) || isset($absDay['sick'])) {
            return [0.0, 0.0];
        }
        if (isset($absDay['overtime']) && $target > 0) {
            $take = $absDay['overtime']['hours'] === null ? $target : min((float) $absDay['overtime']['hours'], $target);
            return [$target - $take, $take];
        }
        return [$target, 0.0];
    }

    public static function dayTarget(array $terms, string $date): float
    {
        if (!Workdays::isWorkday($date)) {
            return 0.0;
        }
        if (Holidays::on($terms['holiday_region'] ?? null, $date) !== null) {
            return 0.0;
        }
        return EmployeeRepo::dailyTarget($terms);
    }

    public static function weekCredit(int $userId, ?array $terms, string $weekStart): float
    {
        if (!EmployeeRepo::hasAccounts($terms)) {
            return 0.0;
        }
        $history = array_reverse(EmployeeRepo::history($userId));
        $weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
        $abs = self::absencesByDay($userId, $weekStart, $weekEnd, $history);
        $credit = 0.0;
        for ($d = new DateTimeImmutable($weekStart); $d->format('Y-m-d') <= $weekEnd; $d = $d->modify('+1 day')) {
            $day = $d->format('Y-m-d');
            $t = self::termsFrom($history, $day);
            if ($t === null || $t['employment_type'] === EmployeeRepo::TYPE_FREELANCER || !Workdays::isWorkday($d)) {
                continue;
            }
            $daily = EmployeeRepo::dailyTarget($t);
            if (Holidays::on($t['holiday_region'] ?? null, $day) !== null || isset($abs[$day]['vacation']) || isset($abs[$day]['sick'])) {
                $credit += $daily;
            }
        }
        return round($credit, 2);
    }

    public static function absenceDays(int $userId, string $account, string $from, string $to): float
    {
        if ($from > $to) {
            return 0.0;
        }
        $history = array_reverse(EmployeeRepo::history($userId));
        $n = 0;
        foreach (self::absencesByDay($userId, $from, $to, $history) as $accounts) {
            if (isset($accounts[$account])) {
                $n++;
            }
        }
        return (float) $n;
    }

    /**
     * Abwesenheiten je Tag und Konto, nur an Arbeitstagen und ohne Feiertage
     * der Mitarbeiter-Region.
     *
     * @return array<string,array<string,array{hours:?float}>>
     */
    public static function absencesByDay(int $userId, string $from, string $to, ?array $history = null): array
    {
        $history ??= array_reverse(EmployeeRepo::history($userId));
        $rows = DB::all(
            'SELECT a.start_date, a.end_date, a.hours, c.account
               FROM absences a JOIN absence_categories c ON c.id = a.category_id
              WHERE a.user_id = ? AND c.account IS NOT NULL AND a.end_date >= ? AND a.start_date <= ?',
            [$userId, $from, $to]
        );
        $out = [];
        foreach ($rows as $r) {
            $account = (string) $r['account'];
            $d = new DateTimeImmutable(max(substr((string) $r['start_date'], 0, 10), $from));
            $end = new DateTimeImmutable(min(substr((string) $r['end_date'], 0, 10), $to));
            for (; $d <= $end; $d = $d->modify('+1 day')) {
                $day = $d->format('Y-m-d');
                if (!Workdays::isWorkday($d)) {
                    continue;
                }
                $terms = self::termsFrom($history, $day);
                if (Holidays::on($terms['holiday_region'] ?? null, $day) !== null) {
                    continue;
                }
                $hours = $r['hours'] !== null ? (float) $r['hours'] : null;
                if (!isset($out[$day][$account])) {
                    $out[$day][$account] = ['hours' => $hours];
                } elseif ($hours === null || $out[$day][$account]['hours'] === null) {
                    $out[$day][$account]['hours'] = null;
                } else {
                    $out[$day][$account]['hours'] += $hours;
                }
            }
        }
        return $out;
    }

    private static function termsFrom(array $historyAsc, string $date): ?array
    {
        $found = null;
        foreach ($historyAsc as $t) {
            if ($t['valid_from'] <= $date) { $found = $t; } else { break; }
        }
        return $found;
    }

    /** @return array<string,float> Datum => gebuchte Stunden (Projektzeit + interne Prozesse). */
    public static function bookedHoursByDay(int $userId, string $from, string $to): array
    {
        $out = [];
        foreach (['time_entries', 'process_entries'] as $table) {
            $rows = DB::all(
                "SELECT substr(start_ts, 1, 10) AS d, SUM(duration_min) AS m FROM $table
                  WHERE user_id = ? AND duration_min IS NOT NULL AND substr(start_ts, 1, 10) >= ? AND substr(start_ts, 1, 10) <= ?
                  GROUP BY substr(start_ts, 1, 10)",
                [$userId, $from, $to]
            );
            foreach ($rows as $r) {
                $out[$r['d']] = ($out[$r['d']] ?? 0.0) + (float) $r['m'] / 60;
            }
        }
        return $out;
    }

    public static function bookedHours(int $userId, string $from, string $to): float
    {
        return round(array_sum(self::bookedHoursByDay($userId, $from, $to)), 2);
    }

    private static function weekStart(string $date): string
    {
        $d = new DateTimeImmutable($date);
        return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
    }
}
