<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-08 (#101/#102/#104):
 *   - Team-Stimmung gilt NUR fuer Team-Mitglieder (abgeleitet aus
 *     plan_allocations). Das ist der v1.0-Default; eine eigene Teams-Tabelle
 *     ist fuer Milestone 1.1 diskutierbar.
 *   - Fuer die Mood-Matrix bekommen wir pro User eine Zeile und pro ISO-Woche
 *     eine Spalte mit dem Durchschnitt beider Dimensionen; der Wochenanfang
 *     folgt CalendarLocale (heute Montag, spaeter konfigurierbar).
 *
 * Diese Tests frieren die Scope-Semantik ein, damit die Matrix spaeter nicht
 * unbemerkt Daten von Dana (ohne Allocation) oder aus falschen Wochen zieht.
 */
final class TeamSentimentTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    private function seedUserWithAllocation(string $username, ?int $projectId = null): int
    {
        $uid = TestSupport::seedUser($username);
        if ($projectId !== null) {
            DB::run(
                'INSERT INTO plan_allocations (project_id, user_id, period_kind, planned_minutes, created_at) VALUES (?,?,?,?,?)',
                [$projectId, $uid, 'total', 0, date('Y-m-d H:i:s')]
            );
        }
        return $uid;
    }

    private function seedProject(): int
    {
        $cid = DB::insert(
            'INSERT INTO clients (name, code, active, created_at) VALUES (?,?,?,?)',
            ['Testkunde', 'TST', 1, date('Y-m-d H:i:s')]
        );
        return DB::insert(
            'INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$cid, 'Testprojekt', 'PRJ', 1, date('Y-m-d H:i:s')]
        );
    }

    public function testTeamMemberIdsAreOnlyUsersWithPlanAllocation(): void
    {
        $pid = $this->seedProject();
        $alice   = $this->seedUserWithAllocation('alice',   $pid);
        $bob     = $this->seedUserWithAllocation('bob',     $pid);
        $charlie = $this->seedUserWithAllocation('charlie', $pid);
        $dana    = $this->seedUserWithAllocation('dana',    null); // keine Allocation

        $ids = Sentiment::teamMemberIds();
        self::assertContains($alice,   $ids);
        self::assertContains($bob,     $ids);
        self::assertContains($charlie, $ids);
        self::assertNotContains($dana, $ids,
            'User ohne plan_allocation sind NICHT Teil des Teams (Felix-Default v1.0)');
    }

    public function testUserLabelsFallBackToUsernameWhenDisplayNameEmpty(): void
    {
        $pid = $this->seedProject();
        $alice = $this->seedUserWithAllocation('alice', $pid);
        DB::run('UPDATE users SET display_name = ? WHERE id = ?', ['', $alice]);
        $labels = Sentiment::userLabels([$alice]);
        self::assertCount(1, $labels);
        self::assertSame('alice', $labels[0]['label']);
    }

    public function testUserLabelsEmptyForEmptyInput(): void
    {
        self::assertSame([], Sentiment::userLabels([]));
    }

    public function testWeeklyMoodMatrixReturnsOneRowPerUserAndOneColumnPerWeek(): void
    {
        $pid = $this->seedProject();
        $alice = $this->seedUserWithAllocation('alice', $pid);
        $bob   = $this->seedUserWithAllocation('bob',   $pid);

        $m = Sentiment::weeklyMoodMatrix([$alice, $bob], 4);
        self::assertArrayHasKey($alice, $m);
        self::assertArrayHasKey($bob,   $m);
        // 4 Wochen fenster = 4 Spalten pro User.
        self::assertCount(4, $m[$alice]);
        self::assertCount(4, $m[$bob]);
        foreach ($m[$alice] as $wk => $cell) {
            self::assertNull($cell['mood'], "Woche $wk ohne Daten muss mood=null liefern");
            self::assertNull($cell['flow']);
        }
    }

    public function testWeeklyMoodMatrixAveragesPerWeekAndIgnoresOutOfScope(): void
    {
        $pid = $this->seedProject();
        $alice = $this->seedUserWithAllocation('alice', $pid);
        $other = TestSupport::seedUser('outsider'); // ohne Allocation

        // Datum fuer Alice: diese Woche Mo 3+5 Mood -> Avg 4.
        $monday = (new DateTimeImmutable('today'))->modify('monday this week')->format('Y-m-d');
        $tuesday = (new DateTimeImmutable('today'))->modify('monday this week')->modify('+1 day')->format('Y-m-d');
        Sentiment::record($alice, $monday,  3, null, null);
        Sentiment::record($alice, $tuesday, 5, 2,    null);

        // Outsider: trotz Daten nicht in der Matrix, weil $userIds ihn nicht enthaelt.
        Sentiment::record($other, $monday, 1, 1, null);

        $m = Sentiment::weeklyMoodMatrix([$alice], 2);
        self::assertArrayNotHasKey($other, $m);
        $wkMonday = CalendarLocale::weekStart(new DateTimeImmutable($monday))->format('Y-m-d');
        self::assertEqualsWithDelta(4.0, $m[$alice][$wkMonday]['mood'], 0.01,
            'Alices Durchschnitt-Mood der aktuellen Woche = (3+5)/2 = 4.0');
        self::assertEqualsWithDelta(2.0, $m[$alice][$wkMonday]['flow'], 0.01,
            'Alices Durchschnitt-Flow = 2.0 (nur ein Eintrag mit Wert 2)');
    }

    public function testWeeklyMoodMatrixUsesCalendarLocaleWeekAnchor(): void
    {
        // Wenn Admin den Wochenanfang auf Sonntag (ISO 7) setzt, fallen
        // Samstag-Daten in dieselbe Woche wie die darauffolgenden Tage.
        Settings::set('calendar.week_start_dow', '7');
        $pid = $this->seedProject();
        $alice = $this->seedUserWithAllocation('alice', $pid);

        $satBeforeToday = (new DateTimeImmutable('today'))->modify('last saturday')->format('Y-m-d');
        Sentiment::record($alice, $satBeforeToday, 4, null, null);

        $m = Sentiment::weeklyMoodMatrix([$alice], 2);
        $satWeekStart = CalendarLocale::weekStart(new DateTimeImmutable($satBeforeToday))->format('Y-m-d');
        self::assertArrayHasKey($satWeekStart, $m[$alice],
            'Samstag-Daten muessen zur Sonntag-Startwoche gezaehlt werden, nicht zur Montag-Woche');
    }
}
