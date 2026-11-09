<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#148): Monatsansicht - Drag ueber Kacheln
 * (horizontal in einer Woche, vertikal ueber mehrere Wochen) oeffnet den
 * Abwesenheits-Dialog mit vorbelegtem Range. Pillen rendern nur Werktage,
 * Wochenende + Feiertag erzeugen Luecken (WorkdaySpans).
 */
final class AbsenceDragBarMonthTest extends TestCase
{
    public function testMonthViewRendersAbsPillsFromWorkdaySpans(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_month.php');
        self::assertStringContainsString('cal-month-abs-pill', $view,
            'Pill-Klasse cal-month-abs-pill fehlt im Monat-Markup');
        self::assertStringContainsString('$__monthAbsPills', $view,
            'Pre-compute $__monthAbsPills muss existieren');
        self::assertStringContainsString('WorkdaySpans::workdaySpans', $view,
            'Monatsansicht muss Werktage-Zerlegung via WorkdaySpans nutzen');
        // Fix #153: Pillen werden als position:absolute-Overlays IN die
        // Zelle gerendert (nicht mehr als Grid-Items mit grid-row/column -
        // das hat den auto-flow der Kacheln verdraengt).
        self::assertStringContainsString('$__monthPillsByDay', $view,
            'Pillen werden pro ymd gruppiert, damit sie in der jeweiligen Zelle gerendert werden koennen');
        self::assertStringContainsString('cal-month-abs-slot-', $view,
            'Pro-Pill-Slot-Klasse fuer nonce-Style-Breite fehlt');
        self::assertMatchesRegularExpression(
            '~\.cal-month-abs-slot-<\?= \(int\) \$__k \?>\{width:calc\(~',
            $view,
            'Nonce-Style muss pro Pill nur noch die Breite setzen (span * 100%)'
        );
        self::assertStringNotContainsString('grid-column:', $view,
            'Grid-Column-Platzierung muss aus den Pill-Styles verschwunden sein (hat Cells verdraengt)');
    }

    public function testMonthCssDefinesPillAndDragStates(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        // Fix #153: Pillen sind jetzt absolute-Overlays, nicht Grid-Items.
        self::assertMatchesRegularExpression(
            '~\.cal-month-abs-pill\s*\{[^}]*position:\s*absolute~s',
            $css,
            'Pills muessen position:absolute sein, damit sie Grid-Auto-Flow nicht verdraengen'
        );
        // Kacheln brauchen position:relative + overflow:visible, damit die
        // absolute Pill span-weit nach rechts laufen kann.
        self::assertMatchesRegularExpression(
            '~\.cal-month-cell\s*\{[^}]*position:\s*relative[^}]*overflow:\s*visible~s',
            $css,
            'cal-month-cell muss position:relative + overflow:visible haben, damit Pills ueber die Zellgrenze laufen koennen'
        );
        self::assertStringContainsString('.cal-month-abs-pill-urlaub', $css);
        self::assertStringContainsString('.cal-month-abs-pill-sonderurlaub', $css);
        self::assertMatchesRegularExpression(
            '~\.cal-month-grid\.is-dragging\s*\{[^}]*cursor:\s*crosshair~s',
            $css
        );
        self::assertStringContainsString('.cal-month-cell.is-drag-range', $css);
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #154): Drag im Monat ging nicht,
     * weil der Link-Wrapper in jeder Zelle den Drag-Start blockierte.
     * Jetzt: Drag-Start-Guard entfernt den Link-Fall, preventDefault
     * unterdrueckt die Default-Navigation, mFinishDrag unterscheidet
     * zwischen echtem Drag (mDragMoved=true -> Dialog) und reinem Click
     * (false -> Link-Navigation zur Wochenansicht).
     */
    public function testMonthDragHandlerSupportsClickThroughAndRangeDrag(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString('mDragMoved', $js,
            'Flag mDragMoved muss zwischen Click und Drag unterscheiden');
        self::assertStringContainsString('cal-month-link', $js,
            'JS muss cal-month-link kennen (Navigation-Fallback bei reinem Click)');
        self::assertStringContainsString('if (moved) {', $js,
            'mFinishDrag muss nur bei mDragMoved den Dialog oeffnen');
        self::assertStringContainsString("window.location = link.href", $js,
            'Nicht-Drag-Click muss zur Wochenansicht navigieren');
    }

    public function testMonthDragHandlersWiredInJs(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString("document.querySelector('.cal-month-grid')", $js,
            'JS muss das Monats-Grid selektieren fuer Drag-Handler');
        self::assertStringContainsString("mgrid.addEventListener('mousedown'", $js,
            'Mousedown-Handler auf cal-month-grid fehlt');
        self::assertStringContainsString("mgrid.addEventListener('mousemove'", $js,
            'Mousemove-Handler auf cal-month-grid fehlt');
        self::assertStringContainsString("mgrid.addEventListener('mouseup'", $js,
            'Mouseup-Handler auf cal-month-grid fehlt');
        self::assertStringContainsString('cal-month-abs-pill', $js,
            'Click-Handler fuer Monats-Pillen (Edit-Dialog) fehlt');
    }

    /**
     * Felix-Feedback 2026-11-09 nach dem #148/#153/#154-Fix:
     * "Ja, genau so ist richtig. Und jetzt funktioniert auch diese
     * Click-and-Track-Funktionalitaet. Bitte festhalten in Regressions-
     * tests, dass das auf jeden Fall so erhalten bleibt."
     *
     * Der komplette Drag-Flow (Screenshot: 02.11.-06.11. Urlaub):
     *   1. mousedown auf Monats-Kachel startet Drag (preventDefault).
     *   2. mousemove ueber weitere Kacheln setzt mDragMoved + Range.
     *   3. mouseup ruft openForNewAbsenceRange(from, to) auf.
     *   4. Overlay-Dialog oeffnet mit Typ=Urlaub praeselektiert,
     *      Von/Bis vorbelegt, OHNE Projekt-Hierarchie, OHNE Start/Ende-
     *      Datetime, OHNE Stunden-Feld.
     */
    public function testDragOpensDialogWithVacationPreselectedAndReducedFields(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');

        self::assertStringContainsString('function openForNewAbsenceRange', $js,
            'openForNewAbsenceRange-Funktion fehlt');
        self::assertMatchesRegularExpression(
            "~ckey\\.indexOf\\('urlaub'\\) !== -1~",
            $js,
            'Pre-Select muss "Urlaub"-Option praeferiert waehlen'
        );
        self::assertMatchesRegularExpression(
            "~ckey\\.indexOf\\('sonder'\\) === -1~",
            $js,
            'Pre-Select darf nicht auf Sonderurlaub fallen (bewusste zweite Wahl)'
        );
        self::assertStringContainsString('fromIn.value = fromYmd;', $js,
            'Von-Feld muss aus Drag-Range vorbelegt werden');
        self::assertStringContainsString('toIn.value   = toYmd', $js,
            'Bis-Feld muss aus Drag-Range vorbelegt werden');

        // setType-Logik: taskWrap ist bei Absence hidden; timeRow/dateRow
        // schalten sich je nach Ueberstundenabbau (Fix #171) um - bei allen
        // anderen Absence-Arten ist dateRow sichtbar und timeRow hidden.
        self::assertMatchesRegularExpression(
            "~taskWrap\\.hidden\\s*=\\s*\\(k !== 'project'\\)~",
            $js,
            'Task-Picker muss bei Absence hidden werden'
        );
        self::assertMatchesRegularExpression(
            "~timeRow\\.hidden\\s*=\\s*\\(k === 'absence'\\s*&&\\s*!isOvertime\\)~",
            $js,
            'Start/Ende-Datetime muss bei Absence verschwinden'
        );
        self::assertMatchesRegularExpression(
            "~dateRow\\.hidden\\s*=\\s*\\(k !== 'absence'\\s*\\|\\|\\s*isOvertime\\)~",
            $js,
            'Von/Bis-Date muss bei Absence sichtbar sein (ausser Ueberstundenabbau - Fix #171)'
        );
        // Fix #171: hoursWrap ist generell hidden - Ueberstundenabbau laeuft
        // Zeit-basiert, die Stundenzahl ergibt sich aus start_ts/end_ts.
        self::assertStringContainsString('hoursWrap.hidden = true', $js,
            'Stunden-Feld ist jetzt generell hidden (Ueberstundenabbau laeuft Zeit-basiert)');
    }

    public function testMonthDragGuardsAllowPillsAndMoodNotToTriggerRangeSelection(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        // Click auf Pille oder Mood-Picker darf KEINEN Drag starten -
        // sie haben eigene Edit/Record-Flows.
        self::assertMatchesRegularExpression(
            "~closest\\('\\.cal-month-abs-pill, \\.cal-sent-cell, \\.cal-sent-day, \\.cal-sent-week'\\)~",
            $js,
            'mousedown-Guard muss Pills und Mood/Flow-Picker vom Drag-Start ausnehmen'
        );
        // Aber cal-month-link darf NICHT im Guard stehen - das war Fix #154:
        // sonst fangen Links jedes mousedown ab und Drag startet nie.
        self::assertStringNotContainsString(
            "closest('.cal-month-abs-pill, .cal-sent-cell, .cal-month-link",
            $js,
            'cal-month-link darf NICHT im mousedown-Guard stehen (hat frueher den Drag unterbunden)'
        );
    }

    public function testMonthViewHasNoPhpSyntaxErrors(): void
    {
        // Letzter Guard: Fix #153 hatte zwischenzeitlich ein endif
        // verschluckt und der Monat warf 500. Dieser Test laesst PHP die
        // Datei parsen, damit das nicht wieder passiert.
        $view = dirname(__DIR__) . '/views/entries/calendar_month.php';
        $out  = [];
        $ret  = 0;
        exec('php -l ' . escapeshellarg($view) . ' 2>&1', $out, $ret);
        self::assertSame(0, $ret,
            'php -l schlaegt in calendar_month.php fehl: ' . implode("\n", $out));
    }
}
