<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Stats is the reporting layer's math surface. These tests pin the invariants
 * we actually care about: the work-day boundary, track fallback, aggregate
 * sums, and the day/week/month series.
 */
final class StatsTest extends TestCase
{
    public function testWorkDayShiftsBeforeBoundaryToPreviousDay(): void
    {
        // With day_boundary_hour = 4, anything before 04:00 belongs to the
        // previous work-day.
        $GLOBALS['APP_CONFIG']['day_boundary_hour'] = 4;
        self::assertSame('2026-09-27', Stats::workDay('2026-09-28 03:59:00'));
        self::assertSame('2026-09-28', Stats::workDay('2026-09-28 04:00:00'));
        self::assertSame('2026-09-28', Stats::workDay('2026-09-28 12:00:00'));
    }

    public function testWorkDayIsCalendarDayWhenBoundaryIsZero(): void
    {
        $GLOBALS['APP_CONFIG']['day_boundary_hour'] = 0;
        self::assertSame('2026-09-28', Stats::workDay('2026-09-28 00:01:00'));
        self::assertSame('2026-09-28', Stats::workDay('2026-09-28 23:59:00'));
    }

    public function testEntryTrackFallsBackFromProjectToClientToLabel(): void
    {
        self::assertSame('acme', Stats::entryTrack([
            'project_track' => 'acme',
            'client_track'  => 'ignored',
        ]));
        self::assertSame('acme-client', Stats::entryTrack([
            'project_track' => '',
            'client_track'  => 'acme-client',
        ]));
        self::assertSame('(ohne Gleis)', Stats::entryTrack([
            'project_track' => '',
            'client_track'  => '',
        ]));
        self::assertSame('acme', Stats::entryTrack([
            'project_track' => '  acme  ',   // gets trimmed
        ]));
    }

    public function testSumByGroupsAndTotalsAndSortsDescending(): void
    {
        $entries = [
            ['duration_min' => 30, 'k' => 'a'],
            ['duration_min' => 15, 'k' => 'a'],
            ['duration_min' => 90, 'k' => 'b'],
        ];
        $out = Stats::sumBy($entries, fn($e) => $e['k']);
        // sumBy applies arsort so the biggest bucket comes first.
        self::assertSame(['b' => 90, 'a' => 45], $out);
        self::assertSame(135, Stats::totalMinutes($entries));
    }

    public function testSeriesGroupsByDayGranularity(): void
    {
        $GLOBALS['APP_CONFIG']['day_boundary_hour'] = 4;
        $entries = [
            ['start_ts' => '2026-09-27 10:00:00', 'duration_min' => 60],
            ['start_ts' => '2026-09-27 14:00:00', 'duration_min' => 30],
            ['start_ts' => '2026-09-28 09:00:00', 'duration_min' => 45],
        ];
        $out = Stats::series($entries, 'day');
        self::assertSame(['2026-09-27', '2026-09-28'], $out['labels']);
        self::assertSame([90, 45], $out['minutes']);
    }

    public function testSeriesRespectsWorkDayBoundary(): void
    {
        // 03:59 on 09-28 belongs to work-day 09-27; 04:00 to 09-28.
        $GLOBALS['APP_CONFIG']['day_boundary_hour'] = 4;
        $entries = [
            ['start_ts' => '2026-09-28 03:59:00', 'duration_min' => 15],
            ['start_ts' => '2026-09-28 04:00:00', 'duration_min' => 25],
        ];
        $out = Stats::series($entries, 'day');
        self::assertSame(['2026-09-27', '2026-09-28'], $out['labels']);
        self::assertSame([15, 25], $out['minutes']);
    }
}
