<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #142): "Die Buchung im Kalender darf
 * nicht in einem eigenen Fenster gemacht werden, sondern oeffnet ein
 * Panel oben drueber, in dem dann die Eingaben getaetigt werden." -
 * Pro-Variante nutzt einen Overlay-Dialog, Community hatte zwar den
 * Dialog in /entries, aber das Dashboard-"+ Eintrag"-Button verlinkte
 * noch auf die Legacy entry_form-Seite, was den Dialog umging.
 */
final class EntryOverlayDialogTest extends TestCase
{
    public function testDashboardAddManualLinksToEntriesWithNewFlag(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/dashboard.php');
        self::assertStringContainsString(
            "route('entries', ['view' => 'calendar', 'new' => 1])",
            $view,
            'Dashboard-Button muss zu entries?view=calendar&new=1 verlinken, nicht zur Legacy-Form-Seite'
        );
        self::assertStringNotContainsString(
            "route('entry_form')",
            $view,
            'Legacy entry_form-Link darf nicht mehr im Dashboard sein'
        );
    }

    public function testCalendarJsHandlesNewOneParamToAutoOpenDialog(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString("params.get('new') === '1'", $js,
            'calendar.js muss den new=1-Query-Param pruefen');
        self::assertStringContainsString('openForNew(y);', $js,
            'Bei new=1 muss der Dialog via openForNew geoeffnet werden');
        self::assertStringContainsString('history.replaceState', $js,
            'Nach dem Oeffnen muss die URL entschaerft werden (keine Dialog-Wiederoeffnung bei Reload)');
    }

    public function testEntriesIndexStillHostsTheOverlayDialogMarkup(): void
    {
        // Sicherung: das Dialog-Markup, auf das das Dashboard jetzt
        // verweist, existiert tatsaechlich auf der Entries-Seite.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/index.php');
        self::assertStringContainsString('<dialog id="calDialog"', $view,
            'calDialog-Markup muss in views/entries/index.php bleiben');
        self::assertStringContainsString('entryAddBtn', $view,
            'entryAddBtn-Button bleibt der Haupt-Trigger fuer den Dialog');
    }

    /**
     * Felix-Feedback 2026-11-09 (Hardening): ctrl_entry_form darf NIE wieder
     * das Legacy-Vollseiten-Formular rendern - jede Anfrage wird zum
     * Overlay-Dialog umgeleitet. Edit via ?id=X -> edit=X, Neu -> new=1.
     */
    public function testLegacyEntryFormControllerRedirectsToOverlay(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
        // Kein view()-Call mehr in ctrl_entry_form - nur Redirects.
        self::assertMatchesRegularExpression(
            '~function ctrl_entry_form\(\): void\s*\{[^}]*redirect_route\(\'entries\'[^}]*\}~s',
            $ctrl,
            'ctrl_entry_form muss ausschliesslich zu entries redirecten'
        );
        self::assertStringContainsString(
            "redirect_route('entries', ['view' => 'calendar', 'edit' => \$id])",
            $ctrl,
            'Edit-Fall muss ?view=calendar&edit=X liefern, damit JS openForEntry triggert'
        );
        self::assertStringContainsString(
            "redirect_route('entries', ['view' => 'calendar', 'new' => 1])",
            $ctrl,
            'Neu-Fall muss ?view=calendar&new=1 liefern, damit JS openForNew triggert'
        );
    }

    public function testCalendarJsHandlesEditIdParam(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString("params.get('edit')", $js,
            'calendar.js muss auch den edit-Query-Param pruefen');
        self::assertStringContainsString('openForEntry(editId)', $js,
            'Bei edit=X muss openForEntry den Bestandseintrag im Overlay oeffnen');
    }

    /**
     * Felix-Feedback 2026-11-09: Als ERSTES muss der Nutzer im Overlay
     * entscheiden, ob Projekt/Prozess/Abwesenheit - DANN kommt alles
     * andere. Markup-Vertrag pinnen.
     */
    public function testOverlayHasTypeSwitcherAsFirstActiveControl(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/index.php');
        // Reihenfolge im Markup: Titel -> calEntryId (hidden) -> calType
        // VOR calTaskWrap.
        $posType = strpos($view, 'id="calType"');
        $posTask = strpos($view, 'id="calTaskWrap"');
        self::assertNotFalse($posType, 'calType-Select nicht gefunden');
        self::assertNotFalse($posTask, 'calTaskWrap nicht gefunden');
        self::assertLessThan($posTask, $posType,
            'calType (Projekt/Prozess/Abwesenheit) muss VOR calTaskWrap im DOM stehen - erste Entscheidung des Nutzers');
    }
}
