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
     * @param list<array{start_min:int,end_min:int}> $events sortiert nach start_min
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
        $cum = 0;
        $boundary = null;
        $firstStart = null;
        $lastEnd = null;
        foreach ($events as $ev) {
            $start = (int) ($ev['start_min'] ?? 0);
            $end   = (int) ($ev['end_min'] ?? 0);
            if ($end <= $start) {
                continue;
            }
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
}
