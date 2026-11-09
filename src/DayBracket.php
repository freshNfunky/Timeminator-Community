<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-08 (#99): Tagessoll-Klammer + Ueberstunden-Klammer.
 *
 * Felix' Kern-Regel (sinngemaess): "Die Klammer geht NICHT von Startzeit
 * der ersten Buchung bis Endzeit der letzten Buchung, sondern ist eine
 * Summenformel ueber gebuchte Minuten. Luecken zaehlen nicht. Sobald die
 * Summe das Tagessoll erreicht, startet an genau dieser Grenze die
 * gestrichelte Ueberstunden-Klammer - auch MITTEN in einer Buchung."
 *
 * Diese Klasse ist bewusst eine pure Logikklasse, damit der Tagessoll-
 * Vertrag ohne DB-Fixture testbar bleibt.
 */
final class DayBracket
{
    /**
     * Berechne Target- und Ueberstunden-Klammer aus Tagesevents.
     *
     * Felix-Feedback 2026-11-09 (Code-Review Fix #128): Entries und Prozesse
     * koennen sich ueberlappen (unterschiedliche Tabellen, kein Overlap-Guard
     * zwischen ihnen). Die Summenformel muss gebuchte MINUTEN zaehlen, nicht
     * Event-Dauern - sonst zaehlt ein Entry 08-10 + Prozess 09-11 als 4 h
     * statt 3 h und die Pensum-Pille kippt faelschlich auf rot. Darum: vor
     * der Summenbildung Intervalle mergen.
     *
     * @param list<array{start_min:int,end_min:int}> $events in beliebiger Reihenfolge
     * @param int $targetMin Tagessoll in Minuten
     * @return null|array{
     *   target:array{from:int,to:int},
     *   over:?array{from:int,to:int},
     *   sum:int
     * }
     */
    public static function compute(array $events, int $targetMin): ?array
    {
        if ($targetMin <= 0 || $events === []) {
            return null;
        }
        $events = self::mergeOverlaps($events);
        if ($events === []) {
            return null;
        }
        $cum = 0;
        $boundary = null;
        $firstStart = null;
        $lastEnd = null;
        foreach ($events as $ev) {
            $start = $ev['start_min'];
            $end   = $ev['end_min'];
            $firstStart ??= $start;
            $dur = $end - $start;
            if ($boundary === null && $cum + $dur >= $targetMin) {
                // Grenze liegt genau $overshoot Minuten vor dem Event-Ende.
                $overshoot = ($cum + $dur) - $targetMin;
                $boundary = $end - $overshoot;
            }
            $cum += $dur;
            $lastEnd = $end;
        }
        if ($firstStart === null || $lastEnd === null) {
            return null;
        }

        $targetEnd = $boundary ?? $lastEnd;
        $result = [
            'target' => ['from' => $firstStart, 'to' => $targetEnd],
            'over'   => null,
            'sum'    => $cum,
        ];
        if ($cum > $targetMin && $boundary !== null) {
            $result['over'] = ['from' => $boundary, 'to' => $lastEnd];
        }
        return $result;
    }

    /**
     * Vereinigt ueberlappende und direkt aneinander grenzende Intervalle zu
     * disjunkten, sortierten Stuecken. Ignoriert ungueltige Events (end<=start).
     *
     * @param list<array{start_min:int,end_min:int}> $events
     * @return list<array{start_min:int,end_min:int}>
     */
    private static function mergeOverlaps(array $events): array
    {
        $norm = [];
        foreach ($events as $ev) {
            $s = (int) ($ev['start_min'] ?? 0);
            $e = (int) ($ev['end_min']   ?? 0);
            if ($e > $s) { $norm[] = ['start_min' => $s, 'end_min' => $e]; }
        }
        if ($norm === []) { return []; }
        usort($norm, static fn($a, $b) => $a['start_min'] <=> $b['start_min']);
        $out = [];
        foreach ($norm as $ev) {
            $last = $out !== [] ? array_key_last($out) : null;
            if ($last !== null && $ev['start_min'] <= $out[$last]['end_min']) {
                $out[$last]['end_min'] = max($out[$last]['end_min'], $ev['end_min']);
            } else {
                $out[] = $ev;
            }
        }
        return $out;
    }
}
