<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #170): Abwesenheiten desselben Users
 * duerfen sich nicht ueberlappen (keine zwei Urlaube am gleichen Tag,
 * keine Krankheit auf Urlaubstag drauf, etc.). AbsenceRepo::save()
 * wirft ein RuntimeException mit sprechender Meldung, wenn der neue
 * Bereich mit einem existierenden Bereich kollidiert.
 */
final class AbsenceOverlapTest extends TestCase
{
    private int $uid;
    private int $urlaubCatId;
    private int $krankheitCatId;

    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        $this->uid = TestSupport::seedUser();
        $this->urlaubCatId = AbsenceRepo::saveCategory([
            'ckey'     => 'urlaub',
            'label'    => 'Urlaub',
            'account'  => 'vacation',
            'position' => 10,
            'active'   => 1,
        ]);
        $this->krankheitCatId = AbsenceRepo::saveCategory([
            'ckey'     => 'krankheit',
            'label'    => 'Krankheit',
            'account'  => 'sick',
            'position' => 20,
            'active'   => 1,
        ]);
    }

    public function testSaveRejectsOverlapWithExistingAbsence(): void
    {
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-14',
            'note'        => null,
            'hours'       => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ueberlappt');
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-11',
            'end_date'    => '2026-11-11',
            'note'        => null,
            'hours'       => null,
        ]);
    }

    public function testSaveRejectsOverlapBetweenDifferentCategories(): void
    {
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-14',
            'note'        => null,
            'hours'       => null,
        ]);

        $this->expectException(RuntimeException::class);
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->krankheitCatId,
            'start_date'  => '2026-11-12',
            'end_date'    => '2026-11-15',
            'note'        => null,
            'hours'       => null,
        ]);
    }

    public function testSaveAllowsBackToBackRanges(): void
    {
        // 10.-12. und 13.-15. stossen aneinander - 12. und 13. ueberlappen
        // nicht, also OK.
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-12',
            'note'        => null,
            'hours'       => null,
        ]);
        $id = AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-13',
            'end_date'    => '2026-11-15',
            'note'        => null,
            'hours'       => null,
        ]);
        self::assertGreaterThan(0, $id);
    }

    public function testUpdateIgnoresSelfWhenCheckingOverlap(): void
    {
        $id = AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-14',
            'note'        => null,
            'hours'       => null,
        ]);
        // Dieselbe ID updaten mit leicht verschobenem Bereich - darf nicht
        // gegen sich selbst kollidieren.
        $newId = AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-11',
            'end_date'    => '2026-11-13',
            'note'        => null,
            'hours'       => null,
        ], $id);
        self::assertSame($id, $newId);
    }

    public function testDifferentUserDoesNotTriggerOverlap(): void
    {
        $otherUid = TestSupport::seedUser('tester-other');
        AbsenceRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-14',
            'note'        => null,
            'hours'       => null,
        ]);
        $id = AbsenceRepo::save([
            'user_id'     => $otherUid,
            'category_id' => $this->urlaubCatId,
            'start_date'  => '2026-11-10',
            'end_date'    => '2026-11-14',
            'note'        => null,
            'hours'       => null,
        ]);
        self::assertGreaterThan(0, $id);
    }

    public function testFindOverlapReturnsNullWhenClear(): void
    {
        self::assertNull(
            AbsenceRepo::findOverlap($this->uid, '2026-11-10', '2026-11-14')
        );
    }
}
