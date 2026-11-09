<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #175): Projekt-Neuanlage-Form soll die
 * gleiche Kaskaden-Hierarchie wie der Entry-Dialog haben - erst
 * Projektart, dann Kunde. Keine flachen Listen. Bei Internes Projekt
 * entfaellt das Kunden-Feld; bei Foerderprojekt wird das Label auf
 * "Projekttraeger" umgestellt. Solange keine Projektart gewaehlt ist,
 * ist der Kunden-Select disabled mit Placeholder "Zuerst Projektart
 * waehlen".
 */
final class ProjectFormCascadeTest extends TestCase
{
    private function tpl(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/partials/forms/project.php');
    }

    public function testCreateFormHasEmptyCategoryDefaultOption(): void
    {
        $tpl = $this->tpl();
        // Beim Neuanlegen ($edit === null) soll eine leere Default-Option
        // "Projektart waehlen …" sichtbar sein, damit die Kaskade von vorne
        // beginnt.
        self::assertStringContainsString("'projects.pick_category'", $tpl,
            'Form braucht projects.pick_category i18n');
        self::assertMatchesRegularExpression(
            '~if\s*\(!\s*\$edit\s*\):\s*\?>\s*<option value=""~',
            $tpl,
            'Leere Default-Option darf nur im Create-Modus (nicht Edit) stehen'
        );
    }

    public function testClientLabelDataAttributesForCascade(): void
    {
        $tpl = $this->tpl();
        self::assertStringContainsString('class="project-client-label"', $tpl,
            'Client-Label-Wrapper fehlt');
        self::assertStringContainsString('data-base-label=', $tpl,
            'data-base-label fuer Label-Swap bei Foerderprojekt fehlt');
        self::assertStringContainsString('data-lock-hint=', $tpl,
            'data-lock-hint fuer "Zuerst Projektart waehlen" fehlt');
        self::assertStringContainsString('data-base-placeholder=', $tpl,
            'data-base-placeholder fuer Placeholder-Swap fehlt');
        self::assertStringContainsString('class="project-client-label-text"', $tpl,
            'Label-Text-Span fehlt (fuer dynamischen Austausch)');
        self::assertStringContainsString('class="project-client-select"', $tpl,
            'Client-Select braucht Marker-Klasse fuer das Cascade-Script');
    }

    public function testCascadeScriptHidesClientOnInternalAndLocksWithoutCategory(): void
    {
        $tpl = $this->tpl();
        // Lock wenn keine Projektart gewaehlt (catSel.value leer).
        self::assertStringContainsString('clientSel.disabled  = !needsClient || !catSel.value', $tpl,
            'Client-Select muss disabled sein ohne Projektart oder bei interner Art');
        // Label hidden fuer internes Projekt.
        self::assertStringContainsString('clientLab.hidden    = !needsClient', $tpl,
            'Client-Label muss bei needs_client=0 hidden sein');
        // Label-Swap bei Foerderprojekt (customLabel = data-client-label).
        self::assertStringContainsString('customLabel', $tpl,
            'Label-Swap-Logik fehlt (Foerdermittelgeber/Projekttraeger)');
        // Placeholder-Swap: lockHint wenn keine Art gewaehlt.
        self::assertStringContainsString('catSel.value ? basePlh : lockHint', $tpl,
            'Placeholder muss zwischen basePlh und lockHint wechseln');
    }

    public function testI18nContainsCascadeKeys(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        self::assertStringContainsString("'projects.pick_category'", $de);
        self::assertStringContainsString("'projects.pick_category'", $en);
        self::assertStringContainsString("'projects.client_lock_hint'", $de);
        self::assertStringContainsString("'projects.client_lock_hint'", $en);
    }
}
