<?php
declare(strict_types=1);

/**
 * Mitarbeiter-Statistik (v1.0 F1): woechentliche Verteilung der Arbeitszeit
 * eines Mitarbeiters, gestapelt aus Projektkategorien (extern/intern/
 * Forschung ...), internen Prozessen, Feiertagen, Urlaub, Krankheit,
 * Ueberstundenabbau.
 *
 * Zusammenfassung von Kennzahlen (KPI), keine Kontrolle: hat ein
 * Vollzeit-Mitarbeiter seine Stunden vollstaendig erfasst, ergibt der Stapel
 * je Woche das Wochensoll (z. B. 40 h) und die Oberkante ist flach.
 * Abweichungen (Unterbuchung/Leerlauf, Mehrarbeit) machen die Oberkante
 * "bergig" — deshalb gibt es bewusst keine Fuellserie "nicht gebucht", die
 * die Kante kuenstlich glaetten wuerde.
 */
final class EmployeeStats
{
    private const PROJECT_COLORS = ['#2563eb', '#06b6d4', '#6366f1', '#0ea5e9', '#3b82f6', '#22d3ee', '#818cf8'];

    /**
     * @return array{user:string,weeks:list<string>,week_starts:list<string>,target:list<?float>,
     *               series:list<array{key:string,label:string,color:string,values:list<float>}>}
     */
    public static function weekly(int $userId, int $weeks = 26, ?string $endDate = null): array
    {
        $weeks = max(4, min(104, $weeks));
        $end = new DateTimeImmutable($endDate ?: 'today');
        $thisMonday = $end->modify('-' . ((int) $end->format('N') - 1) . ' days');
        $firstMonday = $thisMonday->modify('-' . ($weeks - 1) . ' weeks');
        $from = $firstMonday->format('Y-m-d');
        $to = $thisMonday->modify('+6 days')->format('Y-m-d');

        $starts = [];
        $labels = [];
        for ($i = 0; $i < $weeks; $i++) {
            $m = $firstMonday->modify("+$i weeks");
            $starts[] = $m->format('Y-m-d');
            $isCurrent = $m->format('Y-m-d') === $thisMonday->format('Y-m-d') && $end >= new DateTimeImmutable('today');
            $labels[] = 'KW ' . $m->format('W') . ($isCurrent ? ' ' . t('cockpit.week_current_suffix', '(laufend)') : '');
        }
        $weekIndex = fn(string $date) => (int) floor((new DateTimeImmutable($date))->diff($firstMonday)->days / 7);
        $zero = fn() => array_fill(0, $weeks, 0.0);

        // --- Projektzeit je Projektkategorie
        $project = [];  // label => ['color'=>..., 'values'=>[]]
        $uncategorized = t('cockpit.project_uncategorized', 'Ohne Kategorie');
        $rows = DB::all(
            "SELECT substr(te.start_ts, 1, 10) AS d, COALESCE(pc.position, 9999) AS pos, COALESCE(pc.label, ?) AS lbl, SUM(te.duration_min) AS m
               FROM time_entries te
               JOIN projects p ON p.id = te.project_id
               LEFT JOIN project_categories pc ON pc.id = p.category_id
              WHERE te.user_id = ? AND te.duration_min IS NOT NULL AND substr(te.start_ts, 1, 10) >= ? AND substr(te.start_ts, 1, 10) <= ?
              GROUP BY substr(te.start_ts, 1, 10), COALESCE(pc.position, 9999), COALESCE(pc.label, ?)
              ORDER BY 2, 3",
            [$uncategorized, $userId, $from, $to, $uncategorized]
        );
        foreach ($rows as $r) {
            $project[$r['lbl']] ??= ['pos' => (int) $r['pos'], 'values' => $zero()];
            $i = $weekIndex($r['d']);
            if ($i >= 0 && $i < $weeks) {
                $project[$r['lbl']]['values'][$i] += (float) $r['m'] / 60;
            }
        }
        uasort($project, fn($a, $b) => $a['pos'] <=> $b['pos']);

        // --- Interne Prozesse
        $process = $zero();
        foreach (DB::all(
            'SELECT substr(start_ts, 1, 10) AS d, SUM(duration_min) AS m FROM process_entries
              WHERE user_id = ? AND substr(start_ts, 1, 10) >= ? AND substr(start_ts, 1, 10) <= ? GROUP BY substr(start_ts, 1, 10)',
            [$userId, $from, $to]
        ) as $r) {
            $i = $weekIndex($r['d']);
            if ($i >= 0 && $i < $weeks) { $process[$i] += (float) $r['m'] / 60; }
        }

        // --- Feiertage, Urlaub, Krankheit, Ueberstundenabbau + Wochensoll
        $history = array_reverse(EmployeeRepo::history($userId));
        $termsAt = function (string $date) use ($history) {
            $found = null;
            foreach ($history as $t) { if ($t['valid_from'] <= $date) { $found = $t; } else { break; } }
            return $found;
        };
        $abs = AccountRepo::absencesByDay($userId, $from, $to, $history);
        $holiday = $zero(); $vacation = $zero(); $sick = $zero(); $reduction = $zero();
        $target = array_fill(0, $weeks, null);
        for ($i = 0; $i < $weeks; $i++) {
            $t = $termsAt($starts[$i]);
            if ($t !== null && $t['employment_type'] !== EmployeeRepo::TYPE_FREELANCER) {
                $target[$i] = (float) $t['weekly_hours'];
            }
        }
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $day = $d->format('Y-m-d');
            if (!Workdays::isWorkday($d)) { continue; }
            $t = $termsAt($day);
            if ($t === null || $t['employment_type'] === EmployeeRepo::TYPE_FREELANCER) { continue; }
            $i = $weekIndex($day);
            $daily = EmployeeRepo::dailyTarget($t);
            if (Holidays::on($t['holiday_region'] ?? null, $day) !== null) {
                $holiday[$i] += $daily;
                continue;
            }
            $a = $abs[$day] ?? [];
            if (isset($a['vacation'])) { $vacation[$i] += $daily; }
            elseif (isset($a['sick'])) { $sick[$i] += $daily; }
            elseif (isset($a['overtime'])) {
                $reduction[$i] += $a['overtime']['hours'] === null ? $daily : min((float) $a['overtime']['hours'], $daily);
            }
        }

        // --- Serien zusammensetzen
        $series = [];
        $ci = 0;
        foreach ($project as $label => $p) {
            $series[] = ['key' => 'project:' . $label, 'label' => $label, 'color' => self::PROJECT_COLORS[$ci++ % count(self::PROJECT_COLORS)], 'values' => $p['values']];
        }
        $series[] = ['key' => 'process',            'label' => t('cockpit.series_process',   'Interne Prozesse'), 'color' => '#f59e0b', 'values' => $process];
        $series[] = ['key' => 'vacation',           'label' => t('cockpit.series_vacation',  'Urlaub'),           'color' => '#10b981', 'values' => $vacation];
        $series[] = ['key' => 'sick',               'label' => t('cockpit.series_sick',      'Krankheit'),        'color' => '#ef4444', 'values' => $sick];
        $series[] = ['key' => 'overtime_reduction', 'label' => t('cockpit.series_overtime',  'Ueberstundenabbau'),'color' => '#8b5cf6', 'values' => $reduction];
        $series[] = ['key' => 'holiday',            'label' => t('cockpit.series_holiday',   'Feiertag'),         'color' => '#94a3b8', 'values' => $holiday];

        // leere Serien entfallen, Werte runden
        $series = array_values(array_filter($series, fn($s) => array_sum($s['values']) > 0));
        foreach ($series as &$s) { $s['values'] = array_map(fn($v) => round($v, 2), $s['values']); }
        unset($s);

        $u = DB::one('SELECT display_name, username FROM users WHERE id = ?', [$userId]);
        return [
            'user' => (string) (($u['display_name'] ?? '') ?: ($u['username'] ?? '')),
            'weeks' => $labels,
            'week_starts' => $starts,
            'target' => array_map(fn($v) => $v === null ? null : round($v, 2), $target),
            'series' => $series,
        ];
    }
}
