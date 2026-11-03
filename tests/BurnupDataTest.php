<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressions-Smoke fuer die Burn-Up-Payload (v1.0 F2.4).
 *
 * Haelt die Shape von `cockpit_burnup_payload()` fest: Ist-Linie, target_h,
 * planned_end und die Fortschritts-/Prognose-Marker aus project_trackers
 * (progress). Der Chart in views/cockpit/project.php rendert aus genau
 * diesen Keys die zweite %-Achse, die Marker-Punkte und die Deadline-vline;
 * eine Regression hier macht die Analyse-Features im Chart stumm.
 */
final class BurnupDataTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
    }

    private function seedProject(?float $targetHours = 100.0, ?string $plannedEnd = '2026-12-31'): int
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']);
        return DB::insert(
            'INSERT INTO projects (client_id, name, code, target_hours, planned_end, created_at) VALUES (?,?,?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), $targetHours, $plannedEnd, '2026-01-01 00:00:00']
        );
    }

    public function testUnknownProjectReturnsNotOk(): void
    {
        $p = cockpit_burnup_payload(999999);
        self::assertFalse($p['ok']);
        self::assertSame('not_found', $p['error']);
    }

    public function testPayloadShapeKeysArePresent(): void
    {
        $pid = $this->seedProject();
        $p = cockpit_burnup_payload($pid);
        self::assertTrue($p['ok']);
        self::assertArrayHasKey('points',      $p);
        self::assertArrayHasKey('target_h',    $p);
        self::assertArrayHasKey('markers',     $p);
        self::assertArrayHasKey('planned_end', $p);
    }

    public function testPlannedEndAndTargetArePropagated(): void
    {
        $pid = $this->seedProject(250.0, '2026-11-15');
        $p = cockpit_burnup_payload($pid);
        self::assertSame(250.0, $p['target_h']);
        self::assertSame('2026-11-15', $p['planned_end']);
    }

    public function testProgressTrackerBecomesMarkerWithPercentAndForecast(): void
    {
        $pid = $this->seedProject();
        Repo::saveProjectTracker($pid, 'progress', 'Erste Meldung', [
            'value'          => 42.0,
            'value_forecast' => 75.0,
            'date_value'     => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertCount(1, $p['markers']);
        $m = $p['markers'][0];
        self::assertSame('2026-05-01', $m['d']);
        self::assertSame(42.0, $m['p']);
        self::assertSame(75.0, $m['f']);
    }

    public function testProgressWithoutForecastHasNullF(): void
    {
        $pid = $this->seedProject();
        Repo::saveProjectTracker($pid, 'progress', 'ohne Prognose', [
            'value'      => 30.0,
            'date_value' => '2026-04-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertCount(1, $p['markers']);
        self::assertNull($p['markers'][0]['f']);
        self::assertSame(30.0, $p['markers'][0]['p']);
    }

    public function testMarkersAreOrderedByCreatedAt(): void
    {
        $pid = $this->seedProject();
        Repo::saveProjectTracker($pid, 'progress', 'erste', [
            'value' => 20.0, 'date_value' => '2026-02-01',
        ], null);
        Repo::saveProjectTracker($pid, 'progress', 'zweite', [
            'value' => 50.0, 'date_value' => '2026-05-01',
        ], null);
        Repo::saveProjectTracker($pid, 'progress', 'dritte', [
            'value' => 80.0, 'date_value' => '2026-09-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertSame([20.0, 50.0, 80.0], array_column($p['markers'], 'p'));
    }

    public function testStatusReportTrackerDoesNotProduceMarker(): void
    {
        $pid = $this->seedProject();
        Repo::saveProjectTracker($pid, 'status_report', 'nur Status', [
            'status' => 'green', 'date_value' => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertSame([], $p['markers']);
    }

    public function testProjectWithoutPlannedEndSerializesToNull(): void
    {
        $pid = $this->seedProject(100.0, null);
        $p = cockpit_burnup_payload($pid);
        self::assertNull($p['planned_end']);
    }

    public function testProjectWithoutTargetHoursSerializesToNull(): void
    {
        $pid = $this->seedProject(null, '2026-12-31');
        $p = cockpit_burnup_payload($pid);
        self::assertNull($p['target_h']);
    }

    // --- Analyse-Overlay: Fortschritts-Linie + Marker + Forecast-Strahlen -----
    // Diese Tests halten den Shape fest, der im View zu Linie/Punkte/Strahlen wird.
    // Fallen diese Keys oder deren Semantik, verschwindet das Overlay im Chart.

    public function testAnalyseKeysArePresent(): void
    {
        $pid = $this->seedProject();
        $p = cockpit_burnup_payload($pid);
        self::assertArrayHasKey('progress_line',    $p);
        self::assertArrayHasKey('progress_markers', $p);
        self::assertArrayHasKey('forecast_rays',    $p);
        self::assertArrayHasKey('review_interval',  $p);
    }

    public function testReviewIntervalDefaultsTo30(): void
    {
        $pid = $this->seedProject();
        $p = cockpit_burnup_payload($pid);
        self::assertSame(COCKPIT_REVIEW_INTERVAL_DEFAULT_DAYS, $p['review_interval']);
    }

    public function testReviewIntervalProjectOverrideWins(): void
    {
        $pid = $this->seedProject();
        Repo::setProjectReviewIntervalDays($pid, 14);
        $p = cockpit_burnup_payload($pid);
        self::assertSame(14, $p['review_interval']);
    }

    public function testProgressLineIsLengthOfPointsAndStepForwardFilled(): void
    {
        // Burn-Up points sind die distinct Tage ab erster Buchung; damit die
        // Linie step-forward getestet werden kann, buchen wir drei Tage und
        // melden Fortschritt am mittleren Tag.
        $pid = $this->seedProject();
        $this->seedBookingDay($pid, '2026-05-01', 60);
        $this->seedBookingDay($pid, '2026-05-02', 60);
        $this->seedBookingDay($pid, '2026-05-03', 60);
        Repo::saveProjectTracker($pid, 'progress', 'erste', [
            'value' => 50.0, 'date_value' => '2026-05-02',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertCount(count($p['points']), $p['progress_line']);
        self::assertNull($p['progress_line'][0]);                // vor der Meldung: leer
        self::assertSame(50.0, $p['progress_line'][1]);          // ab Meldetermin
        self::assertSame(50.0, $p['progress_line'][2]);          // step-forward bis zum Ende
    }

    public function testProgressMarkersSitOnReportedPercent(): void
    {
        $pid = $this->seedProject();
        $this->seedBookingDay($pid, '2026-04-01', 30);
        $this->seedBookingDay($pid, '2026-05-01', 30);
        Repo::saveProjectTracker($pid, 'progress', 'Meldung', [
            'value' => 42.0, 'date_value' => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertCount(1, $p['progress_markers']);
        self::assertSame(42.0, $p['progress_markers'][0]['y']);
    }

    public function testForecastRayUsesReviewInterval(): void
    {
        $pid = $this->seedProject();
        Repo::setProjectReviewIntervalDays($pid, 30);
        // Buchungstage so, dass as_of und as_of+30d jeweils einen Burn-Up-xIndex haben.
        $this->seedBookingDay($pid, '2026-05-01', 30);
        $this->seedBookingDay($pid, '2026-05-31', 30);
        $this->seedBookingDay($pid, '2026-06-30', 30);
        Repo::saveProjectTracker($pid, 'progress', 'mit Forecast', [
            'value' => 40.0, 'value_forecast' => 70.0, 'date_value' => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertCount(1, $p['forecast_rays']);
        $r = $p['forecast_rays'][0];
        self::assertSame(40.0, $r['y0']);
        self::assertSame(70.0, $r['y1']);
        self::assertLessThan($r['x1'], $r['x0']);  // Strahl geht nach rechts
    }

    public function testProgressWithoutForecastProducesNoRay(): void
    {
        $pid = $this->seedProject();
        $this->seedBookingDay($pid, '2026-05-01', 30);
        Repo::saveProjectTracker($pid, 'progress', 'nur Fortschritt', [
            'value' => 50.0, 'date_value' => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertSame([], $p['forecast_rays']);
        self::assertCount(1, $p['progress_markers']);
    }

    public function testIdealLineAndDeadlineKeysPresent(): void
    {
        $pid = $this->seedProject();
        $p = cockpit_burnup_payload($pid);
        self::assertArrayHasKey('ideal_line', $p);
        self::assertArrayHasKey('deadline',   $p);
        self::assertArrayHasKey('delay',      $p);
    }

    public function testIdealLineIsLinearFrom0To100BetweenStartAndEnd(): void
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, planned_start, planned_end, target_hours, created_at) VALUES (?,?,?,?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), '2026-01-01', '2026-12-31', 100.0, '2026-01-01 00:00:00']
        );
        $this->seedBookingDay($pid, '2026-01-01', 60);
        $this->seedBookingDay($pid, '2026-07-01', 60);
        $this->seedBookingDay($pid, '2026-12-31', 60);
        $p = cockpit_burnup_payload($pid);
        // Burnup clippt an today(); Linearitaets-Beweis haengen wir an die
        // Form: start=0%, monoton aufsteigend, exakt lineare Verteilung.
        self::assertSame(0.0, $p['ideal_line'][0]);
        $prev = -1.0;
        foreach ($p['ideal_line'] as $v) {
            if ($v === null) continue;
            self::assertGreaterThanOrEqual($prev, $v);
            $prev = $v;
        }
        // Erwarteter Wert = 100 * verstrichene_Tage / span_Tage; wir pruefen,
        // dass der tatsaechliche Wert diesem Erwartungswert ±0.5pp entspricht.
        $spanDays = (strtotime('2026-12-31') - strtotime('2026-01-01')) / 86400;
        foreach ([0, 10, 50, 100, (int) floor(count($p['ideal_line']) / 2)] as $i) {
            if (!isset($p['ideal_line'][$i]) || $p['ideal_line'][$i] === null) continue;
            $expected = round(100.0 * $i / $spanDays, 2);
            self::assertEqualsWithDelta($expected, $p['ideal_line'][$i], 0.5);
        }
    }

    public function testLabelsExtendToPlannedEndSoIdealReaches100AtDeadline(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03): die Burn-Up-Labels
        // endeten immer bei "today", die Ideal-Linie konnte deshalb nie ihre
        // 100 %-Marke erreichen und die Deadline-vline klemmte am rechten Rand.
        // Fix: Labels werden bis zum spaetesten relevanten Datum (planned_end,
        // Prognose-Ray-Enden) ausgedehnt - h bleibt ab "today" null.
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        $future = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, planned_end, target_hours, created_at) VALUES (?,?,?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), $future, 100.0, '2026-01-01 00:00:00']
        );
        // Eine Buchung, damit projectBurnup Labels generiert.
        $this->seedBookingDay($pid, (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d'), 60);
        $p = cockpit_burnup_payload($pid);
        $lastLabel = $p['points'][count($p['points']) - 1]['d'];
        self::assertSame($future, $lastLabel, 'Letztes Label muss das planned_end sein, damit Deadline/Ideal am rechten Rand ankommen');
        // Die Ist-Linie endet frueher: Punkte in der Zukunft tragen h = null.
        $future1 = (new DateTimeImmutable('today'))->modify('+10 days')->format('Y-m-d');
        $futurePoint = null;
        foreach ($p['points'] as $pt) if ($pt['d'] === $future1) { $futurePoint = $pt; break; }
        self::assertNotNull($futurePoint);
        self::assertNull($futurePoint['h'], 'Ist-Stunden-Reihe bleibt in der Zukunft null (keine Extrapolation)');
        // Ideal-Linie trifft genau am Deadline-Datum 100 %.
        $lastIdeal = $p['ideal_line'][count($p['ideal_line']) - 1];
        self::assertSame(100.0, $lastIdeal, 'Ideal-Linie muss am planned_end genau 100 % erreichen');
        // Erstes Label = Ideal startet bei 0 %.
        self::assertSame(0.0, $p['ideal_line'][0], 'Ideal-Linie muss am ersten Label bei 0 % starten');
    }

    public function testIdealStartsAtFirstLabelNotKickoff(): void
    {
        // Felix-Feedback 2026-11-03: die Ideal-Linie beginnt am sichtbaren
        // Projektstart (= labels[0] = erster gebuchter Tag), nicht am
        // kickoff_at - sonst startet die Linie mitten im Chart, wenn kickoff
        // nachtraeglich auf ein spaeteres Datum gesetzt wurde.
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        $firstBookingDay = '2026-03-01';
        $lateKickoff     = '2026-08-01';
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, kickoff_at, planned_end, target_hours, created_at) VALUES (?,?,?,?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), $lateKickoff, '2026-12-31', 100.0, '2026-01-01 00:00:00']
        );
        $this->seedBookingDay($pid, $firstBookingDay, 60);
        $p = cockpit_burnup_payload($pid);
        self::assertSame(0.0, $p['ideal_line'][0], 'Ideal startet bei 0 % am ersten Label (nicht erst am Kickoff-Datum)');
        self::assertSame($firstBookingDay, $p['points'][0]['d']);
    }

    public function testDeadlineEqualsCurrentPlannedEndWhenNoShift(): void
    {
        $pid = $this->seedProject(100.0, '2026-11-15');
        $p = cockpit_burnup_payload($pid);
        self::assertSame('2026-11-15', $p['deadline']['date']);
        self::assertNull($p['delay']);
    }

    public function testDeadlineKeepsOriginalAndDelayCarriesCurrentAfterShift(): void
    {
        $pid = $this->seedProject(100.0, '2026-11-15');
        // Simuliert eine ueber UI eingetragene Terminverschiebung.
        DateShiftRepo::logIfShifted(
            DateShiftRepo::KIND_PROJECT, $pid, $pid, '2026-11-15', '2026-12-15', 'API-Delay'
        );
        Repo::setProjectPlannedEnd($pid, '2026-12-15');
        $p = cockpit_burnup_payload($pid);
        self::assertSame('2026-11-15', $p['deadline']['date']);
        self::assertNotNull($p['delay']);
        self::assertSame('2026-12-15', $p['delay']['date']);
    }

    public function testStatusReportTrackerDoesNotProduceLineOrRays(): void
    {
        $pid = $this->seedProject();
        $this->seedBookingDay($pid, '2026-05-01', 30);
        Repo::saveProjectTracker($pid, 'status_report', 'nur Status', [
            'status' => 'green', 'date_value' => '2026-05-01',
        ], null);
        $p = cockpit_burnup_payload($pid);
        self::assertSame([], $p['progress_markers']);
        self::assertSame([], $p['forecast_rays']);
        foreach ($p['progress_line'] as $v) {
            self::assertNull($v);
        }
    }

    /**
     * Minimaler Buchungs-Seed: ein Task + einen Entry am Tag $ymd mit
     * $minutes Dauer, damit Repo::projectBurnup() diesen Tag enthaelt.
     */
    private int $seedUid = 0;
    private array $taskByProject = [];

    private function seedBookingDay(int $projectId, string $ymd, int $minutes): void
    {
        if (!$this->seedUid) {
            $this->seedUid = TestSupport::seedUser('tester-' . uniqid());
        }
        $uid = $this->seedUid;
        $taskByProject = &$this->taskByProject;
        if (!isset($taskByProject[$projectId])) {
            $clientId = (int) DB::scalar('SELECT client_id FROM projects WHERE id = ?', [$projectId]);
            $wp = DB::insert(
                'INSERT INTO work_packages (project_id, code, name, created_at) VALUES (?,?,?,?)',
                [$projectId, 'wp-' . uniqid(), 'WP', '2026-01-01 00:00:00']
            );
            $taskByProject[$projectId] = [
                'task'    => DB::insert(
                    'INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,?,?,?)',
                    [$projectId, $wp, 'T', '2026-01-01 00:00:00']
                ),
                'wp'      => $wp,
                'client'  => $clientId,
            ];
        }
        $start = $ymd . ' 09:00:00';
        $end   = (new DateTimeImmutable($start))->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
        $t = $taskByProject[$projectId];
        DB::insert(
            'INSERT INTO time_entries
             (user_id, task_id, work_package_id, project_id, client_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$uid, $t['task'], $t['wp'], $projectId, $t['client'], $start, $end, $minutes,
             '2026-01-01 00:00:00', '2026-01-01 00:00:00']
        );
    }
}
