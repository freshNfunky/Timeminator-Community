<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#146): Werktage-only-Logik fuer ganztaegige
 * Abwesenheiten. Range Mo-So darf die Wochenend-Lage NICHT verwischen -
 * Wochenende und Feiertage werden uebersprungen und erzeugen Luecken.
 */
final class WorkdaySpansTest extends TestCase
{
    private const WORKING_MON_FRI = [1, 2, 3, 4, 5];

    public function testThreeWeekRangeSkipsTwoWeekendsAndOneHoliday(): void
    {
        // 2026-07-13 (Mo) bis 2026-07-31 (Fr) - 3 Wochen.
        // Wochenenden: 18./19.07. und 25./26.07.
        // Feiertag frei erfunden: 22.07.2026 (Mi).
        $spans = WorkdaySpans::workdaySpans(
            '2026-07-13', '2026-07-31',
            self::WORKING_MON_FRI,
            ['2026-07-22' => true]
        );
        // Erwartete Spans:
        //  Mo 13 - Fr 17 (5)   (Wochenende)
        //  Mo 20 - Di 21 (2)   (Feiertag 22)  (Do 23 - Fr 24 ebenfalls Werktage)
        // -> Aufteilung: Spans sind zusammenhaengende Werktagsblocks.
        //    13-17 (5), 20-21 (2), 23-24 (2), 27-31 (5) = 14 Werktage.
        self::assertCount(4, $spans);
        self::assertSame(['from'=>'2026-07-13','to'=>'2026-07-17','days'=>5], $spans[0]);
        self::assertSame(['from'=>'2026-07-20','to'=>'2026-07-21','days'=>2], $spans[1]);
        self::assertSame(['from'=>'2026-07-23','to'=>'2026-07-24','days'=>2], $spans[2]);
        self::assertSame(['from'=>'2026-07-27','to'=>'2026-07-31','days'=>5], $spans[3]);
        $totalDays = array_sum(array_column($spans, 'days'));
        self::assertSame(14, $totalDays,
            'Total Werktage muss 14 sein (3-Wochen-Range minus 4 Wochenend-Tage minus 1 Feiertag)');
    }

    public function testSingleDayWorkdayReturnsOneSpan(): void
    {
        $spans = WorkdaySpans::workdaySpans('2026-07-13', '2026-07-13', self::WORKING_MON_FRI, []);
        self::assertSame([['from'=>'2026-07-13','to'=>'2026-07-13','days'=>1]], $spans);
    }

    public function testSingleSaturdayReturnsEmpty(): void
    {
        $spans = WorkdaySpans::workdaySpans('2026-07-18', '2026-07-18', self::WORKING_MON_FRI, []);
        self::assertSame([], $spans);
    }

    public function testFullyHolidayRangeReturnsEmpty(): void
    {
        $spans = WorkdaySpans::workdaySpans(
            '2026-12-25', '2026-12-28',
            self::WORKING_MON_FRI,
            ['2026-12-25' => true, '2026-12-28' => true]
        );
        // 25 (Fr, Holiday) + 26 (Sa) + 27 (So) + 28 (Mo, Holiday) = alles frei.
        self::assertSame([], $spans);
    }

    public function testInvertedRangeReturnsEmpty(): void
    {
        self::assertSame([], WorkdaySpans::workdaySpans('2026-07-20', '2026-07-13', self::WORKING_MON_FRI, []));
    }

    public function testCustomWorkdayPreset(): void
    {
        // Admin konfiguriert Sa-Mi (So + Do + Fr = frei).
        $workingSaWed = [6, 7, 1, 2, 3]; // Sa, So, Mo, Di, Mi
        $spans = WorkdaySpans::workdaySpans('2026-07-13', '2026-07-19', $workingSaWed, []);
        // Mo 13 - Mi 15 (3), Do+Fr frei, Sa 18 - So 19 (2).
        self::assertCount(2, $spans);
        self::assertSame(['from'=>'2026-07-13','to'=>'2026-07-15','days'=>3], $spans[0]);
        self::assertSame(['from'=>'2026-07-18','to'=>'2026-07-19','days'=>2], $spans[1]);
    }
}
