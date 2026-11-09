<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (#146): "Wochenenden und Feiertage sind keine
 * Urlaubstage. Wenn ein durchgaengiger Urlaub von 3 Wochen gebucht wird,
 * Wochenenden/Feiertage dazwischen werden nicht mitgezaehlt - sollen auch
 * nicht in der ganztaegigen Pille auftauchen, gibt entsprechende Luecken."
 *
 * Diese pure Logik-Klasse zerlegt eine Datums-Range (Urlaubs-Range) in
 * ZUSAMMENHAENGENDE WERKTAGS-SPANS, mit denen die View pro Span eine
 * Pille rendert. Reine Werktage werden geliefert - Wochenende + Feiertag
 * fallen heraus und erzeugen natuerliche Luecken zwischen zwei Spans.
 */
final class WorkdaySpans
{
    /**
     * @param string $from inclusive YYYY-MM-DD
     * @param string $to   inclusive YYYY-MM-DD
     * @param list<int> $workingDows ISO-Dow-Liste der Werktage (z.B. [1..5])
     * @param array<string,bool> $holidays Feiertage im Bereich (YYYY-MM-DD => true)
     *
     * @return list<array{from:string,to:string,days:int}>
     *   Liste nicht-ueberlappender Werktags-Spans.
     *   - from/to inklusiv, YYYY-MM-DD
     *   - days   Anzahl Werktage im Span
     *
     * Edge-Cases:
     * - from > to: leeres Array
     * - Range ist komplett Wochenende+Feiertag: leeres Array
     * - Range ist zu gross (> 366 Tage): wird auf 366 begrenzt (Sicherheitsnetz)
     */
    public static function workdaySpans(string $from, string $to, array $workingDows, array $holidays): array
    {
        try {
            $start = new DateTimeImmutable($from);
            $end   = new DateTimeImmutable($to);
        } catch (Throwable) {
            return [];
        }
        if ($end < $start) { return []; }
        // Sicherheitsnetz: nicht mehr als 366 Tage pro Aufruf.
        $diffDays = (int) $start->diff($end)->days;
        if ($diffDays > 366) {
            $end = $start->modify('+366 days');
        }

        $spans = [];
        $curFrom = null;
        $curDays = 0;
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $ymd = $d->format('Y-m-d');
            $iso = (int) $d->format('N');
            $isWorkday = in_array($iso, $workingDows, true) && empty($holidays[$ymd]);
            if ($isWorkday) {
                if ($curFrom === null) { $curFrom = $ymd; $curDays = 0; }
                $curDays++;
                $curTo = $ymd;
            } else {
                if ($curFrom !== null) {
                    $spans[] = ['from' => $curFrom, 'to' => $curTo, 'days' => $curDays];
                    $curFrom = null;
                }
            }
        }
        if ($curFrom !== null) {
            $spans[] = ['from' => $curFrom, 'to' => $curTo, 'days' => $curDays];
        }
        return $spans;
    }
}
