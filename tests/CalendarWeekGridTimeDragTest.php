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
        // Mousedown am Grid markiert den Start; mousemove/mouseup laufen auf
        // document, damit der Drag auch dann korrekt endet, wenn die Maus
        // das Grid waehrend des Drags verlaesst (Hardening 2026-11-09).
        self::assertStringContainsString("grid.addEventListener('mousedown'", $js,
            'Mousedown-Handler auf cal-day-grid fehlt');
        self::assertStringContainsString("document.addEventListener('mousemove'", $js,
            'Mousemove muss am document haengen, sonst reisst der Drag ab, wenn die Maus das Grid verlaesst');
        self::assertStringContainsString("document.addEventListener('mouseup'", $js,
            'Mouseup muss am document haengen, sonst wird der Drag nie aufgeloest, wenn die Maus das Grid verlaesst');
        // Minuten aus Y-Position berechnen, 15-Minuten-Snap.
        self::assertStringContainsString('function minuteFromEventOnGrid', $js,
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

    public function testAppJsHasNoLegacyGridDragToEntryForm(): void
    {
        // Felix-Feedback 2026-11-09 (Hardening 8): assets/app.js hatte
        // einen zweiten Grid-Drag-Handler, der parallel zu calendar.js
        // feuerte und via ?r=entry_form&start_ts=... navigierte. Dialog
        // blinkte auf (calendar.js) und verschwand sofort (app.js lud
        // die Legacy-Seite). Dieser Handler ist ERSATZLOS WEG.
        $appJs = (string) file_get_contents(dirname(__DIR__) . '/assets/app.js');
        self::assertStringNotContainsString(
            'r=entry_form&start_ts',
            $appJs,
            'app.js darf NICHT mehr via ?r=entry_form navigieren - sonst Dialog-Flicker'
        );
        self::assertStringNotContainsString(
            "document.querySelectorAll('.cal-day-grid')",
            $appJs,
            'app.js darf KEINEN eigenen cal-day-grid-Handler mehr registrieren - calendar.js macht das'
        );
    }

    public function testGridDragShowsLiveDraftPreview(): void
    {
        // Felix-Feedback 2026-11-09 (#165): Waehrend des Drags muss ein
        // .cal-event-draft Placeholder im Grid sichtbar sein und mit der
        // Maus mitwachsen - sonst wirkt der Drag "blind" bis zum mouseup.
        $js = $this->js();
        self::assertStringContainsString("'cal-event cal-event-draft'", $js,
            'Drag muss einen .cal-event-draft Placeholder erzeugen');
        self::assertStringContainsString("setProperty('--draft-top'", $js,
            'Draft-Position via CSS custom property --draft-top (CSP-strict)');
        self::assertStringContainsString("setProperty('--draft-height'", $js,
            'Draft-Hoehe via CSS custom property --draft-height');
        self::assertStringContainsString('function clearDraftPreview', $js,
            'Draft muss nach mouseup wieder entfernt werden');
        // CSS .cal-event-draft muss existieren (sonst waere die Preview
        // unsichtbar).
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertMatchesRegularExpression('~\.cal-event-draft\s*\{~', $css,
            'CSS-Regel fuer .cal-event-draft fehlt');
    }

    public function testOpenDialogUsesPreventScrollOnFocus(): void
    {
        $js = $this->js();
        // Firefox scrollt beim focus() auf ein weit oben stehendes Form-
        // Element (startIn, taskSel) an den Seitenanfang. preventScroll
        // haelt den Viewport an seiner Position.
        self::assertMatchesRegularExpression(
            '~\.focus\(\s*\{\s*preventScroll\s*:\s*true\s*\}\s*\)~',
            $js,
            'openDialog muss focus() mit preventScroll aufrufen - sonst springt die Seite nach oben'
        );
    }
}
