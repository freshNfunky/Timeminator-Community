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
        self::assertStringContainsString('class="cal-month-grid"', $view, 'Monats-Grid fehlt');
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
