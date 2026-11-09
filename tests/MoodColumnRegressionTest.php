<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression lock for the mood column / mood row in the calendar.
 *
 * Felix-Feedback 2026-11-08: "bitte noch persistieren ueber Regressionstests,
 * dass das dann auch immer drin bleibt und nicht mehr verschwindet. Und dann
 * auch immer konform mit dem Wochenbeginn wie es eingestellt ist."
 *
 * Covers two shapes the mood/flow picker takes in the calendar:
 *
 *  - WEEK rhythm (default): one picker column per visible week in the month
 *    grid, sitting LEFT of the configured week start; in the week view the
 *    same information sits in a single row above the day grid.
 *
 *  - DAY rhythm (admin override): one picker per day tile at the BOTTOM of
 *    the month cell; in the week view, under the day header.
 *
 * Both calendars delegate the week-start question to `Locale`, so even when
 * someone later makes the week-start configurable (planned for 1.1), the
 * mood column follows automatically. These tests pin that contract.
 */
final class MoodColumnRegressionTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    // -------------------------------------------------------------------
    // Locale helper
    // -------------------------------------------------------------------

    public function testLocaleWeekStartDefaultsToMondayWhenSettingAbsent(): void
    {
        TestSupport::bootInMemoryDb();
        self::assertSame(1, CalendarLocale::weekStartDow());
    }

    public function testLocaleWeekStartHonoursSetting(): void
    {
        TestSupport::bootInMemoryDb();
        // Sonntag (ISO 7) als Beispiel fuer ein nicht-europaeisches Office.
        Settings::set('calendar.week_start_dow', '7');
        self::assertSame(7, CalendarLocale::weekStartDow());

        $labels = CalendarLocale::weekdayShortLabels();
        self::assertCount(7, $labels);
        self::assertSame($labels[0], (string) t('cal.day_short.sun', 'So'),
            'Bei week_start_dow=7 muss die erste Label-Position Sonntag sein');
        self::assertSame($labels[1], (string) t('cal.day_short.mon', 'Mo'),
            'Bei week_start_dow=7 steht Montag auf Position 2');
    }

    public function testLocaleWeekStartClampsOutOfRangeToMonday(): void
    {
        TestSupport::bootInMemoryDb();
        Settings::set('calendar.week_start_dow', '99');
        self::assertSame(1, CalendarLocale::weekStartDow(), 'Ungueltige Werte fallen auf Montag zurueck');
        Settings::set('calendar.week_start_dow', '-1');
        self::assertSame(1, CalendarLocale::weekStartDow());
    }

    public function testLocaleWeekStartShiftsDateToConfiguredAnchor(): void
    {
        TestSupport::bootInMemoryDb();
        // 2026-11-04 is a Wednesday (ISO 3).
        $wed = new DateTimeImmutable('2026-11-04');

        Settings::set('calendar.week_start_dow', '1');
        self::assertSame('2026-11-02', CalendarLocale::weekStart($wed)->format('Y-m-d'),
            'Wochenanfang Montag -> Mo 2026-11-02');
        self::assertTrue(CalendarLocale::isWeekStart(new DateTimeImmutable('2026-11-02')));

        Settings::set('calendar.week_start_dow', '7');
        self::assertSame('2026-11-01', CalendarLocale::weekStart($wed)->format('Y-m-d'),
            'Wochenanfang Sonntag -> So 2026-11-01');
        self::assertTrue(CalendarLocale::isWeekStart(new DateTimeImmutable('2026-11-01')));

        Settings::set('calendar.week_start_dow', '6');
        self::assertSame('2026-10-31', CalendarLocale::weekStart($wed)->format('Y-m-d'),
            'Wochenanfang Samstag -> Sa 2026-10-31');
        self::assertTrue(CalendarLocale::isWeekStart(new DateTimeImmutable('2026-10-31')));
    }

    // -------------------------------------------------------------------
    // Month calendar: picker wiring
    // -------------------------------------------------------------------

    public function testMonthCalendarDelegatesWeekBoundariesToLocale(): void
    {
        // Felix' Kernregel fuer den Wochenanfang: einen einzigen Ort fuer die
        // Entscheidung. Die View darf NICHT eigenstaendig 'monday this week'
        // kennen - sonst wandert bei Konfigurationswechsel die Mood-Spalte
        // nicht mit.
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_month.php');
        self::assertStringContainsString('CalendarLocale::weekdayShortLabels()', $view,
            'Monats-View muss CalendarLocale::weekdayShortLabels() fuer die Dow-Header verwenden');
        self::assertStringContainsString('CalendarLocale::weekStart($monthStart)', $view,
            'Monats-View muss CalendarLocale::weekStart() fuer den Grid-Anfang verwenden');
        self::assertStringContainsString('CalendarLocale::weekEnd($monthEnd)', $view,
            'Monats-View muss CalendarLocale::weekEnd() fuer das Grid-Ende verwenden');
        self::assertStringContainsString('CalendarLocale::isWeekStart($d)', $view,
            'Die Mood-Zelle wird am konfigurierten Wochenanfang eingefuegt, nicht hart Montag');
        self::assertStringNotContainsString("modify('monday this week')", $view,
            'Hartes monday-this-week darf nicht mehr in der Monats-View stehen');
        self::assertStringNotContainsString("modify('sunday this week')", $view,
            'Hartes sunday-this-week darf nicht mehr in der Monats-View stehen');
    }

    public function testMonthCalendarWeekModeSitsLeftOfFirstWeekday(): void
    {
        // Das Markup muss die Mood-Zelle VOR die Tageszelle einfuegen, sodass
        // sie im CSS-Grid links davon landet. Pruefen: der Block mit dem
        // Wochenanfang-Check steht vor dem eigentlichen Zellen-Markup.
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_month.php');
        $moodBlock = strpos($view, 'cal-month-sent-col');
        // Position der dynamisch zusammengebauten Tageszelle im Quelltext
        // (Basisklasse `cal-month-cell`, danach optional Modifier).
        $cellBlock = strpos($view, "\$cls = 'cal-month-cell'");
        self::assertNotFalse($moodBlock, 'cal-month-sent-col fehlt');
        self::assertNotFalse($cellBlock, 'cal-month-cell-Deklaration fehlt');
        self::assertLessThan($cellBlock, $moodBlock,
            'cal-month-sent-col muss VOR der Tageszelle stehen (links im Grid)');
    }

    public function testMonthCalendarWeekModeGridHasEightColumns(): void
    {
        // CSS-Vertrag: 8-Spalten-Grid bei weekMode.
        $css = (string) file_get_contents($this->root() . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~cal-month-grid\.cal-month-grid-with-sent\{grid-template-columns:[^;]*repeat\(7~',
            $css,
            '8-Spalten-Grid (mood + 7 Tage) muss im CSS stehen'
        );
    }

    public function testMonthCalendarDayModeHasPickerAtBottomOfTile(): void
    {
        // Felix-Feedback 2026-11-08 (Monatsansicht day-Mode): "am unteren Ende
        // dieser Fliese bzw. Teil". Das Markup haengt den Picker nach der
        // navigations-<a>, und das CSS zieht ihn mit margin-top:auto ans Ende.
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_month.php');
        self::assertStringContainsString('cal-sent-day-month', $view,
            'Der Daily-Picker fuer die Monats-Fliese muss die Klasse cal-sent-day-month tragen');
        self::assertMatchesRegularExpression(
            '~</a>\s*<\?php\s+if\s*\(\s*\$dayMode~',
            $view,
            'Der Daily-Picker muss NACH dem Fliesen-Link stehen (am unteren Rand), nicht davor'
        );

        $css = (string) file_get_contents($this->root() . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~\.cal-sent-day-month\{[^}]*margin-top:auto~',
            $css,
            'cal-sent-day-month braucht margin-top:auto, damit der Picker am Boden der Fliese sitzt'
        );
    }

    // -------------------------------------------------------------------
    // Week calendar: picker wiring
    // -------------------------------------------------------------------

    public function testWeekCalendarWeekModeShowsPickerAboveDayGrid(): void
    {
        // Felix-Feedback 2026-11-08 (Wochenansicht week-Mode): "oben in der
        // Kopfzeile". Pruefen: cal-sent-week steht VOR cal-week (dem Tagesraster).
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_week.php');
        $sentBlock = strpos($view, 'cal-sent-week" data-monday=');
        $weekGrid  = strpos($view, 'class="cal-week"');
        self::assertNotFalse($sentBlock, 'Wochen-Picker-Markup (cal-sent-week) fehlt');
        self::assertNotFalse($weekGrid, 'Tages-Raster-Markup (cal-week) fehlt');
        self::assertLessThan($weekGrid, $sentBlock,
            'cal-sent-week (Wochen-Mood oben) muss VOR dem Tages-Raster stehen');
    }

    public function testWeekCalendarDayModeShowsPickerUnderDayHead(): void
    {
        // Felix-Feedback 2026-11-08 (Wochenansicht day-Mode): "in der
        // Wochenansicht dann oben in der Kopfzeile". Der Picker ist bereits
        // fuer jeden Tag am oberen Rand gerendert (direkt nach cal-day-head).
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_week.php');
        self::assertMatchesRegularExpression(
            '~cal-day-head.*?cal-sent-day~s',
            $view,
            'Der Daily-Picker muss direkt UNTER cal-day-head (also oben in der Kopfzeile) sitzen'
        );
    }

    // -------------------------------------------------------------------
    // Shared JS: both calendars must load it
    // -------------------------------------------------------------------

    public function testBothCalendarsLoadSharedSentimentPickerJs(): void
    {
        foreach (['calendar_week.php', 'calendar_month.php'] as $file) {
            $view = (string) file_get_contents($this->root() . '/views/entries/' . $file);
            self::assertStringContainsString('_sentiment_picker_js.php', $view,
                "$file muss das gemeinsame Sentiment-Picker-JS einbinden - sonst sind die Mood-Buttons tot");
        }
    }
}
