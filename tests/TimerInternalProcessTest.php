<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #186): Der Dashboard-Timer kann jetzt
 * auch Interne Prozesse starten/stoppen. process_entries bekommt
 * nullable end_ts + duration_min (Migration 2026-11-09-05), und
 * ProcessRepo hat startTimer/stopTimer/running analog Repo.
 */
final class TimerInternalProcessTest extends TestCase
{
    private int $uid;
    private int $categoryId;

    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        $this->uid = TestSupport::seedUser();
        $this->categoryId = ProcessRepo::saveCategory([
            'ckey'     => 'admin',
            'label'    => 'Admin',
            'position' => 10,
            'active'   => 1,
        ]);
    }

    public function testStartTimerCreatesRowWithNullEndTs(): void
    {
        $id = ProcessRepo::startTimer($this->uid, $this->categoryId, 'Testlauf');
        self::assertGreaterThan(0, $id);
        $row = DB::one('SELECT * FROM process_entries WHERE id = ?', [$id]);
        self::assertSame((int) $row['user_id'], $this->uid);
        self::assertSame((int) $row['category_id'], $this->categoryId);
        self::assertNotNull($row['start_ts']);
        self::assertNull($row['end_ts']);
        self::assertNull($row['duration_min']);
        self::assertSame('Testlauf', (string) $row['note']);
    }

    public function testRunningReturnsOpenEntry(): void
    {
        ProcessRepo::startTimer($this->uid, $this->categoryId);
        $running = ProcessRepo::running($this->uid);
        self::assertNotNull($running);
        self::assertSame($this->categoryId, (int) $running['category_id']);
        self::assertSame('Admin', (string) $running['category_label']);
        self::assertSame('admin', (string) $running['category_ckey']);
    }

    public function testStopTimerSetsEndTsAndDuration(): void
    {
        $id = ProcessRepo::startTimer($this->uid, $this->categoryId);
        // Simuliere verstrichene Zeit durch direktes DB-Update auf start_ts.
        DB::run(
            'UPDATE process_entries SET start_ts = ? WHERE id = ?',
            [(new DateTimeImmutable('-30 minutes'))->format('Y-m-d H:i:s'), $id]
        );
        ProcessRepo::stopTimer($this->uid);
        $row = DB::one('SELECT * FROM process_entries WHERE id = ?', [$id]);
        self::assertNotNull($row['end_ts']);
        self::assertNotNull($row['duration_min']);
        self::assertGreaterThanOrEqual(29, (int) $row['duration_min']);
        self::assertLessThanOrEqual(31, (int) $row['duration_min']);
        // running() findet den Eintrag jetzt nicht mehr.
        self::assertNull(ProcessRepo::running($this->uid));
    }

    public function testStopTimerNoopWhenNothingRunning(): void
    {
        ProcessRepo::stopTimer($this->uid); // darf nicht crashen
        self::assertNull(ProcessRepo::running($this->uid));
    }

    public function testDashboardControllerPassesProcessCategoriesToView(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/dashboard_ctrl.php');
        self::assertStringContainsString('ProcessRepo::categories(true)', $ctrl,
            'dashboard_ctrl muss processCategories an die View liefern');
        self::assertStringContainsString("'processCategories'", $ctrl,
            'processCategories muss im view()-Call auftauchen');
        self::assertStringContainsString('ProcessRepo::running', $ctrl,
            'dashboard_ctrl muss den laufenden Prozess-Timer abfragen');
    }

    public function testTimerStartControllerBranchesOnProcessOrTask(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
        self::assertStringContainsString("post_int('process_category_id')", $ctrl,
            'ctrl_timer_start muss process_category_id aus POST lesen');
        self::assertStringContainsString('ProcessRepo::startTimer', $ctrl,
            'ctrl_timer_start muss ProcessRepo::startTimer bei process_category_id aufrufen');
        self::assertStringContainsString('ProcessRepo::stopTimer', $ctrl,
            'ctrl_timer_stop muss auch ProcessRepo::stopTimer rufen');
    }

    public function testDashboardViewHasProcessOptgroupAndHiddenInput(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/dashboard.php');
        self::assertStringContainsString("data-kind=\"process\"", $view,
            'Prozess-Optionen muessen data-kind=process tragen');
        self::assertStringContainsString('name="process_category_id"', $view,
            'Hidden Input process_category_id muss im Form liegen');
        self::assertStringContainsString("kind === 'process'", $view,
            'Script muss den process-Branch erkennen');
    }

    public function testTaskPickerHiddenRuleOverridesGridDisplay(): void
    {
        // Felix-Feedback 2026-11-09 Hardening (#186): .task-picker{display:grid}
        // und .tp-stack{display:flex} ueberschreiben das Browser-default
        // [hidden]{display:none}. Ohne explizite Regel wuerde der Task-Picker
        // bei Prozess-Auswahl zwar hidden=true bekommen, aber sichtbar
        // bleiben (CSS-Spezifitaets-Konflikt).
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~\.task-picker\[hidden\][^{]*\{[^}]*display:\s*none~s',
            $css,
            '.task-picker[hidden] muss display:none explizit setzen, sonst '.
            'ueberschreibt der grid/flex-Standard das Browser-default.'
        );
        self::assertStringContainsString('.task-picker.tp-stack[hidden]', $css,
            '.tp-stack[hidden] muss ebenfalls display:none erzwingen (Dashboard-Timer)');
    }
}
