<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionslock fuer die sticky Wochentag-Kopfzeile im Wochen-Kalender.
 *
 * Felix-Feedback 2026-11-08: "ich sehe gerade, dass der Tag, der Wochentag
 * und das Datum nicht immer oben stehen bleibt wenn man die Uhrzeit nach
 * unten scrollt, sondern mit wegscrollt was bloed ist. ... deswegen wuerde
 * ich die Kopfzeile ueber den Uhrzeiten in eine stetige Zeile bewegen."
 *
 * Root-Cause der historischen Regression: `.cal-days` hatte unter 820 px
 * `overflow-x: auto`, wurde damit zum Scroll-Container und `position:sticky`
 * auf dem darin liegenden `.cal-day-head` verlor seinen Bezug zu `.cal-week`.
 * Deshalb pinnen diese Tests auch den overflow-Vertrag (`.cal-week` ist der
 * EINE Scroll-Container), nicht nur das sticky-Styling selbst.
 */
final class CalendarWeekStickyHeaderTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testDayHeadDeclaresStickyAtTopZero(): void
    {
        self::assertMatchesRegularExpression(
            '~\.cal-day-head\s*\{[^}]*position:\s*sticky[^}]*top:\s*0~s',
            $this->css(),
            '.cal-day-head muss position:sticky + top:0 setzen, sonst wandert die Wochentag-Zeile mit beim Scrollen'
        );
    }

    public function testDayHeadDeclaresBackgroundSoContentDoesNotShineThrough(): void
    {
        self::assertMatchesRegularExpression(
            '~\.cal-day-head\s*\{[^}]*background:[^;]+~s',
            $this->css(),
            '.cal-day-head braucht einen Hintergrund, damit Buchungen beim Scrollen nicht durchscheinen'
        );
    }

    public function testWeekContainerIsTheSoleScrollContainer(): void
    {
        // Horizontaler Overflow gehoert jetzt auf .cal-week, nicht auf .cal-days.
        // Wer das rueckgaengig macht, bricht sticky auf .cal-day-head.
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.cal-week\s*\{[^}]*overflow:\s*auto~s',
            $css,
            '.cal-week muss overflow:auto haben (vertikale + horizontale Scroll-Achse im selben Container)'
        );
        self::assertDoesNotMatchRegularExpression(
            '~\.cal-days\s*\{[^}]*overflow-x:\s*auto~s',
            $css,
            '.cal-days darf KEIN overflow-x:auto haben - das wuerde die sticky-Day-Head-Zeile brechen'
        );
    }

    public function testHoursColumnStickyLeftKeepsLabelsVisibleWhenHorizontalScroll(): void
    {
        // Felix-Feedback 2026-11-08 schliesst mit "lassen wir die Mood oben
        // drueber stehen"; die Uhrzeit-Spalte wiederum klebt links, damit sie
        // beim horizontalen Scroll (schmale Viewports) nicht wegwandert.
        self::assertMatchesRegularExpression(
            '~\.cal-week\s+\.cal-hours\s*\{[^}]*position:\s*sticky[^}]*left:\s*0~s',
            $this->css(),
            '.cal-hours muss sticky left:0 sein, damit die Uhrzeiten-Spalte beim horizontalen Scroll stehen bleibt'
        );
    }

    public function testDayHeadStillSitsInsideEveryCalDay(): void
    {
        // Felix-Feedback 2026-11-08: "ansonsten steht dann die Mood auch in
        // der Kopfzeile vom Wochentag". Das Markup muss die Day-Head pro Tag
        // beibehalten (inklusive Mood-Picker direkt darunter im Day-Mode).
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        self::assertStringContainsString('class="cal-day-head"', $view,
            '.cal-day-head muss pro Tag im Markup vorhanden sein');
        self::assertMatchesRegularExpression(
            '~cal-day-head.*?cal-sent-day~s',
            $view,
            'Daily-Mood-Picker muss DIREKT unter der Day-Head sitzen, damit der sticky Head die Reihenfolge nicht brechen kann'
        );
    }
}
