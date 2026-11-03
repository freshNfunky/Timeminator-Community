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
}
