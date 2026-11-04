<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Overlap-Guard fuer Zeitbuchungen und Prozesse (portiert aus Pro-PR #444).
 *
 * Regel: eine Person hat zu jedem Zeitpunkt genau EINE Buchung. Zeitbuchungen
 * und interne Prozesse duerfen sich nicht ueberlappen (Aneinanderstossen ist
 * erlaubt); ein laufender Timer zaehlt bis jetzt. Externe Kalendertermine sind
 * keine Buchungen und duerfen parallel liegen - Abwesenheiten sind tageweise
 * und hier nicht betroffen.
 *
 * Die 8h-Berechnung war in Pro schon korrekt; falsch wurde sie nur durch
 * parallele Buchungen (die zweifach in die Summe eingingen). Dieser Guard
 * schliesst die Ursache aus.
 */
final class BookingOverlapTest extends TestCase
{
    private int $uid;
    private int $taskId;
    private int $procCatId;

    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();

        $this->uid = TestSupport::seedUser();

        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']);
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, created_at) VALUES (?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), '2026-01-01 00:00:00']
        );
        // Default WP + Task fuer Zeitbuchungen.
        $wpid = DB::insert(
            'INSERT INTO work_packages (project_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$pid, 'Default', 'default-' . uniqid(), 1, '2026-01-01 00:00:00']
        );
        $this->taskId = DB::insert(
            'INSERT INTO tasks (project_id, work_package_id, name, kind, active, created_at) VALUES (?,?,?,?,?,?)',
            [$pid, $wpid, 'Dev', 'billable', 1, '2026-01-01 00:00:00']
        );
        $this->procCatId = DB::insert(
            'INSERT INTO process_categories (ckey, label, active, created_at) VALUES (?,?,?,?)',
            ['meeting-' . uniqid(), 'Meeting', 1, '2026-01-01 00:00:00']
        );
    }

    public function testNoConflictOnEmptyDay(): void
    {
        $c = Repo::conflictingBooking($this->uid, '2026-11-04 09:00:00', '2026-11-04 10:00:00');
        self::assertNull($c);
    }

    public function testBackToBackIsAllowed(): void
    {
        Repo::createEntry($this->uid, $this->taskId, '2026-11-04 09:00:00', '2026-11-04 10:00:00', 'A');
        // Aneinanderstossen ist erlaubt: 10:00-11:00 beginnt genau am Ende der ersten Buchung.
        self::assertNull(Repo::bookingConflict($this->uid, '2026-11-04 10:00:00', '2026-11-04 11:00:00'));
    }

    public function testOverlappingTimeEntryIsRejected(): void
    {
        Repo::createEntry($this->uid, $this->taskId, '2026-11-04 09:00:00', '2026-11-04 11:00:00', 'A');
        $msg = Repo::bookingConflict($this->uid, '2026-11-04 10:00:00', '2026-11-04 12:00:00');
        self::assertIsString($msg);
        self::assertStringContainsString('09:00', $msg);
        self::assertStringContainsString('11:00', $msg);
    }

    public function testProcessOverlappingTimeEntryIsRejected(): void
    {
        Repo::createEntry($this->uid, $this->taskId, '2026-11-04 09:00:00', '2026-11-04 11:00:00', 'A');
        $this->expectException(RuntimeException::class);
        ProcessRepo::save([
            'user_id'     => $this->uid,
            'category_id' => $this->procCatId,
            'start_ts'    => '2026-11-04 10:00:00',
            'end_ts'      => '2026-11-04 12:00:00',
            'note'        => 'meeting',
        ]);
    }

    public function testEditingOwnEntryDoesNotSelfConflict(): void
    {
        $id = Repo::createEntry($this->uid, $this->taskId, '2026-11-04 09:00:00', '2026-11-04 10:00:00', 'A');
        // Bearbeiten derselben Buchung mit demselben Zeitfenster darf nicht gegen sich selbst konfliktieren.
        self::assertNull(Repo::bookingConflict($this->uid, '2026-11-04 09:00:00', '2026-11-04 10:00:00', $id));
    }

    public function testDifferentUserDoesNotConflict(): void
    {
        $otherUid = TestSupport::seedUser('other-' . uniqid());
        Repo::createEntry($this->uid, $this->taskId, '2026-11-04 09:00:00', '2026-11-04 11:00:00', 'A');
        // Andere User haben eigene Buchungen - kein Konflikt zwischen Personen.
        self::assertNull(Repo::bookingConflict($otherUid, '2026-11-04 10:00:00', '2026-11-04 12:00:00'));
    }

    public function testRunningTimerCountsAsOngoingUntilNow(): void
    {
        // Ein laufender Timer mit end_ts=NULL muss gegen spaeteren Buchungen
        // greifen, die VOR "now" liegen - in-vitro simulieren wir "laeuft seit heute".
        $start = (new DateTimeImmutable('today'))->format('Y-m-d') . ' 00:00:00';
        $later = (new DateTimeImmutable('today'))->format('Y-m-d') . ' 12:00:00';
        DB::insert(
            'INSERT INTO time_entries (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$this->uid, $this->taskId, 1, 1, $start, null, null, 'running', 'timer', $start, $start]
        );
        // Eine Buchung mitten in der laufenden Timer-Zeit kollidiert.
        $msg = Repo::bookingConflict($this->uid, $later, $later);
        self::assertIsString($msg);
    }

    public function testStopTimerClipsEndAtLaterBookingStart(): void
    {
        // Timer laeuft seit 09:00; ein Kollege hat auf diesem User 10:00-11:00 eingetragen.
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $running = DB::insert(
            'INSERT INTO time_entries (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$this->uid, $this->taskId, 1, 1, $today . ' 09:00:00', null, null, 'running', 'timer', $today . ' 09:00:00', $today . ' 09:00:00']
        );
        Repo::createEntry($this->uid, $this->taskId, $today . ' 10:00:00', $today . ' 11:00:00', 'manual');
        Repo::stopTimer($this->uid);
        $row = DB::one('SELECT end_ts FROM time_entries WHERE id = ?', [$running]);
        self::assertSame($today . ' 10:00:00', (string) $row['end_ts'], 'Timer muss am Beginn der spaeter eingetragenen Buchung enden - keine Parallelzaehlung');
    }
}
