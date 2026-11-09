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
        // Grid-Column + Grid-Row via nonce-Style (CSP-strict).
        self::assertStringContainsString('cal-month-abs-slot-', $view,
            'Pro-Pill-Slot-Klasse fuer nonce-Style-Platzierung fehlt');
        self::assertMatchesRegularExpression(
            '~\.cal-month-abs-slot-<\?= \(int\) \$__k \?>\{grid-column:[^}]+grid-row:~',
            $view,
            'Nonce-Style muss grid-column + grid-row pro Pill setzen'
        );
    }

    public function testMonthCssDefinesPillAndDragStates(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertStringContainsString('.cal-month-abs-pill-urlaub', $css,
            'Urlaubs-Pill-Variante fuer Monat fehlt');
        self::assertStringContainsString('.cal-month-abs-pill-sonderurlaub', $css,
            'Sonderurlaubs-Pill-Variante fuer Monat fehlt');
        self::assertMatchesRegularExpression(
            '~\.cal-month-grid\.is-dragging\s*\{[^}]*cursor:\s*crosshair~s',
            $css,
            'Drag-Zustand im Monats-Grid muss cursor:crosshair setzen'
        );
        self::assertStringContainsString('.cal-month-cell.is-drag-range', $css,
            'Highlight-Klasse fuer Drag-Range-Kacheln fehlt');
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
