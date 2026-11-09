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
        // Fallback label resolves via `t()`, so pin the locale explicitly;
        // asserting both languages keeps the test deterministic regardless of
        // the host's Accept-Language or configured default.
        I18n::setSession('de'); I18n::reset();
        self::assertSame('(ohne Gleis)', Stats::entryTrack([
            'project_track' => '',
            'client_track'  => '',
        ]));
        I18n::setSession('en'); I18n::reset();
        self::assertSame('(no track)', Stats::entryTrack([
            'project_track' => '',
            'client_track'  => '',
        ]));
        // Clear the test's session-locale write before yielding to other
        // tests: without this, LoginThrottle messages later in the suite
        // come out English and break their German assertions.
        unset($_SESSION['_locale']);
        I18n::reset();
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

    public function testStatsDistributionGroupingLabelIsVisibleAndAutoReloads(): void
    {
        // Felix-Feedback 2026-11-06: der "Gruppieren nach"-Dropdown war
        // nicht offensichtlich wirksam ("es gruppiert nichts"). Root-Cause:
        // (1) das group-Suffix am Donut-Titel war .muted + klein, (2) der
        // Dropdown-Change loeste keinen Auto-Reload aus, Nutzer mussten
        // "Auswerten" klicken um die Wirkung zu sehen.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/stats/index.php');
        $css  = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        $js   = (string) file_get_contents(dirname(__DIR__) . '/assets/stats.js');

        // Group-Label ist prominent (eigene CSS-Klasse dist-group-lbl in
        // Brand-Farbe) und beim Server-Render vorbefuellt mit dem Default
        // ("nach Projekt"), damit es sofort sichtbar ist.
        self::assertMatchesRegularExpression(
            '/<span class="dist-group-lbl" id="distGroupLbl">.*?stats\.by_project/s',
            $view,
            'Default-Gruppierungs-Label muss serverseitig vorbefuellt + Klasse .dist-group-lbl sein'
        );
        self::assertMatchesRegularExpression(
            '/\.dist-group-lbl\s*\{[^}]*color\s*:\s*var\(--brand\)/',
            $css,
            '.dist-group-lbl muss brand-farbig sein (nicht muted/klein)'
        );
        self::assertStringNotContainsString(
            '<span class="muted" id="distGroupLbl">',
            $view,
            'Alter muted-Hinweis am Distributions-Titel darf nicht zurueckkommen'
        );

        // Auto-Reload bei Change des group-Dropdowns (sonst sieht Nutzer
        // keine Wirkung, bis er "Auswerten" klickt).
        self::assertMatchesRegularExpression(
            '/groupSel\s*=\s*form\.querySelector\(\'\[name=group\]\'\).*?groupSel\.addEventListener\(\'change\',\s*load\)/s',
            $js,
            'stats.js muss Change am group-Dropdown automatisch load() triggern'
        );
    }

    public function testStatsDefaultRangeIsLast90DaysInViewAndController(): void
    {
        // Felix-Feedback 2026-11-06: der alte Default "first day of this month"
        // zeigte am Monatsanfang nichts, wenn die aktuelle Monatshaelfte noch
        // keine Buchungen enthielt - Verteilungs-Chart erschien "kaputt".
        // Fix: beide Stellen (View + Controller) nutzen jetzt "letzte 90 Tage",
        // damit auch nach Buchungs-Pausen (bis zu drei Monate) der Chart
        // etwas zeigt. Die zwei Default-Quellen muessen synchron bleiben,
        // sonst weicht der Controller-Default vom View-Default ab und der
        // JSON-Endpoint liefert einen anderen Zeitraum als die Formular-Vorbefuellung.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/stats/index.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/stats_ctrl.php');

        self::assertMatchesRegularExpression(
            "/\\\$from\\s*=\\s*\\(new DateTimeImmutable\\('now'\\)\\)->modify\\('-90 days'\\)/",
            $view,
            'View-Default-Range muss "letzte 90 Tage" sein (nicht first day of this month)'
        );
        self::assertStringNotContainsString(
            "first day of this month",
            $view,
            'Alter Default "first day of this month" darf nicht wieder auftauchen - der Chart war am Monatsanfang leer'
        );
        self::assertMatchesRegularExpression(
            "/\\(new DateTimeImmutable\\('now'\\)\\)->modify\\('-90 days'\\)/",
            $ctrl,
            'Controller ctrl_stats_data muss denselben -90-Tage-Default liefern wie die View'
        );
        self::assertStringNotContainsString(
            "first day of this month",
            $ctrl,
            'Controller-Default darf nicht auf "first day of this month" zurueckfallen'
        );
    }
}
