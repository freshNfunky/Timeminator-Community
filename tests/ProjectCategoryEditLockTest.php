<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #174): Beim Edit darf die Projektart
 * NICHT zwischen intern (needs_client=0) und nicht-intern (needs_client=1)
 * wechseln. Interne Projekte haben technisch den Sentinel-Kunden
 * __internal__; ein Wechsel auf Kundenprojekt wuerde ohne Kunde-Switch
 * den Sentinel vererben (Datenintegritaet-Problem). V1.2 (Pro-Issue)
 * wird den Switch koordiniert mit Kunde-Wechsel erlauben.
 */
final class ProjectCategoryEditLockTest extends TestCase
{
    public function testEditFormHidesCrossCategoryOptions(): void
    {
        $tpl = (string) file_get_contents(dirname(__DIR__) . '/views/partials/forms/project.php');
        // Edit-Modus: Projektart-Dropdown enthaelt nur Optionen GLEICHER
        // Art (intern bleibt intern, nicht-intern bleibt nicht-intern).
        self::assertStringContainsString('$__editIsInternal', $tpl,
            'Form muss merken, ob das Edit-Projekt intern ist');
        self::assertStringContainsString('!((int) $__pc[\'needs_client\'])', $tpl,
            'Needs_client=0 wird als internal interpretiert');
        self::assertMatchesRegularExpression(
            '~if\s*\(\s*\$__pcIsInternal\s*!==\s*\$__editIsInternal\s*\)\s*\{\s*continue;\s*\}~',
            $tpl,
            'Optionen mit gegensaetzlicher Projektart muessen uebersprungen werden'
        );
    }

    public function testEditFormForInternalProjectShowsReadonlyLabelWithoutProse(): void
    {
        // Felix-Feedback 2026-11-09 (Fix #177): bei internen Projekten
        // KEINE Combobox und KEIN Prosatext - nur readonly-Text "Internes
        // Projekt" + hidden category_id.
        $tpl = (string) file_get_contents(dirname(__DIR__) . '/views/partials/forms/project.php');
        self::assertStringContainsString("if (\$edit && \$__editIsInternal === true", $tpl,
            'View muss intern-Fall separat behandeln');
        self::assertStringContainsString('class="input-readonly"', $tpl,
            'Intern-Fall: readonly-Container fuer den Kategorie-Label');
        self::assertStringContainsString('<input type="hidden" name="category_id"', $tpl,
            'Hidden category_id muss die Projektart beim Save weiter transportieren');
        // Der ehemalige Prosatext darf nicht mehr im Template stehen.
        self::assertStringNotContainsString(
            'projects.category_lock_hint',
            $tpl,
            'Prosa-Hinweis "Wechsel ab v1.2" muss aus der UI raus - hat keinen Informationswert fuer End-User'
        );
    }

    public function testSaveRejectsCategorySwitchAcrossInternalBoundary(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/structure_ctrl.php');
        // Kern-Check: wasInternal !== willBeInternal -> flash + redirect.
        self::assertStringContainsString('$wasInternal', $ctrl,
            'ctrl_project_save muss den existierenden intern-Status pruefen');
        self::assertStringContainsString('$willBeInternal', $ctrl,
            'ctrl_project_save muss die neue Projektart auf intern pruefen');
        self::assertMatchesRegularExpression(
            '~if\s*\(\s*\$wasInternal\s*!==\s*\$willBeInternal\s*\)~',
            $ctrl,
            'Ablehnung des Wechsels intern<->nicht-intern muss im Code stehen'
        );
        self::assertStringContainsString("'projects.err_category_lock'", $ctrl,
            'Spezifischer Fehler-Key fuer den Lock muss gesetzt werden');
    }

    public function testI18nContainsCategoryLockMessages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        self::assertStringContainsString("'projects.category_lock_hint'", $de,
            'DE i18n fehlt projects.category_lock_hint');
        self::assertStringContainsString("'projects.category_lock_hint'", $en,
            'EN i18n fehlt projects.category_lock_hint');
        self::assertStringContainsString("'projects.err_category_lock'", $de,
            'DE i18n fehlt projects.err_category_lock');
        self::assertStringContainsString("'projects.err_category_lock'", $en,
            'EN i18n fehlt projects.err_category_lock');
    }
}
