<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * v1.0 F3 Firmen-Cockpit (Issue #162).
 *
 * Smoke: Soll/Ist-Matrix firmenweit aggregiert projektuebergreifend
 * (verwendet PlanRepo); Prozess-Stunden-Rollup summiert korrekt. Die
 * commercial Tiles sind reine `.pro-teaser`-Links und werden nur
 * strukturell gepinnt — keine EUR-Berechnung in Community.
 */
final class CompanyCockpitTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    private function seedProject(string $name = 'P'): int
    {
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['C', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        return DB::insert(
            'INSERT INTO projects (client_id, name, code, created_at) VALUES (?,?,?,?)',
            [$cid, $name, 'p-' . uniqid(), '2026-01-01 00:00:00']
        );
    }

    public function testCompanyMatrixAggregatesAcrossProjects(): void
    {
        $u1 = TestSupport::seedUser('alex');
        $p1 = $this->seedProject('ProjectA');
        $p2 = $this->seedProject('ProjectB');
        // 300 min on p1 + 180 min on p2 same user same week -> sum 480 min firmenweit.
        PlanRepo::saveAllocation([
            'project_id' => $p1, 'user_id' => $u1, 'period_kind' => 'week',
            'period_start' => '2026-10-26', 'planned_minutes' => 300,
        ]);
        PlanRepo::saveAllocation([
            'project_id' => $p2, 'user_id' => $u1, 'period_kind' => 'week',
            'period_start' => '2026-10-26', 'planned_minutes' => 180,
        ]);
        $soll = PlanRepo::sollByProjectUserWeek('2026-10-26', '2026-11-02');
        // Flatten the way the company_cockpit_ctrl does.
        $byUserWeek = [];
        foreach ($soll as $byUser) {
            foreach ($byUser as $uid => $byWeek) {
                foreach ($byWeek as $wk => $m) {
                    $byUserWeek[$uid][$wk] = ($byUserWeek[$uid][$wk] ?? 0) + $m;
                }
            }
        }
        self::assertSame(480, (int) $byUserWeek[$u1]['2026-W44']);
    }

    public function testProcessHoursRollupSumsCorrectly(): void
    {
        $uid = TestSupport::seedUser('tim');
        $cat = DB::insert(
            'INSERT INTO process_categories (ckey, label, position, active, created_at) VALUES (?,?,?,1,?)',
            ['meetings', 'Meetings', 10, '2026-01-01 00:00:00']
        );
        DB::insert(
            'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?)',
            [$uid, $cat, '2026-05-02 09:00:00', '2026-05-02 11:00:00', 120, '2026-05-02 09:00:00', '2026-05-02 09:00:00']
        );
        DB::insert(
            'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?)',
            [$uid, $cat, '2026-06-15 09:00:00', '2026-06-15 10:00:00', 60, '2026-06-15 09:00:00', '2026-06-15 09:00:00']
        );
        // Out-of-range entry (2025) must NOT count toward 2026 total.
        DB::insert(
            'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?)',
            [$uid, $cat, '2025-12-20 09:00:00', '2025-12-20 10:00:00', 60, '2025-12-20 09:00:00', '2025-12-20 09:00:00']
        );

        $rows = DB::all(
            "SELECT pc.id, COALESCE(SUM(pe.duration_min), 0) AS minutes, COUNT(pe.id) AS entries
               FROM process_categories pc
          LEFT JOIN process_entries pe
                 ON pe.category_id = pc.id
                AND substr(pe.start_ts, 1, 10) >= ?
                AND substr(pe.start_ts, 1, 10) <= ?
              WHERE pc.active = 1
              GROUP BY pc.id",
            ['2026-01-01', '2026-12-31']
        );
        self::assertCount(1, $rows);
        self::assertSame(180, (int) $rows[0]['minutes']);
        self::assertSame(2,   (int) $rows[0]['entries']);
    }

    public function testCategoryHoursExcludeInactiveProjects(): void
    {
        $uid = TestSupport::seedUser('u');
        $catId = DB::insert('INSERT INTO project_categories (ckey, label, needs_client, position, active, created_at) VALUES (?,?,1,10,1,?)',
            ['ext', 'External', '2026-01-01 00:00:00']);
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['C', 'c-x', '2026-01-01 00:00:00']);
        $pActive = DB::insert('INSERT INTO projects (client_id, category_id, name, code, active, created_at) VALUES (?,?,?,?,1,?)',
            [$cid, $catId, 'Active', 'pa', '2026-01-01 00:00:00']);
        $pInactive = DB::insert('INSERT INTO projects (client_id, category_id, name, code, active, created_at) VALUES (?,?,?,?,0,?)',
            [$cid, $catId, 'Inactive', 'pi', '2026-01-01 00:00:00']);
        $tid = DB::insert('INSERT INTO tasks (project_id, work_package_id, name, created_at) VALUES (?,NULL,?,?)',
            [$pActive, 'T', '2026-01-01 00:00:00']);
        DB::insert(
            'INSERT INTO time_entries (user_id, client_id, project_id, task_id, start_ts, end_ts, duration_min, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $cid, $pActive, $tid, '2026-05-02 09:00:00', '2026-05-02 11:00:00', 120, '2026-05-02 09:00:00', '2026-05-02 09:00:00']
        );
        // The controller query filters WHERE p.active = 1, so Inactive stays
        // out of the aggregation entirely.
        $rows = DB::all(
            "SELECT COALESCE(pc.id, 0) AS cat_id, COUNT(DISTINCT p.id) AS project_count,
                    COALESCE(SUM(te.duration_min), 0) AS minutes
               FROM projects p
          LEFT JOIN project_categories pc ON pc.id = p.category_id
          LEFT JOIN time_entries te
                 ON te.project_id = p.id
                AND te.duration_min IS NOT NULL
                AND substr(te.start_ts, 1, 10) >= ?
                AND substr(te.start_ts, 1, 10) <= ?
              WHERE p.active = 1
              GROUP BY COALESCE(pc.id, 0)",
            ['2026-01-01', '2026-12-31']
        );
        self::assertCount(1, $rows);
        self::assertSame(1,   (int) $rows[0]['project_count']);
        self::assertSame(120, (int) $rows[0]['minutes']);
    }

    public function testCompanyCockpitMovesSubtitleProseIntoInfoTooltips(): void
    {
        // Felix-Feedback 2026-11-07: Prosa-Hinweise in den Card-Heads gehoeren
        // als ⓘ-Tooltip, nicht als sichtbare muted-Zeile. Betrifft alle vier
        // Subtitles im Firmen-Cockpit (Jahreskennzahlen, Woche Ist/Soll,
        // Projekte nach Projektart, Interne Prozesse).
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/cockpit/company.php');

        // Alle vier Prosa-span.muted sind weg (die kurzen muted-Zellen in der
        // Matrix mit font-size:11px bleiben - das sind Daten, keine Prosa).
        foreach ([
            'company_kpis_subtitle',
            'company_matrix_subtitle',
            'company_cats_subtitle',
            'company_processes_subtitle',
        ] as $oldKey) {
            self::assertStringNotContainsString(
                "span class=\"muted\"><?= h(t('cockpit.$oldKey'",
                $view,
                "Prosa-Subtitle $oldKey darf nicht mehr als sichtbarer muted-span stehen"
            );
            self::assertStringNotContainsString(
                "span class=\"muted\"><?= h(sprintf(t('cockpit.$oldKey'",
                $view,
                "Prosa-Subtitle $oldKey (sprintf-Variante) darf nicht mehr als muted-span stehen"
            );
        }

        // Intro-Prosa am Firmen-Cockpit-Head muss ebenfalls Tooltip sein.
        self::assertStringNotContainsString(
            "p class=\"muted\" style=\"margin-bottom:0\">\n    <?= h(t('cockpit.company_intro'",
            $view,
            'Intro-Prosa am Firmen-Cockpit-Head muss als sichtbarer muted-Absatz entfallen'
        );

        // Stattdessen: tip()-Aufruf pro Card-Head.
        foreach ([
            'cockpit.company_intro',
            'cockpit.company_kpis_subtitle',
            'cockpit.company_matrix_subtitle',
            'cockpit.company_cats_subtitle_tip',
            'cockpit.company_processes_subtitle_tip',
        ] as $tipKey) {
            self::assertStringContainsString(
                "tip('$tipKey')",
                $view,
                "tip()-Aufruf fuer $tipKey fehlt"
            );
        }

        // Die Prosa-Fuss-Zeile unter der Matrix ist weg und ihre Erklaerung
        // wandert in den Matrix-Tooltip mit rein.
        self::assertStringNotContainsString(
            'company_matrix_legend',
            $view,
            'Matrix-Legend-Prosa am Tabellen-Fuss muss entfallen (Inhalt landet im Head-Tooltip)'
        );
        $de = include dirname(__DIR__) . '/lang/de.php';
        self::assertStringContainsString(
            'gruen = Soll gesetzt',
            $de['cockpit.company_matrix_subtitle'],
            'Matrix-Tooltip muss die Farblegende enthalten (konsolidiert aus matrix_legend)'
        );
    }

    public function testProTeaserTilesShowFictiveDemoNumbersInsteadOfDashPlaceholder(): void
    {
        // Felix-Feedback 2026-11-06: die Pro-Teaser-Kacheln im Firmen-
        // Cockpit sollen keine "— €"-Platzhalter mehr zeigen, sondern
        // ausgegraute, deutlich als Beispiel markierte Zahlen. CSS-Klasse
        // .tile-value-demo macht das visuell (gestrichelte Unterstreichung,
        // opacity .6).
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/cockpit/company.php');
        $css  = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');

        // Mindestens die drei prominenten Tiles haben konkrete Beispielzahlen.
        self::assertStringContainsString('142.800 €', $view, 'Angebote-gewonnen-Teaser braucht fiktive Beispielzahl');
        self::assertStringContainsString('98.650 €',  $view, 'Rechnungsstellung-Teaser braucht fiktive Beispielzahl');
        self::assertStringContainsString('+ 38.250 €', $view, 'Ergebnis-Teaser braucht fiktive Beispielzahl inkl. Vorzeichen');

        // Alle Werte tragen die CSS-Klasse "tile-value-demo", damit sie
        // optisch als Beispiel durchgestrichen/ausgegraut werden.
        self::assertMatchesRegularExpression(
            '/tile-value tile-value-demo/',
            $view,
            'Teaser-Werte muessen die CSS-Klasse tile-value-demo tragen'
        );

        // Fallback-Platzhalter "— €" nur noch dort erlaubt, wo keine
        // konkrete Beispielzahl Sinn ergibt (hier: nirgends im Firmen-
        // Cockpit mehr).
        self::assertStringNotContainsString(
            '>— €</div>',
            $view,
            'Alte Dash-Platzhalter "— €" muessen aus den Teaser-Tiles raus'
        );

        // CSS kennt die Demo-Markierung (dashed underline + opacity).
        self::assertMatchesRegularExpression(
            '/\.tile-value-demo\s*\{[^}]*text-decoration:\s*underline\s+dashed/',
            $css,
            'CSS .tile-value-demo braucht dashed underline (visuell Beispiel-Markierung)'
        );
    }
}
