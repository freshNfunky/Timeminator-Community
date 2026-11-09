<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#143): "Ich kann nicht unterscheiden zwischen
 * sogenannten internen Projekten, externen Projekten oder Foerderprojekten.
 * Das fehlt hier komplett."
 *
 * Schema + Projekt-Formular tragen bereits eine project_categories-FK, aber
 * die Dimension war in der Projekt-Liste und im Task-Picker unsichtbar.
 * Dieser Test zurrt die neue Sichtbarkeit fest.
 */
final class ProjectCategoryVisibilityTest extends TestCase
{
    public function testProjectsListShowsCategoryPill(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/structure/projects.php');
        self::assertStringContainsString("t('projects.category', 'Projektart')", $view,
            'Projektart-Spalten-Header fehlt');
        self::assertStringContainsString('pill-cat-', $view,
            'Kategorie-Pill mit ckey-Klasse fehlt in der Liste');
    }

    public function testTaskPickerCarriesCategoryInProjectOptions(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/partials/task_picker.php');
        self::assertStringContainsString('data-cat-ckey', $view,
            'Projekt-Option muss data-cat-ckey mitfuehren, damit JS/CSS Kategorie-aware sein kann');
        self::assertStringContainsString('data-cat-label', $view,
            'Projekt-Option muss data-cat-label mitfuehren');
        self::assertStringContainsString("'[' . \$p['category_label'] . '] '", $view,
            'Option-Text praefix "[<Kategorie>] " fehlt');
    }

    public function testRepoTasksSqlJoinsProjectCategories(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/src/Repo.php');
        self::assertMatchesRegularExpression(
            '~LEFT JOIN project_categories pc ON pc\.id\s*=\s*p\.category_id~',
            $src,
            'Repo::tasks() muss project_categories per LEFT JOIN einbinden'
        );
        self::assertStringContainsString('pc.label AS project_category_label', $src,
            'Repo::tasks() muss project_category_label selektieren');
        self::assertStringContainsString('pc.ckey AS project_category_ckey', $src,
            'Repo::tasks() muss project_category_ckey selektieren');
    }

    public function testCategoryPillsHaveCssStyling(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        foreach (['kundenprojekt', 'internes_projekt', 'foerderprojekt'] as $ckey) {
            self::assertStringContainsString(".pill-cat-{$ckey}", $css,
                "CSS-Regel fuer pill-cat-{$ckey} fehlt");
        }
        // Dark-Mode fuer alle drei Kategorien.
        self::assertStringContainsString(':root[data-theme="dark"] .pill-cat-kundenprojekt', $css);
    }
}
