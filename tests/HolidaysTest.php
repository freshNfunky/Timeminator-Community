<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static Holiday Overlay (#110).
 *
 * Pins known-good dates for a handful of years so that an off-by-one in
 * the easter or nth-weekday math fails loudly rather than drifting
 * silently through release after release.
 */
final class HolidaysTest extends TestCase
{
    public function testEasterRelativeDacHDatesHoldFor2024(): void
    {
        $byKey = self::byKey(Holidays::forYear(2024, 'de'));
        self::assertSame('2024-03-29', $byKey['holiday.de.good_friday']['date'],  'Good Friday 2024');
        self::assertSame('2024-04-01', $byKey['holiday.de.easter_monday']['date'],'Easter Monday 2024');
        self::assertSame('2024-05-09', $byKey['holiday.de.ascension']['date'],   'Ascension 2024');
        self::assertSame('2024-05-20', $byKey['holiday.de.whit_monday']['date'], 'Whit Monday 2024');
        self::assertSame('2024-05-30', $byKey['holiday.de.corpus_christi']['date'], 'Corpus Christi 2024');
    }

    public function testEasterRelativeDacHDatesHoldFor2025(): void
    {
        $byKey = self::byKey(Holidays::forYear(2025, 'de'));
        self::assertSame('2025-04-18', $byKey['holiday.de.good_friday']['date']);
        self::assertSame('2025-04-21', $byKey['holiday.de.easter_monday']['date']);
        self::assertSame('2025-05-29', $byKey['holiday.de.ascension']['date']);
    }

    public function testEasterRelativeDacHDatesHoldFor2026(): void
    {
        $byKey = self::byKey(Holidays::forYear(2026, 'de'));
        self::assertSame('2026-04-03', $byKey['holiday.de.good_friday']['date']);
        self::assertSame('2026-04-06', $byKey['holiday.de.easter_monday']['date']);
    }

    public function testDachHasExpectedCountPerYear(): void
    {
        self::assertCount(16, Holidays::forYear(2026, 'de'), 'DACH union is 16 entries');
    }

    public function testUsFederalHasElevenEntries(): void
    {
        $us = Holidays::forYear(2026, 'en');
        self::assertCount(11, $us, 'US federal set is 11 entries');
        $byKey = self::byKey($us);
        // Nth-weekday pins — a wrong index would flip these by a week.
        self::assertSame('2026-01-19', $byKey['holiday.us.mlk_day']['date'],       '3rd Mon of Jan 2026');
        self::assertSame('2026-02-16', $byKey['holiday.us.presidents_day']['date'],'3rd Mon of Feb 2026');
        self::assertSame('2026-05-25', $byKey['holiday.us.memorial_day']['date'],  'last Mon of May 2026');
        self::assertSame('2026-09-07', $byKey['holiday.us.labor_day']['date'],     '1st Mon of Sep 2026');
        self::assertSame('2026-11-26', $byKey['holiday.us.thanksgiving']['date'],  '4th Thu of Nov 2026');
    }

    public function testUsFixedDatesLandOnTheirCalendarDay(): void
    {
        $byKey = self::byKey(Holidays::forYear(2026, 'en'));
        self::assertSame('2026-01-01', $byKey['holiday.us.new_year']['date']);
        self::assertSame('2026-06-19', $byKey['holiday.us.juneteenth']['date']);
        self::assertSame('2026-07-04', $byKey['holiday.us.independence']['date']);
        self::assertSame('2026-12-25', $byKey['holiday.us.christmas']['date']);
    }

    public function testUnknownLocaleFallsBackToDach(): void
    {
        $de = Holidays::forYear(2026, 'de');
        $xx = Holidays::forYear(2026, 'xx');
        self::assertSame(array_column($de, 'date'), array_column($xx, 'date'));
    }

    public function testByDateReturnsRangeKeyedByDate(): void
    {
        $map = Holidays::byDate('2026-12-20', '2026-12-31', 'de');
        self::assertArrayHasKey('2026-12-25', $map);
        self::assertArrayHasKey('2026-12-26', $map);
        self::assertArrayNotHasKey('2026-12-01', $map, 'outside range');
    }

    public function testBetweenCrossesYearBoundary(): void
    {
        $range = Holidays::between('2025-12-24', '2026-01-02', 'de');
        $dates = array_column($range, 'date');
        self::assertContains('2025-12-25', $dates);
        self::assertContains('2025-12-26', $dates);
        self::assertContains('2026-01-01', $dates);
    }

    /**
     * @param array<int,array{date:string,key:string,scope:string}> $rows
     * @return array<string,array{date:string,key:string,scope:string}>
     */
    private static function byKey(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) { $out[$r['key']] = $r; }
        return $out;
    }
}
