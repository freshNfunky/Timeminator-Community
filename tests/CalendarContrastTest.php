<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: Kalender-Eintraege bekommen kontrastbasierte Textfarbe.
 *
 * Felix-Feedback 2026-11-06: Zeiteintrag-Bloecke auf gelbem/orangem/
 * hellgruenem Hintergrund waren mit weissem Default-Text unlesbar.
 * Jeder Eintrag berechnet jetzt beim Rendern anhand der Luminanz
 * (0.299*R + 0.587*G + 0.114*B) ob schwarze oder weisse Schrift
 * besser lesbar ist - Schwelle 150.
 *
 * Dieser Test friert zwei Dinge ein:
 *   1. Die Luminanz-Formel + Schwelle in views/entries/calendar_week.php
 *      (als anonyme Closure $__calInk).
 *   2. Dass jeder .cal-event-ID-Regel-Block eine color:-Deklaration
 *      bekommt, wenn die Zeile eine Hintergrundfarbe traegt.
 */
final class CalendarContrastTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    /** Hilfsfunktion: PHP-seitige Reproduktion der View-Formel, damit der
     *  Test die Schwellenwerte direkt ueberpruefen kann. */
    private function pickInk(string $hex): string
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) {
            return '#ffffff';
        }
        $r = hexdec(substr($h, 0, 2));
        $g = hexdec(substr($h, 2, 2));
        $b = hexdec(substr($h, 4, 2));
        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        return $lum > 150 ? '#1f2937' : '#ffffff';
    }

    public function testLuminanceFormulaPicksDarkTextOnLightBackgrounds(): void
    {
        // Helle Farben (Felix' Screenshot): Gelb, Orange - die kritischen Faelle.
        self::assertSame('#1f2937', $this->pickInk('#FFD500'), 'Gelb muss schwarze Schrift geben');
        self::assertSame('#1f2937', $this->pickInk('#f59e0b'), 'Orange (tailwind amber-500) muss schwarze Schrift geben');
        self::assertSame('#1f2937', $this->pickInk('#ffffff'), 'Weiss auf Weiss waere unleserlich - schwarze Schrift');
        self::assertSame('#1f2937', $this->pickInk('#fde68a'), 'Hellgelb (amber-200, dark-mode cal-process-text) wird schwarze Schrift geben');
        // 3-stellige Hex-Variante
        self::assertSame('#1f2937', $this->pickInk('#fd0'), '3-stelliges Hex fuer helles Gelb muss gleich behandelt werden');
    }

    public function testLuminanceFormulaPicksLightTextOnDarkBackgrounds(): void
    {
        // Dunkle Farben: Blau, dunkles Grau, dunkles Rot, Navy
        self::assertSame('#ffffff', $this->pickInk('#2563eb'), 'Brand-Blau muss weisse Schrift geben');
        self::assertSame('#ffffff', $this->pickInk('#1f2937'), 'Dunkelgrau muss weisse Schrift geben');
        self::assertSame('#ffffff', $this->pickInk('#8b5cf6'), 'Lila muss weisse Schrift geben');
        self::assertSame('#ffffff', $this->pickInk('#92400e'), 'Dunkles Orange (dark-mode cal-process) muss weisse Schrift geben');
        self::assertSame('#ffffff', $this->pickInk('#000000'), 'Schwarz muss weisse Schrift geben');
    }

    public function testLuminanceFormulaReturnsWhiteFallbackForInvalidColor(): void
    {
        self::assertSame('#ffffff', $this->pickInk(''));
        self::assertSame('#ffffff', $this->pickInk('zzz'));
        self::assertSame('#ffffff', $this->pickInk('not-a-color'));
        self::assertSame('#ffffff', $this->pickInk('#12345'));  // falsche Laenge
    }

    public function testCalendarViewEmitsLuminanceLogicAndColorDeclaration(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/entries/calendar_week.php');

        // Luminanz-Closure $__calInk definiert mit der WCAG-approximierten Formel.
        self::assertStringContainsString(
            '$__calInk = static function (string $hex): string',
            $view,
            'View muss die $__calInk-Closure definieren'
        );
        self::assertStringContainsString(
            '0.299 * $r + 0.587 * $g + 0.114 * $b',
            $view,
            'WCAG-approximierte Luminanz-Formel muss wortgetreu im View stehen'
        );
        // Schwelle 150.
        self::assertStringContainsString(
            "\$lum > 150 ? '#1f2937' : '#ffffff'",
            $view,
            'Schwellenwert 150 fuer helle-vs-dunkle Entscheidung muss fest stehen'
        );
        // Jede .cal-event-<id>-Regel emittiert eine color:-Deklaration zusaetzlich
        // zu background-color (nur wenn eine Farbe vorhanden ist).
        self::assertStringContainsString(
            "'background-color:' . \$__ev['color'] . ';color:' . \$__ink",
            $view,
            'Pro-Event-CSS muss color aus $__ink zusaetzlich zur background-color setzen'
        );
    }
}
