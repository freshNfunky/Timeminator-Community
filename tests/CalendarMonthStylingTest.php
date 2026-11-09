<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09:
 *
 * #122: Monatsansicht muss Feiertage + Wochenenden farblich hervorheben
 *       wie die Wochenansicht - Parity.
 *
 * #123: Feiertagsregion kommt aus employee_terms.holiday_region, nicht
 *       aus dem System-Default. Ein Bayern-User sieht den 6. Januar
 *       (Heilige Drei Koenige) damit korrekt als Feiertag.
 */
final class CalendarMonthStylingTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_month.php');
    }

    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testMonthCellGetsWeekendClassForNonWorkingDays(): void
    {
        self::assertStringContainsString('is-weekend', $this->view(),
            'Monats-Fliese muss .is-weekend-Marker rendern');
        self::assertStringContainsString("Holidays::workingDays()", $this->view(),
            'Weekend-Bestimmung muss ueber Holidays::workingDays() laufen');
    }

    public function testMonthCssDefinesWeekendAndHolidayBackgrounds(): void
    {
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.cal-month-cell\.is-weekend\s*\{[^}]*background-color~s',
            $css,
            'Weekend-Hintergrund fehlt'
        );
        self::assertMatchesRegularExpression(
            '~\.cal-month-cell\.is-holiday\s*\{[^}]*background-color~s',
            $css,
            'Feiertags-Hintergrund fehlt'
        );
        // Dark-Mode-Variante.
        self::assertMatchesRegularExpression(
            '~:root\[data-theme="dark"\]\s+\.cal-month-cell\.is-weekend~',
            $css,
            'Dark-Mode Weekend-Variante fehlt'
        );
    }

    public function testEntriesControllerPrefersEmployeeHolidayRegionOverSystemDefault(): void
    {
        // Felix' Bug: der Controller nutzte Holidays::currentRegion()
        // statt der User-spezifischen holiday_region. Fix: User-Region
        // zuerst, Fallback auf System.
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
        self::assertStringContainsString("EmployeeRepo::termsAt", $ctrl,
            'Controller muss employee_terms laden, um holiday_region zu bestimmen');
        self::assertStringContainsString("holiday_region", $ctrl,
            'User-spezifische holiday_region muss im Controller vorkommen');
        self::assertStringContainsString("Holidays::currentRegion()", $ctrl,
            'System-Region bleibt als Fallback stehen');
    }

    public function testBayernUserSeesEpiphanyAsHoliday(): void
    {
        // Direkt-Pruefung: 2026-01-06 ist in DE-BY ein gesetzlicher Feiertag.
        // Bei DE (bundesweit) nicht.
        TestSupport::bootInMemoryDb();
        self::assertNotNull(Holidays::on('DE-BY', '2026-01-06'),
            '6. Januar (Heilige Drei Koenige) MUSS in DE-BY ein Feiertag sein');
        self::assertNull(Holidays::on('DE', '2026-01-06'),
            '6. Januar ist in DE (bundesweit) kein Feiertag - die Union aus Region + Default muss also pro User erfolgen');
    }

    public function testMonthViewAlsoReceivesHolidayDataFromController(): void
    {
        // Felix' Beobachtung impliziert: Monatsansicht muss ebenfalls
        // Feiertage reinkommen (frueher nur week-Zweig hat $holidays
        // geladen). Controller-Vertrag: beim month-Span werden Holidays
        // ueber padStart..padEnd geladen.
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
        self::assertStringContainsString("\$calendarSpan === 'month'", $ctrl,
            'Monat-Zweig fuer Feiertags-Range muss existieren');
        self::assertStringContainsString('$padStart', $ctrl,
            'Monat-Range nutzt $padStart'
        );
        self::assertStringContainsString('$padEnd', $ctrl);
    }
}
