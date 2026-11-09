<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #138): Pro-Parity - rote "Jetzt"-Linie
 * im Wochen-Kalender, horizontal quer durch die heutige Tag-Spalte auf
 * Hoehe der aktuellen Uhrzeit. Nur sichtbar, wenn die gerade angezeigte
 * Woche TODAY enthaelt.
 *
 * Zurrt die View-Vertraege (Markup + CSS + nonce-Style) + Controller-
 * Mathe (Minuten -> Prozent des 24-h-Tages) + i18n fest.
 */
final class CalendarNowLineTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
    }

    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testViewComputesIsCurrentWeekAndMinuteOfDay(): void
    {
        $view = $this->view();
        self::assertStringContainsString('$__isCurWeek', $view,
            'Current-Week-Flag muss in der View berechnet werden');
        self::assertStringContainsString('$__nowPct', $view,
            '$__nowPct (0..100) muss aus der aktuellen Minute abgeleitet werden');
        // Minute-of-day: Stunden*60 + Minuten.
        self::assertMatchesRegularExpression(
            '~\$__nowMin\s*=\s*\(int\)\s*\$__nowDt->format\(\'G\'\)\s*\*\s*60\s*\+\s*\(int\)\s*\$__nowDt->format\(\'i\'\)~',
            $view,
            'Minute-of-day muss G*60+i rechnen (0-basierte Stunde)'
        );
    }

    public function testViewEmitsNowLineMarkupOnlyForTodayAndWhenInCurrentWeek(): void
    {
        $view = $this->view();
        // Markup ist an today + $__nowPct gebunden.
        self::assertMatchesRegularExpression(
            '~\$__day\[\'isToday\'\]\s*&&\s*\$__nowPct\s*!==\s*null~',
            $view,
            'cal-now-line-Markup muss doppelt gegatet sein (today UND $__nowPct)'
        );
        self::assertStringContainsString('class="cal-now-line"', $view,
            'Markup fuer die Jetzt-Linie fehlt');
        self::assertStringContainsString("t('cal.now_line', 'Jetzt')", $view,
            'i18n-Key cal.now_line muss im Tooltip verwendet werden');
    }

    public function testNonceStyleBlockEmitsCalNowLineTopOnlyWhenNowPctAvailable(): void
    {
        $view = $this->view();
        self::assertMatchesRegularExpression(
            '~\.cal-now-line\{top:<\?= \$__nowPct \?>%\}~',
            $view,
            'nonce-Style muss .cal-now-line{top:X%} setzen'
        );
        self::assertStringContainsString('if ($__nowPct !== null)', $view,
            'nonce-Emission muss durch $__nowPct !== null gegatet sein');
    }

    public function testCssDefinesNowLineAsRedBarWithDot(): void
    {
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.cal-now-line\s*\{[^}]*position:\s*absolute[^}]*background:\s*#ef4444~s',
            $css,
            '.cal-now-line muss absolute positioniert + rot (#ef4444) sein'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-now-line::before\s*\{[^}]*border-radius:\s*50%~s',
            $css,
            '.cal-now-line::before muss einen runden Dot rendern'
        );
        // Dark-Mode-Variante.
        self::assertStringContainsString(':root[data-theme="dark"] .cal-now-line', $css,
            'Dark-Mode-Variante fuer die Jetzt-Linie fehlt');
    }

    public function testI18nKeysPresentInBothLanguages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        self::assertStringContainsString("'cal.now_line'", $de);
        self::assertStringContainsString("'cal.now_line'", $en);
    }
}
