<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #139/#140/#145): Im Zeiteintrag fehlten
 * - aus Felix' Sicht - Prozesse (Akquise/Leads/Buchhaltung) und
 * Abwesenheiten (Urlaub/Krankheit/Ueberstundenabbau). Tatsaechlich
 * verlinkte das Dashboard auf die Legacy entry_form-Seite, die diese
 * Typen nicht hat; der Overlay-Dialog in /entries hatte sie laengst.
 * Mit Fix #142 oeffnet der Dashboard-Link nun den Dialog direkt.
 *
 * Dieser Test friert den Dialog-Vertrag ein: Typ-Dropdown muss drei
 * Sektionen bieten - Projekt-Zeit, Interne Prozesse (<optgroup>),
 * Abwesenheiten (<optgroup>) - und die Server-Seite muss Kategorien
 * laden, damit die Optgroups nicht leer bleiben.
 */
final class EntryDialogTypesTest extends TestCase
{
    public function testDialogMarkupHasProjectProcessAndAbsenceTypes(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/index.php');
        self::assertStringContainsString('<dialog id="calDialog"', $view);
        self::assertStringContainsString("t('cal.type_project', 'Projekt-Zeit')", $view,
            'Typ "Projekt-Zeit" fehlt im Dialog-Dropdown');
        self::assertStringContainsString("t('cal.group_processes'", $view,
            'Optgroup "Interne Prozesse" fehlt');
        self::assertStringContainsString("t('cal.group_absences'", $view,
            'Optgroup "Abwesenheiten" fehlt');
        // Markup iteriert ueber die Server-Listen.
        self::assertStringContainsString("foreach (\$processCats as \$__c)", $view,
            'Prozess-Kategorien werden nicht iteriert');
        self::assertStringContainsString("foreach (\$absenceCats as \$__c)", $view,
            'Abwesenheits-Kategorien werden nicht iteriert');
    }

    public function testEntriesControllerLoadsProcessAndAbsenceCategories(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
        self::assertStringContainsString('AbsenceRepo::categories(true)', $ctrl,
            'Entries-Controller muss aktive Abwesenheits-Kategorien laden');
        self::assertStringContainsString('ProcessRepo::categories(true)', $ctrl,
            'Entries-Controller muss aktive Prozess-Kategorien laden');
        self::assertStringContainsString("'processCats'", $ctrl,
            'processCats muss an die View uebergeben werden');
        self::assertStringContainsString("'absenceCats'", $ctrl,
            'absenceCats muss an die View uebergeben werden');
    }

    public function testDialogJsRoutesToThreeDistinctSaveEndpoints(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString('D.urls.entry_save', $js,
            'Projekt-Zeit-Save-URL fehlt');
        self::assertStringContainsString('D.urls.process_save', $js,
            'Prozess-Save-URL fehlt');
        self::assertStringContainsString('D.urls.absence_save', $js,
            'Abwesenheits-Save-URL fehlt');
    }
}
