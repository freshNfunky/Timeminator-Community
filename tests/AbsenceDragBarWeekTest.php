<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#146): Drag im Wochen-Header + Pillen-Band
 * fuer ganztaegige Abwesenheiten. Werktage-only, Wochenende/Feiertag
 * erzeugen Luecken.
 *
 * Dieser Test pinnt die View-, CSS- und JS-Vertraege.
 */
final class AbsenceDragBarWeekTest extends TestCase
{
    public function testWeekViewRendersPillsInsideDayheadCells(): void
    {
        // Felix-Feedback 2026-11-09 (Fix #155): Pillen sitzen jetzt
        // INNERHALB der jeweiligen Dayhead-Zelle (zwischen Wochentag-Label
        // und Pensum-Badge), nicht mehr als separate Band-Zone oberhalb.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        self::assertStringContainsString('cal-absbar-pill', $view,
            'Pillen-Klasse cal-absbar-pill fehlt');
        // Pillen werden nach from-YMD gruppiert und in die dayhead der
        // ersten Spalte ihres Spans gerendert.
        self::assertStringContainsString('$__absBandByYmd', $view,
            'Pillen muessen pro ymd gruppiert werden, damit sie in der jeweiligen Zelle gerendert werden');
        self::assertStringContainsString('WorkdaySpans::workdaySpans', $view,
            'Werktags-Zerlegung muss ueber WorkdaySpans laufen');
        // Alter Pro-Tag-Button ist raus - Pillen ersetzen ihn.
        self::assertStringNotContainsString('class="cal-day-absence"', $view,
            'Alte cal-day-absence-Buttons in der Wochenansicht duerfen nicht mehr gerendert werden');
        // Die separate Band-Row ist entfernt - Pillen sitzen in der Dayhead.
        self::assertStringNotContainsString('<div class="cal-week-absbar"', $view,
            'Separate cal-week-absbar-Row darf nicht mehr existieren - Pillen sitzen in dayhead');
    }

    public function testPillsStackAsGanttRowsWhenOverlapping(): void
    {
        // Felix-Feedback 2026-11-09 (#162): Mehrere ganztaegige Pillen am
        // gleichen Tag muessen sich wie Gantt-Rows UNTEREINANDER anordnen,
        // nicht ueberlappen. Pensum-Badge sitzt unter allen Pillen.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        // Row-Assignment im PHP ist greedy pro from-ymd sortiert.
        self::assertStringContainsString("'row'", $view,
            'Jede Pille braucht ein row-Feld fuer den Gantt-Slot');
        self::assertStringContainsString('$__rowOccupancy', $view,
            'Row-Occupancy-Logik (greedy slot picker) fehlt');
        // Top-Offset pro Row in der inline CSS (22 + row * 24).
        self::assertMatchesRegularExpression(
            '~22\s*\+\s*\(\(int\)\s*\$__p\[\'row\'\]\)\s*\*\s*24~',
            $view,
            'Top-Offset pro Row (22 + row*24) muss in der inline-CSS gesetzt werden'
        );
        // Pro ymd Max-Row-Anzahl - aggregiert zum wochenweiten maxWeekRows
        // fuer einheitliches Label-Margin (Fix #173).
        self::assertStringContainsString('$__absRowsByYmd', $view,
            'Max-Row-Anzahl pro ymd muss berechnet werden (als Input fuer wochenweites Max)');
    }

    public function testPensumBadgesAlignAcrossWeek(): void
    {
        // Felix-Feedback 2026-11-09 (Fix #173): Pensum-Badges sitzen pro
        // Woche auf GLEICHER Hoehe (bottom), egal wie viele Pillen ein
        // einzelner Tag hat. Dafuer muss das Label-Margin wochenweit
        // einheitlich via max rows ueber alle Tage gesetzt werden, nicht
        // mehr per Tag (data-day).
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        self::assertStringContainsString('$__maxWeekRows', $view,
            'Wochenweites Max rows muss aus $__absRowsByYmd aggregiert werden');
        // Einheitlicher Selector .cal-week-head .cal-week-dayhead-label
        // statt data-day-individuell.
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\s+\.cal-week-dayhead-label\{margin-bottom:~',
            $view,
            'Label-Margin muss wochenweit via .cal-week-head gesetzt werden (Fix #173)'
        );
        // Alte per-Tag-Regel darf nicht mehr auftauchen.
        self::assertStringNotContainsString(
            '.cal-week-dayhead[data-day="',
            $view,
            'Kein data-day-individuelles Label-Margin mehr (Fix #173)'
        );
    }

    public function testCssDefinesPillPositionAbsoluteInsideDayhead(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        // Fix #155: Pillen sind position:absolute innerhalb der dayhead,
        // Breite wird pro Pill via nonce-Style auf span*100% gesetzt.
        self::assertMatchesRegularExpression(
            '~\.cal-absbar-pill\s*\{[^}]*position:\s*absolute~s',
            $css,
            'Pills muessen position:absolute sein, damit sie ueber die Nachbar-Cells laufen koennen'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead\s*\{[^}]*position:\s*relative[^}]*overflow:\s*visible~s',
            $css,
            'Dayhead braucht position:relative + overflow:visible fuer die absolute Pille'
        );
        // Fix #173: der feste :has()-Selector wurde durch eine wochenweite
        // inline-Regel ersetzt (margin-bottom pro Woche, nicht pro Tag),
        // damit Pensum-Badges auf gleicher Hoehe sitzen. In app.css steht
        // nur noch ein Kommentar statt der alten festen 26px-Regel.
        self::assertStringNotContainsString(
            '.cal-week-dayhead:has(.cal-absbar-pill) .cal-week-dayhead-label{margin-bottom:26px}',
            $css,
            'Alte feste :has()-Regel ist obsolet (Fix #173) - wochenweite inline-Regel uebernimmt'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-absbar-pill\s*\{[^}]*top:\s*2\dpx~s',
            $css,
            'Pille muss top:~22px haben (direkt unter dem Label, oberhalb von Pensum)'
        );
        self::assertStringContainsString('.cal-absbar-pill-urlaub', $css);
        self::assertStringContainsString('.cal-absbar-pill-sonderurlaub', $css);
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\.is-dragging\s*\{[^}]*cursor:\s*crosshair~s',
            $css
        );
        self::assertStringContainsString('.cal-week-dayhead.is-drag-range', $css);
    }

    public function testJsExposesOpenForNewAbsenceRangeAndDragHandlers(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString('function openForNewAbsenceRange', $js,
            'openForNewAbsenceRange-Funktion fehlt');
        self::assertStringContainsString("head.addEventListener('mousedown'", $js,
            'Mousedown-Handler auf cal-week-head fehlt');
        self::assertStringContainsString("head.addEventListener('mousemove'", $js,
            'Mousemove-Handler auf cal-week-head fehlt');
        self::assertStringContainsString("head.addEventListener('mouseup'", $js,
            'Mouseup-Handler auf cal-week-head fehlt');
        self::assertStringContainsString('cal-absbar-pill', $js,
            'Click-Handler fuer Band-Pillen fehlt (oeffnet Edit-Dialog)');
    }

    public function testMigrationSeedsSonderurlaubCategoryWithNoAccount(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/schema/migrations/2026-11-09-01-sonderurlaub-category.php');
        self::assertStringContainsString("'sonderurlaub'", $sql,
            'Migration muss ckey "sonderurlaub" seeden');
        self::assertStringContainsString("'Sonderurlaub'", $sql,
            'Migration muss label "Sonderurlaub" seeden');
        // Konto NULL = kein Urlaubskonto-Effekt, nur statistisch.
        self::assertMatchesRegularExpression(
            '~INSERT\s+INTO\s+absence_categories[^)]*account[^)]*\)\s*VALUES\s*\([^)]*NULL~si',
            $sql,
            'Sonderurlaub muss account=NULL bekommen (kein Urlaubskonto-Effekt)'
        );
    }

    public function testDialogI18nIncludesGroupAbsencesLabel(): void
    {
        $idx = (string) file_get_contents(dirname(__DIR__) . '/views/entries/index.php');
        self::assertMatchesRegularExpression(
            "~'group_absences'\s*=>\s*t\('cal\.group_absences'~",
            $idx,
            'Dialog-i18n-Map muss group_absences-Key enthalten, damit JS die Default-Urlaubsoption findet'
        );
    }
}
