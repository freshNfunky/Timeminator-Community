<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-08 (#99): Tagessoll-Klammer-Logik.
 *
 * Die wichtigsten Felix-Aussagen in Code gegossen:
 *
 *   1) "Klammer geht NICHT von Startzeit der ersten Buchung bis Endzeit der
 *      letzten Buchung, sondern ist eine Summenformel."
 *   2) "Luecken dazwischen addieren sich NICHT automatisch zu den 8h hinzu."
 *   3) "Ab der Minute, an der das Tagessoll erreicht ist, beginnt die
 *      gestrichelte Ueberstunden-Klammer - kann mitten in einer Buchung sein."
 *   4) "Die Event-Rendering-Blocks selbst werden NICHT gesplittet."
 *      (= DayBracket liefert nur Grenzen, nicht Event-Splits.)
 */
final class DayBracketTest extends TestCase
{
    public function testContiguousDayWithExactTargetReturnsSingleTargetBracket(): void
    {
        $events = [
            ['start_min' => 8 * 60,  'end_min' => 12 * 60],  // 4h
            ['start_min' => 13 * 60, 'end_min' => 17 * 60],  // 4h
        ];
        $r = DayBracket::compute($events, 8 * 60);
        self::assertNotNull($r);
        self::assertSame(8 * 60, $r['target']['from']);
        self::assertSame(17 * 60, $r['target']['to'], 'Bei genau 8h Summe endet die Target-Klammer am Ende der letzten Buchung');
        self::assertNull($r['over']);
        self::assertSame(8 * 60, $r['sum']);
    }

    public function testGapsAreNotCountedTowardsTheTarget(): void
    {
        // Buchungen 8-10, 11-13, 14-17 -> Summe = 7h, aber Zeit-Spanne 8-17 waere 9h.
        // Felix: Luecken zaehlen NICHT, also noch keine Overtime.
        $events = [
            ['start_min' => 8 * 60,  'end_min' => 10 * 60],  // 2h
            ['start_min' => 11 * 60, 'end_min' => 13 * 60],  // 2h
            ['start_min' => 14 * 60, 'end_min' => 17 * 60],  // 3h
        ];
        $r = DayBracket::compute($events, 8 * 60);
        self::assertNotNull($r);
        self::assertSame(8 * 60, $r['target']['from']);
        self::assertSame(17 * 60, $r['target']['to'], 'Target-Klammer umschliesst 8-17, aber summiert nur gebuchte Minuten');
        self::assertNull($r['over'], 'Summe 7h < Target 8h -> keine Overtime-Klammer');
        self::assertSame(7 * 60, $r['sum']);
    }

    public function testBoundaryFallsInsideAnEventWhenTargetIsOverrun(): void
    {
        // 8-12 (4h) + 13-18 (5h) = 9h; Target 8h.
        // Nach der ersten Buchung Summe 4h. Zweite Buchung (13-18) addiert 5h ->
        // Summe wuerde 9h, Target = 8h, Overshoot = 1h. Grenze = 18:00 - 1h = 17:00.
        $events = [
            ['start_min' => 8 * 60,  'end_min' => 12 * 60],
            ['start_min' => 13 * 60, 'end_min' => 18 * 60],
        ];
        $r = DayBracket::compute($events, 8 * 60);
        self::assertNotNull($r);
        self::assertSame(8 * 60,  $r['target']['from']);
        self::assertSame(17 * 60, $r['target']['to'], 'Target-Klammer endet mitten in der zweiten Buchung bei 17:00');
        self::assertNotNull($r['over']);
        self::assertSame(17 * 60, $r['over']['from']);
        self::assertSame(18 * 60, $r['over']['to']);
        self::assertSame(9 * 60,  $r['sum']);
    }

    public function testEmptyEventsReturnsNull(): void
    {
        self::assertNull(DayBracket::compute([], 8 * 60));
    }

    public function testZeroOrNegativeTargetReturnsNull(): void
    {
        $events = [['start_min' => 8 * 60, 'end_min' => 9 * 60]];
        self::assertNull(DayBracket::compute($events, 0));
        self::assertNull(DayBracket::compute($events, -10));
    }

    public function testInvertedEventIsIgnored(): void
    {
        // Defensive: Event mit end <= start wird nicht berueckstichtigt.
        $events = [
            ['start_min' => 8 * 60, 'end_min' => 8 * 60],   // zero-length
            ['start_min' => 9 * 60, 'end_min' => 17 * 60],  // 8h
        ];
        $r = DayBracket::compute($events, 8 * 60);
        self::assertNotNull($r);
        self::assertSame(9 * 60, $r['target']['from']);
        self::assertSame(17 * 60, $r['target']['to']);
        self::assertSame(8 * 60, $r['sum']);
    }

    public function testFullDaySingleEventOvershoot(): void
    {
        // Eine einzige lange Buchung 9-19 (10h) + Target 8h -> Grenze = 19:00 - 2h = 17:00.
        $events = [['start_min' => 9 * 60, 'end_min' => 19 * 60]];
        $r = DayBracket::compute($events, 8 * 60);
        self::assertNotNull($r);
        self::assertSame(9 * 60,  $r['target']['from']);
        self::assertSame(17 * 60, $r['target']['to']);
        self::assertNotNull($r['over']);
        self::assertSame(17 * 60, $r['over']['from']);
        self::assertSame(19 * 60, $r['over']['to']);
        self::assertSame(10 * 60, $r['sum']);
    }
}
