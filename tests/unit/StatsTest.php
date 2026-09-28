<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stats::class)]
final class StatsTest extends TestCase
{
    #[DataProvider('workDayCases')]
    public function testWorkDayBoundary(string $input, int $hour, string $expected): void
    {
        $GLOBALS['APP_CONFIG']['day_boundary_hour'] = $hour;
        self::assertSame($expected, Stats::workDay($input));
    }

    public static function workDayCases(): array
    {
        return [
            'after boundary maps to same day' => ['2026-09-27 06:15:00', 4, '2026-09-27'],
            'before boundary maps to previous day' => ['2026-09-27 03:15:00', 4, '2026-09-26'],
            'at boundary hour belongs to same day' => ['2026-09-27 04:00:00', 4, '2026-09-27'],
            'boundary = 0 means calendar day' => ['2026-09-27 03:15:00', 0, '2026-09-27'],
            'boundary = 0 midnight rolls forward' => ['2026-09-27 00:00:00', 0, '2026-09-27'],
        ];
    }

    public function testEntryTrackPrefersProjectOverClient(): void
    {
        self::assertSame(
            'urgent',
            Stats::entryTrack(['project_track' => 'urgent', 'client_track' => 'default'])
        );
        self::assertSame(
            'default',
            Stats::entryTrack(['project_track' => '', 'client_track' => 'default'])
        );
        self::assertSame(
            '(ohne Gleis)',
            Stats::entryTrack(['project_track' => '', 'client_track' => ''])
        );
    }

    public function testApplyScopeFiltersIncludeExclude(): void
    {
        $entries = [
            ['project_id' => 1, 'client_id' => 10, 'project_track' => '', 'client_track' => '', 'start_ts' => '2026-09-27 09:00:00'],
            ['project_id' => 2, 'client_id' => 10, 'project_track' => '', 'client_track' => '', 'start_ts' => '2026-09-27 09:30:00'],
            ['project_id' => 3, 'client_id' => 20, 'project_track' => '', 'client_track' => '', 'start_ts' => '2026-09-27 10:00:00'],
        ];
        $out = Stats::applyScope($entries, ['include_project_ids' => [1, 3]]);
        self::assertCount(2, $out);
        self::assertSame([1, 3], array_column($out, 'project_id'));

        $out = Stats::applyScope($entries, ['exclude_client_ids' => [10]]);
        self::assertCount(1, $out);
        self::assertSame(3, $out[0]['project_id']);
    }

    public function testApplyScopeCutoverReassignsClient(): void
    {
        $entries = [
            ['project_id' => 1, 'client_id' => 10, 'client_name' => 'Old', 'project_track' => '', 'client_track' => '', 'start_ts' => '2026-09-26 08:00:00'],
            ['project_id' => 1, 'client_id' => 10, 'client_name' => 'Old', 'project_track' => '', 'client_track' => '', 'start_ts' => '2026-09-27 08:00:00'],
        ];
        $out = Stats::applyScope($entries, [
            'cutover' => [
                ['date' => '2026-09-27', 'project_id' => 1, 'to_client_id' => 99, 'to_client_name' => 'New'],
            ],
        ]);
        self::assertSame(10, $out[0]['client_id']);
        self::assertSame(99, $out[1]['client_id']);
        self::assertSame('New', $out[1]['client_name']);
    }

    public function testApplyScopeTrackFilter(): void
    {
        $entries = [
            ['project_id' => 1, 'client_id' => 10, 'project_track' => 'a', 'client_track' => '', 'start_ts' => '2026-09-27 08:00:00'],
            ['project_id' => 2, 'client_id' => 10, 'project_track' => 'b', 'client_track' => '', 'start_ts' => '2026-09-27 08:00:00'],
        ];
        $out = Stats::applyScope($entries, ['tracks' => ['a']]);
        self::assertCount(1, $out);
        self::assertSame(1, $out[0]['project_id']);
    }
}
