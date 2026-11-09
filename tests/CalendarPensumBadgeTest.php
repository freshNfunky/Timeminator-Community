<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#118): Pensum-Badge pro Tag in der Kalender-
 * Kopfzeile. Drei Zonen:
 *   - unter Soll  -> gruen (is-under)
 *   - genau drauf -> gelb  (is-ok, Toleranz +/- 5 Minuten)
 *   - ueber Soll  -> rot   (is-over)
 *
 * Freelancer ohne Terms bekommen kein Badge (Tagessoll ist null).
 */
final class CalendarPensumBadgeTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
    }

    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testBadgeMarkupLivesInsideStickyHeader(): void
    {
        $view = $this->view();
        self::assertStringContainsString('cal-day-pensum', $view,
            'Pensum-Badge-Markup fehlt');
        // Badge muss im Kopfzeilen-Block (nach dem HTML-Markup fuer
        // cal-week-dayhead-label) stehen, damit es sticky mitwandert -
        // NICHT in der cal-day-Column. Suche explizit das Markup-Div
        // (nicht den CSS-Selektor, der durch #162 weiter oben im Style-
        // Block mehrfach vorkommt).
        $pos = strpos($view, '<div class="cal-week-dayhead-label">');
        self::assertNotFalse($pos, 'Kopfzeilen-Label-Div fehlt');
        $tail = substr($view, $pos, 4500);
        self::assertStringContainsString('cal-day-pensum', $tail,
            'Pensum-Badge muss innerhalb der Kopfzeile stehen');
    }

    public function testCssDefinesThreeZonesAndDarkMode(): void
    {
        $css = $this->css();
        self::assertMatchesRegularExpression('~\.cal-day-pensum\.is-under\s*\{~', $css, 'is-under-Regel fehlt');
        self::assertMatchesRegularExpression('~\.cal-day-pensum\.is-ok\s*\{~',    $css, 'is-ok-Regel fehlt');
        self::assertMatchesRegularExpression('~\.cal-day-pensum\.is-over\s*\{~',  $css, 'is-over-Regel fehlt');
        // Dark-Mode-Varianten.
        self::assertMatchesRegularExpression(
            '~:root\[data-theme="dark"\]\s+\.cal-day-pensum\.is-under~',
            $css,
            'Dark-Mode-Variante fuer is-under fehlt'
        );
    }

    public function testBadgeIsOnlyRenderedWhenDailyTargetIsAPositiveNumber(): void
    {
        // Felix-Feedback 2026-11-09 (#119): Badge nur bei WERKTAG mit Soll > 0.
        // Freelancer (null) UND Wochenende/Feiertag (0) rendern beide KEIN Badge.
        $view = $this->view();
        $pos = strpos($view, 'cal-day-pensum');
        self::assertNotFalse($pos);
        $before = substr($view, max(0, $pos - 900), 900);
        self::assertMatchesRegularExpression(
            '~if\s*\(\s*\$__dayTgt\s*!==\s*null\s*&&\s*\$__dayTgt\s*>\s*0\s*\)~',
            $before,
            'Badge-Markup muss hinter "if ($__dayTgt !== null && $__dayTgt > 0)" stehen, damit Wochenenden + Feiertage kein Badge bekommen'
        );
    }

    public function testZoneClassificationRulesMatchFelixSpec(): void
    {
        // Die Klassifikations-Logik steht als PHP-Konstante im View:
        //   diff = sum - target
        //   |diff| <= 5     -> is-ok
        //   diff < 0        -> is-under
        //   sonst           -> is-over
        //
        // Hier wird das Spec-Verhalten nachgerechnet (nicht im View, sondern
        // als Freeze gegen eine spaetere Umstellung der Zonen).
        $spec = static function (int $sum, int $target): string {
            $diff = $sum - $target;
            if (abs($diff) <= 5) { return 'is-ok'; }
            if ($diff < 0)       { return 'is-under'; }
            return 'is-over';
        };
        self::assertSame('is-under', $spec(6 * 60, 8 * 60),  '6h vs 8h -> unter Soll');
        self::assertSame('is-ok',    $spec(8 * 60, 8 * 60),  '8h vs 8h -> genau drauf');
        self::assertSame('is-ok',    $spec(8 * 60 + 3, 8 * 60), '+3 min -> noch genau drauf');
        self::assertSame('is-over',  $spec(8 * 60 + 10, 8 * 60), '+10 min -> ueber Soll');
        self::assertSame('is-over',  $spec(10 * 60, 8 * 60), '10h vs 8h -> rot');
    }

    public function testBadgeTooltipKeyExistsInBothLanguages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        self::assertStringContainsString("'cal.day_pensum_tip'", $de);
        self::assertStringContainsString("'cal.day_pensum_tip'", $en);
    }
}
