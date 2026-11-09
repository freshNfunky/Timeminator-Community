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
}
