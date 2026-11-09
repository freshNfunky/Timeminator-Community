<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #166): bei internen Projekten darf in
 * der Dashboard-Liste und im Kalender NICHT der Kundenname (z.B.
 * "Nordlicht Consulting") erscheinen - internes Projekt hat technisch
 * einen Platzhalter-Kunden. Felix will dort den Kategorie-Label
 * ("Internes Projekt") sehen, damit die Buchung nicht dem Kunden
 * zugeschlagen wirkt.
 *
 * entry_client_display() in src/helpers.php kapselt diese Switch-Logik.
 * Repo::entries, Repo::entry und Repo::running liefern dafuer die
 * Spalten project_category_ckey + project_category_label mit.
 */
final class EntryClientDisplayTest extends TestCase
{
    public function testReturnsEmDashForInternalProjects(): void
    {
        // Fix #179: Felix will bei internen Projekten in der Kunden-Spalte
        // einen em-dash sehen statt "Internes Projekt" - macht deutlicher
        // dass es keinen Kunden gibt.
        $e = [
            'client_name'              => 'Nordlicht Consulting',
            'project_category_ckey'    => 'intern',
            'project_category_label'   => 'Internes Projekt',
        ];
        self::assertSame('—', entry_client_display($e));
    }

    public function testFallsBackToClientNameForKundenprojekt(): void
    {
        $e = [
            'client_name'              => 'Aurora Labs',
            'project_category_ckey'    => 'kunde',
            'project_category_label'   => 'Kundenprojekt',
        ];
        self::assertSame('Aurora Labs', entry_client_display($e));
    }

    public function testFallsBackToClientNameForFoerderprojekt(): void
    {
        $e = [
            'client_name'              => 'Bundesministerium X',
            'project_category_ckey'    => 'foerderung',
            'project_category_label'   => 'Foerderprojekt',
        ];
        self::assertSame('Bundesministerium X', entry_client_display($e));
    }

    public function testFallsBackToClientNameWhenCategoryMissing(): void
    {
        // Backwards-compat: alte Rows ohne category-Felder.
        $e = ['client_name' => 'Falkenflug GmbH'];
        self::assertSame('Falkenflug GmbH', entry_client_display($e));
    }

    public function testEmDashEvenWhenInternLabelMissing(): void
    {
        // Fix #179: intern -> immer em-dash, egal ob ein Label gesetzt ist.
        $e = [
            'client_name'              => 'Platzhalter-Kunde',
            'project_category_ckey'    => 'intern',
            'project_category_label'   => '',
        ];
        self::assertSame('—', entry_client_display($e));
    }

    public function testRepoEntriesQueryIncludesCategoryColumns(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__) . '/src/Repo.php');
        // Repo::entries, Repo::entry und Repo::running muessen project_category_*
        // mitliefern - sonst zeigt entry_client_display immer den Kundennamen.
        self::assertStringContainsString('pc.ckey  AS project_category_ckey', $repo,
            'Repo::entries muss project_category_ckey liefern');
        self::assertStringContainsString('pc.label AS project_category_label', $repo,
            'Repo::entries muss project_category_label liefern');
        // Einzel-Entry + Running-Timer.
        self::assertStringContainsString('pc.ckey AS project_category_ckey', $repo,
            'Repo::entry / Repo::running muessen project_category_ckey liefern');
    }

    public function testViewsUseEntryClientDisplayHelper(): void
    {
        // Pflicht in den Hauptdarstellungen (Dashboard + Kalender).
        $dashboard = (string) file_get_contents(dirname(__DIR__) . '/views/dashboard.php');
        self::assertStringContainsString('entry_client_display($running)', $dashboard,
            'Laufender Timer muss ueber entry_client_display gerendert werden');
        self::assertStringContainsString('entry_client_display($e)', $dashboard,
            'Recent-Tabelle muss ueber entry_client_display gerendert werden');

        $week = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        self::assertStringContainsString('entry_client_display($__e)', $week,
            'Wochen-Kalender muss ueber entry_client_display gerendert werden');

        $month = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_month.php');
        self::assertStringContainsString('entry_client_display($e)', $month,
            'Monats-Kalender muss ueber entry_client_display gerendert werden');
    }
}
