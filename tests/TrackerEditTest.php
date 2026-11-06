<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: Tracker-Timeline-Eintraege sind editierbar + loeschbar
 * (Felix-Feedback 2026-11-06: "ich meine dass pro variante diese tracker
 * eintraege editierbar hat. mach das mal editierbar.", plus: "nix parken.
 * neue sachen von mir an den plan haengen. nichts fallen lassen").
 *
 * Deckt zwei Schichten ab:
 *   1. Repo::updateProjectTracker persistiert Werte, inkl. project_mood +
 *      customer_mood (frueher ignoriert, s. Felix-Nachbesserung).
 *   2. views/cockpit/project.php rendert pro Eintrag die <details>-Edit-
 *      Form mit allen Pflichtfeldern und einen Loeschen-Button.
 */
final class TrackerEditTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
    }

    private function root(): string
    {
        return dirname(__DIR__);
    }

    private function seedProjectAndUser(): array
    {
        $uid = TestSupport::seedUser('editor-' . uniqid());
        $cid = DB::insert(
            'INSERT INTO clients (name, code, created_at) VALUES (?, ?, ?)',
            ['Acme', 'acme-' . uniqid(), '2026-01-01 00:00:00']
        );
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, target_hours, planned_end, created_at) VALUES (?,?,?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), 100.0, '2026-12-31', '2026-01-01 00:00:00']
        );
        return [$pid, $uid];
    }

    // ---- Repo-Schicht ------------------------------------------------------

    public function testUpdatePersistsProgressValueForecastAndMoods(): void
    {
        [$pid, $uid] = $this->seedProjectAndUser();
        $id = Repo::saveProjectTracker($pid, 'progress', 'initial', [
            'value' => 50.0, 'value_forecast' => 60.0,
            'project_mood' => 3, 'customer_mood' => 3,
            'date_value' => '2026-05-01',
        ], $uid);

        Repo::updateProjectTracker($id, 'updated', null, [
            'value' => 72.0, 'value_forecast' => 85.0,
            'project_mood' => 5, 'customer_mood' => 1,
            'date_value' => '2026-05-15',
        ]);

        $row = Repo::projectTracker($id);
        self::assertSame('updated', $row['note']);
        self::assertSame(72.0, (float) $row['value']);
        self::assertSame(85.0, (float) $row['value_forecast']);
        self::assertSame(5,    (int) $row['project_mood']);
        self::assertSame(1,    (int) $row['customer_mood']);
        self::assertSame('2026-05-15', $row['date_value']);
    }

    public function testUpdateClampsMoodsOutOfRangeToNull(): void
    {
        [$pid, $uid] = $this->seedProjectAndUser();
        $id = Repo::saveProjectTracker($pid, 'progress', 'x', [
            'value' => 10.0, 'project_mood' => 3, 'customer_mood' => 3,
        ], $uid);

        Repo::updateProjectTracker($id, 'x', null, [
            'project_mood' => 9,     // out of 1..5
            'customer_mood' => '',   // leer = loeschen
        ]);

        $row = Repo::projectTracker($id);
        self::assertNull($row['project_mood'],  'Mood > 5 muss auf null fallen');
        self::assertNull($row['customer_mood'], 'Leerer String muss Mood loeschen');
    }

    public function testUpdateBackdatesCreatedAtWhenValidDateTimeGiven(): void
    {
        [$pid, $uid] = $this->seedProjectAndUser();
        $id = Repo::saveProjectTracker($pid, 'progress', 'x', ['value' => 20.0], $uid);

        Repo::updateProjectTracker($id, 'x', '2026-03-15T09:30', []);
        $row = Repo::projectTracker($id);
        self::assertSame('2026-03-15 09:30:00', $row['created_at'],
            'created_at muss auf ISO-datetime-local zurueckdatierbar sein (Pro-UI-Paritaet: Nachtrag am Montag fuer Freitag)');
    }

    public function testUpdateRejectsEmptyNote(): void
    {
        [$pid, $uid] = $this->seedProjectAndUser();
        $id = Repo::saveProjectTracker($pid, 'progress', 'x', ['value' => 10.0], $uid);
        $this->expectException(RuntimeException::class);
        Repo::updateProjectTracker($id, '', null, []);
    }

    // ---- View-Schicht ------------------------------------------------------

    public function testCockpitViewRendersEditFormAndDeleteButton(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');

        // Head-Layout: .tt-actions gruppiert Bearbeiten-Button + X-Loeschen
        // rechts im Head; der Inhalt (Datum/User/Pill) bleibt linksbuendig.
        self::assertStringContainsString(
            'class="tt-actions"',
            $view,
            'Head braucht eine .tt-actions-Box fuer Bearbeiten + Loeschen'
        );
        self::assertMatchesRegularExpression(
            '/<button[^>]*class="btn btn-ghost btn-sm tt-edit-toggle"[^>]*aria-controls="tt-edit-/s',
            $view,
            'Bearbeiten-Button muss als .tt-edit-toggle mit aria-controls im Head stehen'
        );
        self::assertMatchesRegularExpression(
            '/<div class="tt-edit"\s+id="tt-edit-[^"]+"\s+hidden>/',
            $view,
            'Edit-Panel ist ein <div class=tt-edit> hidden mit ID unter dem Body'
        );

        // Form postet an project_tracker_update mit id, project_id, CSRF
        // und einem expliziten return_to=cockpit_project.
        self::assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*project_tracker_update[^"]*"[^>]*class="tt-edit-form"/s',
            $view,
            'Edit-Form muss auf project_tracker_update posten'
        );
        self::assertStringContainsString(
            'name="return_to" value="cockpit_project"',
            $view,
            'return_to=cockpit_project sorgt dafuer, dass der Save zurueck in die Timeline fuehrt'
        );
        self::assertStringContainsString('name="created_at"',    $view, 'created_at fehlt');
        self::assertStringContainsString('name="project_mood"',  $view, 'project_mood fehlt');
        self::assertStringContainsString('name="customer_mood"', $view, 'customer_mood fehlt');

        // Abbrechen-Button neben Speichern, der das Panel ohne POST schliesst
        // (openEdit(li, false)).
        self::assertMatchesRegularExpression(
            '/<button[^>]*class="btn btn-ghost btn-sm tt-edit-cancel"[^>]*type="button"[^>]*>.*?common\.cancel/s',
            $view,
            'Abbrechen-Button neben Speichern fehlt'
        );
        self::assertMatchesRegularExpression(
            '/tt-edit-cancel.*?openEdit\(li,\s*false\)/s',
            $view,
            'Abbrechen-Handler muss openEdit(li, false) aufrufen'
        );

        // Loeschen bleibt als "x" im .tt-head (Felix-Feedback 2026-11-06:
        // "alle Eintraege bekommen ein Bearbeiten und ein Loeschenkreuz").
        // Im Edit-Panel bewusst KEIN Loeschen-Button - dort nur Speichern +
        // Abbrechen.
        self::assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*project_tracker_delete[^"]*"[^>]*class="tt-del-form"/s',
            $view,
            'X-Loeschen-Form muss im Head jedes Eintrags vorhanden sein'
        );
        self::assertStringContainsString(
            'class="tt-del"',
            $view,
            'X-Loeschen-Button (Klasse tt-del) im Head fehlt'
        );
        self::assertStringContainsString('return confirm(', $view, 'Delete braucht confirm()');
        self::assertStringNotContainsString('tt-del-form-inline', $view, 'Delete im Edit-Dialog weg');
        self::assertStringNotContainsString('btn-danger',         $view, 'Roter Delete im Dialog weg');
    }

    public function testCockpitCssHidesDeleteXWhileEditOpen(): void
    {
        // Felix-Feedback 2026-11-06: nur die Zeile, die gerade bearbeitet
        // wird, soll ihr x-Loeschen ausblenden. Realisiert ueber die
        // .is-editing-Klasse an der <li>, die vom tt-edit-toggle-Script
        // gesetzt wird.
        $css = (string) file_get_contents($this->root() . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '/\.tracker-timeline \.tt-item\.is-editing \.tt-del-form\s*\{\s*visibility\s*:\s*hidden/',
            $css,
            'CSS muss das tt-del-form ausblenden, solange die Zeile .is-editing traegt'
        );
    }

    public function testCockpitViewEditIsGatedByStructureManagePerm(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');
        self::assertMatchesRegularExpression(
            '/\$canEditTracker\s*=\s*Auth::can\(\'structure\.manage\'\)/',
            $view,
            'Edit-Zugriff muss per structure.manage-Permission beschraenkt sein (gleiches Guard wie Save)'
        );
        self::assertMatchesRegularExpression(
            '/\$editable\s*=\s*\$canEditTracker\s*&&\s*in_array\(\$ttype,\s*\[.+?progress.+?\],\s*true\)/s',
            $view,
            'Nur bestimmte Tracker-Typen sind editierbar (progress, date_shift, meeting, status_report)'
        );
    }
}
