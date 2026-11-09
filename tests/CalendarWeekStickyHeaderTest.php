<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionslock fuer die durchgehende Wochentag-Kopfzeile im Wochen-Kalender.
 *
 * Felix-Feedback 2026-11-08: "ich sehe gerade, dass der Tag, der Wochentag
 * und das Datum nicht immer oben stehen bleibt wenn man die Uhrzeit nach
 * unten scrollt, sondern mit wegscrollt was bloed ist. ... deswegen wuerde
 * ich die Kopfzeile ueber den Uhrzeiten in eine stetige Zeile bewegen und
 * dann kann man auch in der Ecke wo dann ueber der Uhrzeit eine Zelle bzw.
 * Kachel entsteht."
 *
 * Nach #97 war die Kopfzeile sticky pro Tag separat; #109 hat sie zu einer
 * echten Corner-Zelle + 7 Tag-Spalten zusammengefuehrt. Diese Tests pinnen:
 *   - die neue Struktur (cal-week-head + cal-week-corner + cal-week-dayhead)
 *   - `.cal-week` bleibt der einzige Scroll-Container (sonst bricht sticky)
 *   - `.cal-hours` sticky-left (horizontaler Scroll)
 *   - den Daily-Mood-Picker direkt unter dem Head
 */
final class CalendarWeekStickyHeaderTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    private function view(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
    }

    public function testContinuousHeadRowDeclaresStickyAtTopZero(): void
    {
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\s*\{[^}]*position:\s*sticky[^}]*top:\s*0~s',
            $this->css(),
            '.cal-week-head muss position:sticky + top:0 setzen - sonst wandert die Wochentag-Zeile mit beim Scrollen'
        );
    }

    public function testHeadRowSpansFullWidthAndMatchesDaysGrid(): void
    {
        // Der Head muss die 52px + 7-Spalten-Struktur der Haupt-Zeile mitnehmen,
        // sonst sitzt die Mo-So-Beschriftung nicht exakt ueber den Tages-Spalten.
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\s*\{[^}]*grid-column:\s*1\s*/\s*-1[^}]*grid-template-columns:\s*52px\s+repeat\(7~s',
            $this->css(),
            '.cal-week-head muss beide .cal-week-Spalten spannen und sein eigenes 52px+7×1fr-Subgrid haben'
        );
    }

    public function testHeadRowDeclaresBackgroundSoContentDoesNotShineThrough(): void
    {
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\s*\{[^}]*background:[^;]+~s',
            $this->css(),
            '.cal-week-head braucht einen Hintergrund, damit Buchungen beim Scrollen nicht durchscheinen'
        );
    }

    public function testWeekGridIsThreeRowWithTheHeadFirst(): void
    {
        // .cal-week ist ein 2-Spalten-Grid + mindestens 2 Zeilen (auto 1fr),
        // damit der Head genau eine eigene Zeile ueber Uhrzeiten + Tages-Spalten besetzt.
        self::assertMatchesRegularExpression(
            '~\.cal-week\s*\{[^}]*grid-template-rows:\s*auto\s+1fr~s',
            $this->css(),
            '.cal-week braucht grid-template-rows: auto 1fr, damit der Head seine eigene Zeile hat'
        );
    }

    public function testCornerCellExistsOverTheHoursColumn(): void
    {
        self::assertStringContainsString('cal-week-corner', $this->view(),
            'Corner-Zelle (cal-week-corner) oben links ueber der Uhrzeiten-Spalte fehlt');
        self::assertMatchesRegularExpression(
            '~\.cal-week-corner\s*\{[^}]*border-right:[^}]+~s',
            $this->css(),
            'cal-week-corner braucht border-right, damit die visuelle Trennung zur Head-Zeile sichtbar bleibt'
        );
    }

    public function testDayHeadsAreNoLongerChildrenOfCalDay(): void
    {
        // Felix' Kernwunsch (#109): die Day-Heads sitzen OBEN als eine
        // durchgehende Zeile, nicht in jeder .cal-day einzeln. Die Prueefung
        // scannt den View: nach dem "cal-day" DIV-Open darf NICHT unmittelbar
        // ein cal-day-head folgen.
        self::assertStringContainsString('cal-week-dayhead', $this->view(),
            'cal-week-dayhead (Tageskopf in der durchgehenden Head-Zeile) fehlt');
        self::assertDoesNotMatchRegularExpression(
            '~<div class="cal-day[^"]*"[^>]*>\s*<div class="cal-day-head"~',
            $this->view(),
            'Day-Head darf nicht mehr als erstes Kind von .cal-day stehen - sonst sitzt er pro Spalte und nicht durchgehend oben'
        );
    }

    public function testStateModifiersMoveFromCalDayDescendantToDayheadClass(): void
    {
        // is-today / is-holiday / is-nonworking landen direkt am .cal-week-dayhead
        // als Marker-Klasse, damit die Farbregeln ohne .cal-day-* Parent greifen.
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead\.is-today[^{]*\{[^}]*color:\s*var\(--brand\)~s',
            $this->css(),
            'is-today-Regel auf cal-week-dayhead fehlt'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead\.is-holiday[^{]*\{[^}]*color:\s*var\(--brand\)~s',
            $this->css(),
            'is-holiday-Regel auf cal-week-dayhead fehlt'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead\.is-nonworking[^{]*\{[^}]*font-style:\s*italic~s',
            $this->css(),
            'is-nonworking-Regel (italic) auf cal-week-dayhead fehlt'
        );
    }

    public function testWeekContainerIsTheSoleScrollContainer(): void
    {
        // Horizontaler Overflow gehoert auf .cal-week, nicht auf .cal-days.
        // Wer das rueckgaengig macht, bricht sticky auf .cal-week-head.
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.cal-week\s*\{[^}]*overflow:\s*auto~s',
            $css,
            '.cal-week muss overflow:auto haben (vertikale + horizontale Scroll-Achse im selben Container)'
        );
        self::assertDoesNotMatchRegularExpression(
            '~\.cal-days\s*\{[^}]*overflow-x:\s*auto~s',
            $css,
            '.cal-days darf KEIN overflow-x:auto haben - das wuerde die sticky-Head-Zeile brechen'
        );
    }

    public function testNarrowViewportAlignsHeadAndDaysWithSameMinColumnWidth(): void
    {
        // Felix-Feedback 2026-11-09 (#125): bei max-width:820px haben die
        // .cal-day-Spalten min-width:120px und scrollen horizontal; die
        // .cal-week-head-Spalten mussten denselben Vertrag bekommen, sonst
        // rutschen die Wochentag-Labels relativ zu den Uhrzeit-Spalten.
        $css = $this->css();
        // Beide Regeln koennen in derselben max-width:820px-Media-Query
        // irgendwo zusammenstehen. Wir pruefen ihre Nachbarschaft.
        $pos = strpos($css, '@media(max-width:820px)');
        if ($pos === false) {
            $pos = strpos($css, '@media (max-width:820px)');
        }
        self::assertNotFalse($pos, '820-px-Media-Query fehlt');
        // Block-Ende: naechste Zeile, die nur "}" am Zeilen-Anfang hat.
        $end = strpos($css, "\n}\n", $pos);
        self::assertNotFalse($end, 'Ende der 820-px-Media-Query nicht gefunden');
        $block = substr($css, $pos, $end - $pos);
        self::assertMatchesRegularExpression(
            '~\.cal-day\s*\{\s*min-width:\s*120px~',
            $block,
            '.cal-day muss unter 820 px min-width:120px haben (bestehender Vertrag)'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-week-head\s*\{[^}]*grid-template-columns:\s*52px\s+repeat\(7,\s*minmax\(120px,\s*1fr\)\)~',
            $block,
            '.cal-week-head muss unter 820 px denselben minmax(120px,1fr)-Spalten-Vertrag haben, damit Head und cal-days synchron bleiben'
        );
    }

    public function testDayheadIsFlexColumnWithMoodBottomAligned(): void
    {
        // Felix-Feedback 2026-11-09 (Fix #147): Mood/Flow-Smileys muessen
        // in der cal-week-dayhead am Unterrand sitzen. Verhindert, dass die
        // Smileys nach oben rutschen, wenn benachbarte Spalten in Zukunft
        // eine Urlaubs-Reihe einfuegen (Pro-Parity + Issue 477).
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead,\.cal-day-head\s*\{[^}]*display:\s*flex[^}]*flex-direction:\s*column~s',
            $this->css(),
            'cal-week-dayhead muss display:flex + flex-direction:column sein'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-week-dayhead\s+\.cal-sent-day-head\s*\{[^}]*margin-top:\s*auto~s',
            $this->css(),
            'cal-sent-day-head braucht margin-top:auto, damit Mood/Flow am Boden sitzen'
        );
    }

    public function testHoursColumnStickyLeftKeepsLabelsVisibleWhenHorizontalScroll(): void
    {
        self::assertMatchesRegularExpression(
            '~\.cal-week\s+\.cal-hours\s*\{[^}]*position:\s*sticky[^}]*left:\s*0~s',
            $this->css(),
            '.cal-hours muss sticky left:0 sein, damit die Uhrzeiten-Spalte beim horizontalen Scroll stehen bleibt'
        );
    }

    public function testDailyMoodPickerSitsInsideStickyHeaderNotInsideCalDay(): void
    {
        // Felix-Feedback 2026-11-09 (#110): "Moods muessen in die Kopfzeile
        // rein, nicht in die Uhrzeiten-Tabelle - sonst scrollen die beim
        // Auto-Scroll zur ersten Buchungsstunde aus dem sichtbaren Bereich."
        //
        // Der Daily-Picker sitzt deshalb als Kind von .cal-week-dayhead
        // und NICHT mehr in .cal-day.
        $view = $this->view();

        self::assertStringContainsString('cal-sent-day-head', $view,
            'Daily-Picker braucht die Marker-Klasse cal-sent-day-head in der Kopfzeile');
        self::assertStringContainsString('cal-week-dayhead-label', $view,
            'Label-Wrapper fuer Wochentag in der Kopfzeile fehlt');

        $pos = strpos($view, 'class="cal-day<?=');
        self::assertNotFalse($pos, 'Spalten-Start "class=\"cal-day<?=" fehlt im Markup');
        $after = substr($view, $pos, 400);
        self::assertStringNotContainsString('cal-day-head', $after,
            'Nach dem Spalten-Start darf kein cal-day-head mehr folgen (Head ist jetzt oben)');
        self::assertStringNotContainsString('cal-sent-day', $after,
            'Daily-Mood-Picker darf NICHT mehr in .cal-day stehen - sonst scrollt er mit (Felix #110)'
        );
    }
}
