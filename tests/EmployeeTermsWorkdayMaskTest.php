<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * M1.1 #192: workday_mask pro Vertragsstand. HR/GL stellt pro Mitarbeiter
 * ein, welche Wochentage als Werktage zaehlen. Der Nutzer selbst darf das
 * NICHT - die Checkboxen liegen im Vertragsdaten-Partial, nicht im Profil.
 *
 * NULL-Mask faellt auf den globalen `working_days`-Default zurueck.
 */
final class EmployeeTermsWorkdayMaskTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        Workdays::override([1, 2, 3, 4, 5]); // global Mo-Fr
    }

    protected function tearDown(): void
    {
        Workdays::override(null);
    }

    public function testParseMaskNormalisesAndDedups(): void
    {
        self::assertSame([1, 2, 3, 4, 5], Workdays::parseMask('1,2,3,4,5'));
        self::assertSame([3, 4, 5, 6], Workdays::parseMask('6,5,4,3'));
        self::assertSame([2, 5], Workdays::parseMask('2,2,5,5,5'));
        self::assertNull(Workdays::parseMask(null));
        self::assertNull(Workdays::parseMask(''));
        self::assertNull(Workdays::parseMask('8,9,0,-1')); // alles out of range
    }

    public function testMaskToCsvFromPostArray(): void
    {
        self::assertSame('1,2,3,4,5', Workdays::maskToCsv([1, 2, 3, 4, 5]));
        self::assertSame('3,4,5,6', Workdays::maskToCsv(['6', 5, '4', 3]));
        self::assertNull(Workdays::maskToCsv([]));
        self::assertNull(Workdays::maskToCsv([0, 8]));
    }

    public function testSaveTermsPersistsMaskAsCsv(): void
    {
        $uid = TestSupport::seedUser();
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null, [3, 4, 5, 6]);
        $row = DB::one('SELECT * FROM employee_terms WHERE user_id = ?', [$uid]);
        self::assertSame('3,4,5,6', (string) $row['workday_mask']);
    }

    public function testSaveTermsNullMaskStoresNullForGlobalFallback(): void
    {
        $uid = TestSupport::seedUser();
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null, null);
        $row = DB::one('SELECT workday_mask FROM employee_terms WHERE user_id = ?', [$uid]);
        self::assertNull($row['workday_mask']);
    }

    public function testDailyTargetUsesUserMaskPerWeek(): void
    {
        // Mitarbeiter arbeitet Mi-Sa (4 Tage), 40h/Woche -> 10h/Tag.
        $terms = ['weekly_hours' => 40.0, 'workday_mask' => '3,4,5,6'];
        self::assertEqualsWithDelta(10.0, EmployeeRepo::dailyTarget($terms), 0.001);

        // Ohne Mask (NULL) -> globaler Default Mo-Fr (5 Tage) -> 8h/Tag.
        $terms2 = ['weekly_hours' => 40.0, 'workday_mask' => null];
        self::assertEqualsWithDelta(8.0, EmployeeRepo::dailyTarget($terms2), 0.001);
    }

    public function testDailyTargetMinutesRespectsUserMaskNotGlobal(): void
    {
        $uid = TestSupport::seedUser();
        // User arbeitet Mi-Sa, Mo zaehlt bei IHM als frei - auch wenn global Mo-Fr.
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null, [3, 4, 5, 6]);

        // 2026-11-16 ist Montag (ISO 1) -> fuer DIESEN User frei.
        self::assertSame(0, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-16'));
        // 2026-11-18 ist Mittwoch (ISO 3) -> Werktag, 40/4*60 = 600 min.
        self::assertSame(600, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-18'));
        // 2026-11-21 ist Samstag (ISO 6) -> bei DIESEM User Werktag.
        self::assertSame(600, EmployeeRepo::dailyTargetMinutes($uid, '2026-11-21'));
    }

    public function testWorkdaysForUserFallsBackToGlobalWhenMaskNull(): void
    {
        $uid = TestSupport::seedUser();
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null, null);
        // Mit NULL-Mask verhaelt sich User wie global (Mo-Fr).
        self::assertTrue(Workdays::isWorkdayForUser($uid, '2026-11-16')); // Mo
        self::assertFalse(Workdays::isWorkdayForUser($uid, '2026-11-21')); // Sa
    }

    public function testWorkdaysForUserRespectsMask(): void
    {
        $uid = TestSupport::seedUser();
        EmployeeRepo::saveTerms($uid, '2026-01-01', EmployeeRepo::TYPE_EMPLOYEE, 40.0, 25.0, null, [3, 4, 5, 6]);
        self::assertFalse(Workdays::isWorkdayForUser($uid, '2026-11-16')); // Mo nicht
        self::assertTrue(Workdays::isWorkdayForUser($uid, '2026-11-18')); // Mi
        self::assertTrue(Workdays::isWorkdayForUser($uid, '2026-11-21')); // Sa
    }

    public function testPartialRendersWorkdayCheckboxes(): void
    {
        $partial = (string) file_get_contents(dirname(__DIR__) . '/views/partials/forms/employee_terms.php');
        self::assertStringContainsString('name="workday_mask[]"', $partial,
            'Vertragsdaten-Partial muss Werktage-Checkboxen rendern');
        self::assertStringContainsString("t('employee.form_workdays'", $partial,
            'Label muss ueber i18n-Key laufen');
        self::assertStringContainsString("t('employee.col_workdays'", $partial,
            'Historien-Spalte Werktage muss i18n-Key nutzen');
    }

    public function testProfileFormDoesNotExposeWorkdayMask(): void
    {
        // Felix-Regel: Werktage gehoeren in Vertragsdaten (HR/GL), NICHT ins Benutzermenue.
        $profile = (string) file_get_contents(dirname(__DIR__) . '/views/profile/index.php');
        self::assertStringNotContainsString('workday_mask', $profile,
            'profile/index.php darf keine Werktage-Einstellung enthalten - gehoert in Vertragsdaten.');
    }

    public function testControllerSaveReadsWorkdayMaskFromPost(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/admin_ctrl.php');
        self::assertStringContainsString("workday_mask", $ctrl,
            'ctrl_employee_terms_save muss workday_mask aus POST lesen und an saveTerms uebergeben');
    }
}
