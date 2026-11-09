<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #160): Click/Drag im Wochen-Kalender-
 * Grid muss die tatsaechlich gewaehlten Minuten uebergeben.
 *
 * Vorher: openForNew(ymd) fuellte immer 09:00-10:00 vor, egal wo der
 * Nutzer im Zeit-Grid geklickt hatte. Felix hat 11:00-14:00 gezogen und
 * der Dialog zeigte 09:00-10:00 - das fuehrte beim Speichern zur
 * Falschmeldung "Konflikt mit bestehendem Entry 09:00-11:00".
 *
 * Fix pinnt den Vertrag:
 *   - openForNew akzeptiert optionale (startMin, endMin)-Parameter
 *   - cal-day-grid traegt mousedown/mousemove/mouseup-Handler, die aus
 *     der Y-Position die Minute berechnen (15min-Snap, 24h = 100%)
 *   - Click ohne Drag fuellt eine Standard-60min-Range vor
 */
final class CalendarWeekGridTimeDragTest extends TestCase
{
    private function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
    }

    public function testOpenForNewAcceptsStartAndEndMinutes(): void
    {
        $js = $this->js();
        self::assertMatchesRegularExpression(
            '~function openForNew\(ymd,\s*startMin,\s*endMin\)~',
            $js,
            'openForNew muss optionale startMin/endMin-Parameter haben'
        );
        // Fallback auf 9:00/10:00 nur wenn Zeit null ist.
        self::assertMatchesRegularExpression(
            "~typeof startMin === 'number' && startMin >= 0~",
            $js,
            'startMin wird nur verwendet, wenn number und >= 0'
        );
        // Minute -> HH:MM-Format mit pad2.
        self::assertStringContainsString('pad2(Math.floor(sMin / 60))', $js,
            'startTime muss aus sMin per Modulo/Divide gebaut werden');
    }

    public function testCalDayGridHasMouseDragHandlers(): void
    {
        $js = $this->js();
        self::assertStringContainsString("grid.addEventListener('mousedown'", $js,
            'Mousedown-Handler auf cal-day-grid fehlt');
        self::assertStringContainsString("grid.addEventListener('mousemove'", $js,
            'Mousemove-Handler auf cal-day-grid fehlt');
        self::assertStringContainsString("grid.addEventListener('mouseup'", $js,
            'Mouseup-Handler auf cal-day-grid fehlt');
        // Minuten aus Y-Position berechnen, 15-Minuten-Snap.
        self::assertStringContainsString('function minuteFromEvent', $js,
            'Pixel->Minute-Mapping-Helper fehlt');
        self::assertMatchesRegularExpression(
            '~Math\.round\(raw\s*/\s*15\)\s*\*\s*15~',
            $js,
            '15-Minuten-Snap fehlt'
        );
    }

    public function testDragGuardsSkipExistingEventsAndPills(): void
    {
        $js = $this->js();
        // Clicks auf bestehende Events / Pillen / Brackets starten KEINEN
        // neuen Drag (haben eigene Edit-Handler).
        self::assertMatchesRegularExpression(
            "~closest\\('\\.cal-event, \\.cal-process, \\.cal-day-absence, \\.cal-absbar-pill, \\.cal-day-bracket'\\)~",
            $js,
            'mousedown-Guard muss Events, Pillen und Brackets ausschliessen'
        );
    }

    public function testShortClickFallsBackToStandardOneHourBlock(): void
    {
        $js = $this->js();
        // hi - lo < 15 -> hi = lo + 60 (1-Stunden-Fallback-Block).
        self::assertMatchesRegularExpression(
            '~hi\s*-\s*lo\s*<\s*15\)\s*\{\s*hi\s*=\s*lo\s*\+\s*60~',
            $js,
            'Reine Clicks (keine Drag-Bewegung) muessen auf 60min-Block fallen'
        );
    }
}
