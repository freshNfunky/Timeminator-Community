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
    public function testWeekViewRendersAbsBandBeforeHeadRow(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        self::assertStringContainsString('cal-week-absbar', $view,
            'Band-Zone cal-week-absbar fehlt im Markup');
        self::assertStringContainsString('cal-absbar-pill', $view,
            'Pillen-Klasse cal-absbar-pill fehlt');
        // Pillen-Build iteriert ueber $__absBand (pre-computed Werktags-Spans).
        self::assertStringContainsString('foreach ($__absBand', $view,
            'Pillen muessen aus $__absBand gebaut werden');
        // WorkdaySpans wird benutzt, um die Werktags-Spans zu erzeugen.
        self::assertStringContainsString('WorkdaySpans::workdaySpans', $view,
            'Werktags-Zerlegung muss ueber WorkdaySpans::workdaySpans laufen');
        // Alter Pro-Tag-Button ist raus - Band ersetzt ihn.
        self::assertStringNotContainsString('class="cal-day-absence"', $view,
            'Alte cal-day-absence-Buttons in der Wochenansicht duerfen nicht mehr gerendert werden - Band ersetzt sie');
    }

    public function testCssDefinesAbsBarGridAndPillVariants(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~\.cal-week-absbar\s*\{[^}]*grid-template-columns:\s*52px\s+repeat\(7~s',
            $css,
            'Absbar muss 52px + 7 Spalten im Grid haben (synchron zu .cal-week-head)'
        );
        self::assertStringContainsString('.cal-absbar-pill-urlaub', $css,
            'Urlaubs-Pill-Variante fehlt');
        self::assertStringContainsString('.cal-absbar-pill-sonderurlaub', $css,
            'Sonderurlaubs-Pill-Variante fehlt');
        // Drag-Hover: cursor:crosshair + is-drag-range.
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\.is-dragging\s*\{[^}]*cursor:\s*crosshair~s',
            $css,
            'Drag-Zustand muss cursor:crosshair haben'
        );
        self::assertStringContainsString('.cal-week-dayhead.is-drag-range', $css,
            'Drag-Range-Highlight-Klasse fehlt');
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
