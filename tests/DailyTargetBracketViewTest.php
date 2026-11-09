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
        self::assertNull(EmployeeRepo::dailyTargetMinutes($uid, '2026-11-09'),
            'Ohne terms-Row darf dailyTargetMinutes null liefern (keine Klammer rendern)');

        // Angestellter mit 40h-Woche + 5-Tage-Preset -> 8h/Tag = 480 Minuten.
        // 2026-11-09 ist ein Montag (Werktag).
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null);
        self::assertSame(480, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-09'));
    }

    public function testWeekendsAreZeroHourDays(): void
    {
        // Felix-Feedback 2026-11-09 (#119): Wochenenden duerfen NICHT mit
        // 8h als Soll gerechnet werden. In den Presets sind Mo-Fr Arbeitstage;
        // Samstag/Sonntag muessen 0 liefern.
        TestSupport::bootInMemoryDb();
        $uid = TestSupport::seedUser('employee');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null);
        // 2026-11-07 = Samstag, 2026-11-08 = Sonntag (Default-Preset Mo-Fr).
        self::assertSame(0, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-07'),
            'Samstag muss Tagessoll 0 liefern (kein Werktag in Default-Presets)');
        self::assertSame(0, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-08'),
            'Sonntag muss Tagessoll 0 liefern');
    }

    public function testHolidayInUserRegionIsZeroHourDay(): void
    {
        // Felix-Feedback 2026-11-09 (#119): "Feiertage gelten auch natuerlich
        // als freie Tage." DE-BY: Allerheiligen ist 1. November. Falls
        // 1.11.2026 ein Werktag ist (das ist ein Sonntag), nehmen wir
        // stattdessen den Nationalfeiertag 3.10.2026 (Samstag) oder
        // Tag der Deutschen Einheit - 2026-10-03 ist ein Samstag.
        // Fester Werktag-Feiertag in DE: 2026-05-01 (Fr Tag der Arbeit).
        TestSupport::bootInMemoryDb();
        $uid = TestSupport::seedUser('employee_bavaria');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, 'DE-BY');
        self::assertSame(0, EmployeeRepo::dailyTargetMinutes($uid, '2026-05-01'),
            'Werktag-Feiertag (Tag der Arbeit, Freitag) muss Tagessoll 0 liefern fuer DE-BY');
    }

    public function testNoHolidayRegionLeavesTargetUntouched(): void
    {
        // Wenn der User keine holiday_region hat, zaehlen Feiertage nicht
        // als frei - er bekommt den regulaeren Werktag-Tagessoll.
        TestSupport::bootInMemoryDb();
        $uid = TestSupport::seedUser('employee_no_region');
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null);
        self::assertSame(480, EmployeeRepo::dailyTargetMinutes($uid, '2026-05-01'),
            'Ohne holiday_region bleibt der Tagessoll am 1. Mai bei 480 Min.');
    }
}
