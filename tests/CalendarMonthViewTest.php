<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: Monatsansicht im Kalender.
 *
 * Historische Regression (Felix-Feedback 2026-11-04: "warum gibt es keine
 * monats-sicht mehr? was ist los? warum verschwinden permanent features die
 * es früher gab. sofort wieder rein!! und dann ein regressionstest der das
 * immer überprüft damit das nicht nochmal passiert.").
 *
 * Dieser Test friert sowohl die Existenz der Monats-View als auch den
 * Controller-Weg ein, der zu ihr fuehrt. Umbenennen/Loeschen einer der
 * Teile macht den Test rot.
 */
final class CalendarMonthViewTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    public function testMonthCalendarViewFileExists(): void
    {
        $path = $this->root() . '/views/entries/calendar_month.php';
        self::assertFileExists($path, 'views/entries/calendar_month.php muss vorhanden sein (Pro-Paritaet)');
    }

    public function testEntriesIndexDispatchesMonthView(): void
    {
        $index = file_get_contents($this->root() . '/views/entries/index.php');
        self::assertIsString($index);

        // Der Dispatcher muss calendar_month.php einbinden, wenn calendarSpan='month'.
        self::assertStringContainsString("calendar_month.php", $index, 'index.php bindet calendar_month.php nicht ein');
        self::assertStringContainsString("'month'", $index, 'calendarSpan-Weiche fehlt im Dispatcher');
    }

    public function testEntriesControllerParsesSpanAndMonth(): void
    {
        $ctrl = file_get_contents($this->root() . '/src/controllers/entries_ctrl.php');
        self::assertIsString($ctrl);

        self::assertMatchesRegularExpression(
            "/\\\$spanParam\\s*=.*get\\('span'/s",
            $ctrl,
            'Controller liest ?span= nicht aus der Query'
        );
        self::assertStringContainsString("'calendarSpan'", $ctrl, 'calendarSpan wird nicht an die View uebergeben');
        self::assertStringContainsString("'month_start'", $ctrl, 'month_start wird nicht an die View uebergeben');
    }

    public function testMonthViewRendersGridAndNavigation(): void
    {
        $view = file_get_contents($this->root() . '/views/entries/calendar_month.php');
        self::assertIsString($view);

        // Grid + Toggle + Monats-Navigation + Projekt-Zeilen.
        // Die Grid-Klasse wird dynamisch gebaut ('cal-month-grid' plus ggf.
        // ' cal-month-grid-with-sent' bei weeklichem Rhythmus), daher Substring.
        self::assertStringContainsString('cal-month-grid', $view, 'Monats-Grid fehlt');
        self::assertStringContainsString('class="cal-month-cell', $view, 'Monats-Zelle fehlt');
        self::assertStringContainsString('class="cal-span-toggle"', $view, 'Woche/Monat-Toggle fehlt');
        self::assertStringContainsString('cal.prev_month', $view, 'Vormonat-Nav fehlt');
        self::assertStringContainsString('cal.next_month', $view, 'Folgemonat-Nav fehlt');
        // Zellen-Inhalt: Projektlisten.
        self::assertStringContainsString('cal-month-projs', $view, 'Projekt-Liste pro Zelle fehlt');
    }

    public function testWeekViewAlsoOffersSpanToggle(): void
    {
        $cal = file_get_contents($this->root() . '/views/entries/calendar_week.php');
        self::assertIsString($cal);
        self::assertStringContainsString('cal-span-toggle', $cal, 'Woche/Monat-Toggle fehlt in der Wochenansicht');
        self::assertStringContainsString("'span' => 'month'", $cal, 'Monats-Link fehlt im Woche/Monat-Toggle');
    }

    public function testMonthViewHasWeeklyMoodColumnInsteadOfDailyByDefault(): void
    {
        // Felix-Feedback 2026-11-08: "Default ist wöchentlich und nicht
        // täglich. Das Ganze gilt auch für Monat. … links vom Montag bzw.
        // dem ersten Tag in der Woche noch eine Spalte für das Mood-Tracking."
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_month.php');

        // Granularity-Entscheidung + 8-Spalten-Modifier.
        self::assertStringContainsString("sentGranularity", $view, 'Monats-View muss die Granularitaet lesen');
        self::assertStringContainsString("cal-month-grid-with-sent", $view, '8-Spalten-Grid-Modifier fehlt');
        self::assertStringContainsString("cal-month-sent-col", $view, 'Mood-Spalte links vom Montag fehlt');

        // Wochen-Picker-Markup (per Row) ist vorhanden.
        self::assertStringContainsString("cal-sent-week", $view, 'Wochen-Picker-Markup fehlt');
        self::assertStringContainsString('data-dim="mood"', $view, 'Mood-Dim fehlt im Wochen-Picker');
        self::assertStringContainsString('data-dim="flow"', $view, 'Flow-Dim fehlt im Wochen-Picker');

        // Picker nur bei Montag einfuegen (eine Mood-Zelle pro Woche, nicht pro Tag).
        self::assertStringContainsString('format(\'N\') === 1', $view, 'Pro Woche genau ein Mood-Picker: Check auf Montag fehlt');

        // Daily-Picker darf im Monat NICHT unkonditional gerendert werden -
        // er haengt an $dayMode (nur wenn Granularitaet='day').
        self::assertStringContainsString('$dayMode &&', $view, 'Daily-Mood pro Fliese muss an $dayMode haengen (sonst erscheint er immer)');
        self::assertStringContainsString("cal-sent-day-month", $view, 'Daily-Picker fuer Monatsfliesen fehlt');
    }

    public function testMonthViewLoadsSharedSentimentPickerJs(): void
    {
        // Damit Klicks funktionieren: die gemeinsame Sentiment-JS wird eingebunden,
        // nicht nur in der Wochenansicht. Sonst sind die Buttons tot.
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_month.php');
        self::assertStringContainsString("_sentiment_picker_js.php", $view, 'Monats-View muss das gemeinsame Sentiment-Picker-JS einbinden');

        $week = (string) file_get_contents($this->root() . '/views/entries/calendar_week.php');
        self::assertStringContainsString("_sentiment_picker_js.php", $week, 'Wochen-View muss ebenfalls das gemeinsame Sentiment-Picker-JS einbinden');

        $shared = $this->root() . '/views/entries/_sentiment_picker_js.php';
        self::assertFileExists($shared, 'Gemeinsames Sentiment-Picker-JS-Partial fehlt');
        $js = (string) file_get_contents($shared);
        self::assertStringContainsString('cal-sent-day', $js, 'Daily-Picker-Listener fehlt im Shared-JS');
        self::assertStringContainsString('cal-sent-week', $js, 'Weekly-Picker-Listener fehlt im Shared-JS');
    }

    public function testEntriesControllerPassesSentimentDataForMonthView(): void
    {
        // Felix-Feedback 2026-11-08: ohne geladene Sentiment-Daten bleibt der
        // Monat-Picker leer. Der Controller muss die Woche UND den Monatsbereich
        // (padStart..padEnd) abdecken.
        $ctrl = (string) file_get_contents($this->root() . '/src/controllers/entries_ctrl.php');
        self::assertStringContainsString("\$calendarSpan === 'month'", $ctrl, 'Controller muss im Monatsmodus die Sentiment-Range erweitern');
        self::assertStringContainsString('$padStart', $ctrl, 'padStart wird fuer die Monats-Sentiment-Range gebraucht');
        self::assertStringContainsString('$padEnd', $ctrl, 'padEnd wird fuer die Monats-Sentiment-Range gebraucht');
    }

    public function testClaudeMdLocksFeatureRegressionRule(): void
    {
        // Die CLAUDE.md-Regel "Jedes Feature braucht einen Regressionstest"
        // ist selbst regressionsgeschuetzt: wer sie entfernt, bricht diesen Test.
        $path = $this->root() . '/CLAUDE.md';
        self::assertFileExists($path, 'CLAUDE.md fehlt im Repo-Root');
        $body = (string) file_get_contents($path);
        self::assertStringContainsString('Regressionstest', $body, 'CLAUDE.md verliert die Regressionstest-Regel');
        self::assertStringContainsString('MUSS', $body, 'CLAUDE.md verliert die verbindliche Formulierung');
    }
}
