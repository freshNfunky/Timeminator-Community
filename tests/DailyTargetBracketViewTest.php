<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionslock dafuer, dass der Wochenkalender den Tagessoll-Bracket
 * tatsaechlich einbindet. Die Logik selbst ist in DayBracketTest gepinnt,
 * die EmployeeRepo-Helper in EmployeeRepoTest - hier geht es nur um die
 * View-Integration:
 *
 *   - calendar_week.php nutzt `EmployeeRepo::dailyTargetMinutes`
 *   - DayBracket::compute wird aufgerufen
 *   - Das Markup rendert `.cal-day-bracket-target-<offset>` + optional -over
 *   - CSS fuer .cal-day-bracket + .cal-day-bracket-over (gestrichelt) ist da
 *   - i18n-Keys fuer die Tooltips sind in beiden Sprachen vorhanden
 */
final class DailyTargetBracketViewTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
    }

    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testViewReadsPerUserDailyTarget(): void
    {
        self::assertStringContainsString('EmployeeRepo::dailyTargetMinutes', $this->view(),
            'View muss das Tagessoll per User aus EmployeeRepo abrufen');
        self::assertStringContainsString('DayBracket::compute', $this->view(),
            'View muss die Klammer-Grenzen via DayBracket::compute berechnen');
    }

    public function testViewRendersBracketMarkupForEachDay(): void
    {
        $view = $this->view();
        self::assertStringContainsString('cal-day-bracket-target-', $view,
            'Target-Klammer-Divs (indexiert pro Day-Offset) muessen gerendert werden');
        self::assertStringContainsString('cal-day-bracket-over-', $view,
            'Overtime-Klammer-Divs (indexiert pro Day-Offset) muessen gerendert werden');
        self::assertStringContainsString("t('cal.day_bracket_target'", $view,
            'Tooltip-Key fuer Target-Klammer muss im View stehen');
        self::assertStringContainsString("t('cal.day_bracket_over'", $view,
            'Tooltip-Key fuer Overtime-Klammer muss im View stehen');
    }

    public function testCssDeclaresTargetAndOverBracketStyles(): void
    {
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.cal-day-bracket\s*\{[^}]*position:\s*absolute[^}]*right:[^}]+border-left:\s*0~s',
            $css,
            '.cal-day-bracket muss absolut platziert sein und den linken Rand offen lassen'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-day-bracket-over\s*\{[^}]*border-style:\s*dashed~s',
            $css,
            '.cal-day-bracket-over muss border-style:dashed setzen (gestrichelte Overtime-Klammer)'
        );
    }

    public function testI18nKeysArePresentInBothLanguages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        foreach (['cal.day_bracket_target', 'cal.day_bracket_over'] as $key) {
            self::assertStringContainsString("'$key'", $de, "DE-Key $key fehlt");
            self::assertStringContainsString("'$key'", $en, "EN-Key $key fehlt");
        }
    }

    public function testEmployeeRepoDailyTargetMinutesReturnsNullForFreelancers(): void
    {
        TestSupport::bootInMemoryDb();
        $uid = TestSupport::seedUser('freelancer');
        self::assertNull(EmployeeRepo::dailyTargetMinutes($uid, '2026-11-08'),
            'Ohne terms-Row darf dailyTargetMinutes null liefern (keine Klammer rendern)');

        // Angestellter mit 40h-Woche + 5-Tage-Preset -> 8h/Tag = 480 Minuten.
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null);
        self::assertSame(480, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-08'));
    }
}
