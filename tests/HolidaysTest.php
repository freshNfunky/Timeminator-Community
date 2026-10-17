<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Holiday catalog + region matching (v1.0 refit of #110).
 *
 * Pins known-good dates for a handful of years so that an off-by-one in
 * the easter or nth-weekday math fails loudly, and locks the region /
 * sentinel semantics so a careless refactor can't quietly change which
 * days a user picks up.
 */
final class HolidaysTest extends TestCase
{
    public function testEasterRelativeDeDatesHoldFor2024(): void
    {
        $byKey = self::byKey(Holidays::forRegion('DE-ALL', 2024));
        self::assertSame('2024-03-29', $byKey['holiday.de.good_friday']['date'],  'Good Friday 2024');
        self::assertSame('2024-04-01', $byKey['holiday.de.easter_monday']['date'],'Easter Monday 2024');
        self::assertSame('2024-05-09', $byKey['holiday.de.ascension']['date'],   'Ascension 2024');
        self::assertSame('2024-05-20', $byKey['holiday.de.whit_monday']['date'], 'Whit Monday 2024');
    }

    public function testEasterRelativeDeDatesHoldFor2025(): void
    {
        $byKey = self::byKey(Holidays::forRegion('DE-ALL', 2025));
        self::assertSame('2025-04-18', $byKey['holiday.de.good_friday']['date']);
        self::assertSame('2025-04-21', $byKey['holiday.de.easter_monday']['date']);
        self::assertSame('2025-05-29', $byKey['holiday.de.ascension']['date']);
    }

    public function testEasterRelativeDeDatesHoldFor2026(): void
    {
        $byKey = self::byKey(Holidays::forRegion('DE-ALL', 2026));
        self::assertSame('2026-04-03', $byKey['holiday.de.good_friday']['date']);
        self::assertSame('2026-04-06', $byKey['holiday.de.easter_monday']['date']);
    }

    public function testDeAllCoversOnlyNationallyMandatedDays(): void
    {
        $days = Holidays::forRegion('DE-ALL', 2026);
        $keys = array_column($days, 'key');
        // The 9 federally-statutory days ("gesetzlich bundesweit").
        self::assertContains('holiday.de.new_year',       $keys);
        self::assertContains('holiday.de.good_friday',    $keys);
        self::assertContains('holiday.de.easter_monday',  $keys);
        self::assertContains('holiday.de.labour_day',     $keys);
        self::assertContains('holiday.de.ascension',      $keys);
        self::assertContains('holiday.de.whit_monday',    $keys);
        self::assertContains('holiday.de.german_unity',   $keys);
        self::assertContains('holiday.de.christmas_day',  $keys);
        self::assertContains('holiday.de.st_stephen',     $keys);
        // Regional-only days must NOT bleed into the nationwide feed.
        self::assertNotContains('holiday.de.all_saints',    $keys, 'Allerheiligen is BY/BW/NW/RP/SL only');
        self::assertNotContains('holiday.de.corpus_christi',$keys, 'Fronleichnam is regional');
        self::assertNotContains('holiday.de.reformation',   $keys, 'Reformationstag is regional');
    }

    public function testBavariaIncludesBoth_NationwideAndRegionalDays(): void
    {
        $keys = array_column(Holidays::forRegion('DE-BY', 2026), 'key');
        // Nationwide days are included via the DE-ALL sentinel.
        self::assertContains('holiday.de.german_unity', $keys);
        self::assertContains('holiday.de.christmas_day', $keys);
        // Bavarian regional days.
        self::assertContains('holiday.de.epiphany',     $keys);
        self::assertContains('holiday.de.corpus_christi', $keys);
        self::assertContains('holiday.de.all_saints',   $keys);
        self::assertContains('holiday.de.assumption',   $keys, 'Mariae Himmelfahrt is BY + SL');
    }

    public function testBerlinExcludesSouthernCatholicDays(): void
    {
        $keys = array_column(Holidays::forRegion('DE-BE', 2026), 'key');
        self::assertContains('holiday.de.womens_day',    $keys, 'Berlin + MV: Frauentag');
        self::assertNotContains('holiday.de.corpus_christi', $keys);
        self::assertNotContains('holiday.de.all_saints',     $keys);
        self::assertNotContains('holiday.de.epiphany',       $keys);
    }

    public function testUsFederalHasElevenEntries(): void
    {
        $us = Holidays::forRegion('US-FED', 2026);
        self::assertCount(11, $us, 'US federal set is 11 entries');
        $byKey = self::byKey($us);
        self::assertSame('2026-01-19', $byKey['holiday.us.mlk_day']['date'],       '3rd Mon of Jan 2026');
        self::assertSame('2026-02-16', $byKey['holiday.us.presidents_day']['date'],'3rd Mon of Feb 2026');
        self::assertSame('2026-05-25', $byKey['holiday.us.memorial_day']['date'],  'last Mon of May 2026');
        self::assertSame('2026-09-07', $byKey['holiday.us.labor_day']['date'],     '1st Mon of Sep 2026');
        self::assertSame('2026-11-26', $byKey['holiday.us.thanksgiving']['date'],  '4th Thu of Nov 2026');
    }

    public function testUsFixedDatesLandOnTheirCalendarDay(): void
    {
        $byKey = self::byKey(Holidays::forRegion('US-FED', 2026));
        self::assertSame('2026-01-01', $byKey['holiday.us.new_year']['date']);
        self::assertSame('2026-06-19', $byKey['holiday.us.juneteenth']['date']);
        self::assertSame('2026-07-04', $byKey['holiday.us.independence']['date']);
        self::assertSame('2026-12-25', $byKey['holiday.us.christmas']['date']);
    }

    public function testAustriaReturnsThirteenEntries(): void
    {
        self::assertCount(13, Holidays::forRegion('AT-ALL', 2026), 'AT fixed set is 13 entries');
    }

    public function testUnknownRegionReturnsEmpty(): void
    {
        self::assertSame([], Holidays::forRegion('XX-ZZ', 2026));
        self::assertSame([], Holidays::forRegion('__none__', 2026));
    }

    public function testByDateReturnsRangeKeyedByDate(): void
    {
        $map = Holidays::byDate('2026-12-20', '2026-12-31', 'DE-ALL');
        self::assertArrayHasKey('2026-12-25', $map);
        self::assertArrayHasKey('2026-12-26', $map);
        self::assertArrayNotHasKey('2026-12-01', $map, 'outside range');
    }

    public function testBetweenCrossesYearBoundary(): void
    {
        $range = Holidays::between('2025-12-24', '2026-01-02', 'DE-ALL');
        $dates = array_column($range, 'date');
        self::assertContains('2025-12-25', $dates);
        self::assertContains('2025-12-26', $dates);
        self::assertContains('2026-01-01', $dates);
    }

    public function testCatalogExposesAllAdvertisedCodes(): void
    {
        $codes = Holidays::regionCodes();
        foreach (['__none__', 'DE-ALL', 'DE-BY', 'DE-BE', 'AT-ALL', 'CH-ALL', 'CH-ZH', 'US-FED'] as $code) {
            self::assertContains($code, $codes);
            if ($code !== '__none__') {
                self::assertTrue(Holidays::isValidRegion($code), "$code should be valid");
            }
        }
        self::assertFalse(Holidays::isValidRegion('DE-XX'));
    }

    /**
     * @param array<int,array{date:string,key:string,regions:array<int,string>}> $rows
     * @return array<string,array{date:string,key:string,regions:array<int,string>}>
     */
    private static function byKey(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) { $out[$r['key']] = $r; }
        return $out;
    }
}
