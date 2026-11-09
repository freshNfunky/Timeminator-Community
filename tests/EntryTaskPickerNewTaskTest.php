<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #141): Aufgaben-Dropdown im Entry-
 * Formular hat eine zusaetzliche "+ Neue Aufgabe"-Option; waehlt der
 * Nutzer sie, erscheint unter dem Select ein Inline-Input, in dem er den
 * Namen der neuen Aufgabe tippt. Der Server legt die Task mit dem im
 * Picker eingestellten Projekt-/Arbeitspaket-Kontext an und verwendet
 * die neue ID direkt fuer den Entry.
 */
final class EntryTaskPickerNewTaskTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
    }

    public function testPickerViewHasNewTaskOptionAndInlinePanel(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/partials/task_picker.php');
        self::assertStringContainsString('value="__new__"', $view,
            '"+ Neue Aufgabe"-Option mit value="__new__" fehlt im Task-Dropdown');
        self::assertStringContainsString('class="tp-opt-new"', $view,
            'Markierungsklasse tp-opt-new fuer die neue Option fehlt');
        self::assertStringContainsString('class="tp-new-task"', $view,
            'Inline-Create-Panel .tp-new-task fehlt');
        self::assertStringContainsString('name="new_task_name"', $view,
            'Eingabefeld fuer neuen Aufgabennamen fehlt');
        self::assertStringContainsString('name="new_task_project_id"', $view,
            'Hidden-Mirror-Feld new_task_project_id fehlt');
        self::assertStringContainsString('name="new_task_wp_id"', $view,
            'Hidden-Mirror-Feld new_task_wp_id fehlt');
    }

    public function testJsWiresTaskPickerSyncAndSubmitMirror(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/app.js');
        self::assertStringContainsString("task.value === '__new__'", $js,
            'JS muss __new__ als Trigger fuer das Inline-Panel erkennen');
        self::assertStringContainsString("root.querySelector('.tp-new-task')", $js,
            'JS sucht das Panel via .tp-new-task');
        self::assertStringContainsString('form.addEventListener(\'submit\'', $js,
            'Submit-Handler muss Projekt + WP final mirrorn, bevor der Form-POST rausgeht');
    }

    public function testCssDefinesInlinePanelLayout(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~\.task-picker\s+\.tp-new-task\s*\{[^}]*grid-column:\s*1\s*/\s*-1~s',
            $css,
            '.tp-new-task muss im Picker-Grid ueber alle 4 Spalten spannen'
        );
    }

    public function testResolverReturnsRawIntForExistingTask(): void
    {
        require_once dirname(__DIR__) . '/src/controllers/entries_ctrl.php';
        self::assertSame(42, entries_resolve_task_id('42', ['name' => '', 'project_id' => 0, 'wp_id' => 0]));
    }

    public function testResolverRefusesNewTaskWithoutProject(): void
    {
        require_once dirname(__DIR__) . '/src/controllers/entries_ctrl.php';
        self::assertSame(0, entries_resolve_task_id('__new__', ['name' => 'Konzept', 'project_id' => 0, 'wp_id' => 0]),
            'Ohne Projekt kann keine neue Aufgabe entstehen -> 0');
        self::assertSame(0, entries_resolve_task_id('__new__', ['name' => '', 'project_id' => 5, 'wp_id' => 0]),
            'Ohne Namen kann keine neue Aufgabe entstehen -> 0');
    }

    public function testResolverCreatesTaskUnderPickedProject(): void
    {
        require_once dirname(__DIR__) . '/src/controllers/entries_ctrl.php';
        $clientId  = DB::insert('INSERT INTO clients (name, code, active, created_at) VALUES (?,?,?,?)',
            ['Acme', 'ACM', 1, now()]);
        $projectId = DB::insert('INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$clientId, 'Pilot', 'PIL', 1, now()]);
        $wpId      = DB::insert('INSERT INTO work_packages (project_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$projectId, 'WP1', 'WP1', 1, now()]);

        $newId = entries_resolve_task_id('__new__', [
            'name' => 'Konzept',
            'project_id' => $projectId,
            'wp_id' => $wpId,
        ]);
        self::assertGreaterThan(0, $newId, 'Neue Task-ID muss > 0 sein');
        $row = DB::one('SELECT project_id, work_package_id, name, active FROM tasks WHERE id = ?', [$newId]);
        self::assertSame($projectId, (int) $row['project_id']);
        self::assertSame($wpId, (int) $row['work_package_id']);
        self::assertSame('Konzept', $row['name']);
        self::assertSame(1, (int) $row['active']);
    }

    public function testResolverCreatesTaskWithoutWorkPackage(): void
    {
        require_once dirname(__DIR__) . '/src/controllers/entries_ctrl.php';
        $clientId  = DB::insert('INSERT INTO clients (name, code, active, created_at) VALUES (?,?,?,?)',
            ['Beta', 'BET', 1, now()]);
        $projectId = DB::insert('INSERT INTO projects (client_id, name, code, active, created_at) VALUES (?,?,?,?,?)',
            [$clientId, 'Beta-Proj', 'BEP', 1, now()]);
        $newId = entries_resolve_task_id('__new__', [
            'name' => 'Review',
            'project_id' => $projectId,
            'wp_id' => 0,
        ]);
        self::assertGreaterThan(0, $newId);
        $row = DB::one('SELECT work_package_id FROM tasks WHERE id = ?', [$newId]);
        self::assertNull($row['work_package_id'], 'wp_id=0 muss als NULL landen');
    }

    public function testI18nKeysPresentInBothLanguages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        foreach (['tp.new_task', 'tp.new_task_name', 'tp.new_task_hint'] as $key) {
            self::assertStringContainsString("'{$key}'", $de, "DE-Key {$key} fehlt");
            self::assertStringContainsString("'{$key}'", $en, "EN-Key {$key} fehlt");
        }
    }
}
