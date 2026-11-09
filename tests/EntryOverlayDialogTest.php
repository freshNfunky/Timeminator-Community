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
    public function testDashboardAddManualLinksToEntriesCalendar(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/dashboard.php');
        // Hardening 6: Dashboard-Button fuehrt NUR auf den Kalender,
        // nicht mehr per Deep-Link &new=1. Der Deep-Link hat bewirkt,
        // dass jeder reload den Dialog wieder mit heute/09:00-10:00
        // vorbelegt und den Grid-Drag ueberschrieben hat.
        self::assertStringContainsString(
            "route('entries', ['view' => 'calendar'])",
            $view,
            'Dashboard-Button muss zu entries?view=calendar verlinken (ohne Deep-Link)'
        );
        self::assertStringNotContainsString(
            "'new' => 1",
            $view,
            'Dashboard-Link darf keinen new=1-Deep-Link mehr setzen'
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
        // Hardening 6 (2026-11-09): "Neu"-Fall OHNE new=1 - der Deep-Link
        // hat dazu gefuehrt, dass jeder reload den Dialog erneut mit
        // heute/09:00-10:00 vorbelegt und den Grid-Drag ueberschrieb.
        // Jetzt landet der Nutzer auf dem Kalender und draggt selbst.
        self::assertStringContainsString(
            "redirect_route('entries', ['view' => 'calendar']);",
            $ctrl,
            'Neu-Fall muss zum Kalender redirecten (ohne new=1-Deep-Link)'
        );
        self::assertStringNotContainsString(
            "redirect_route('entries', ['view' => 'calendar', 'new' => 1])",
            $ctrl,
            'new=1 darf nicht mehr zwangsweise vom Controller gesetzt werden'
        );
    }

    public function testCalendarJsHandlesEditIdParam(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        self::assertStringContainsString("params.get('edit')", $js,
            'calendar.js muss auch den edit-Query-Param pruefen');
        // Hardening 5: deep-link geht ueber sessionStorage-Flag, damit
        // Save-Reload nicht erneut triggert. openForEntry wird mit
        // parseInt(pendingEdit, 10) gerufen, weil das Flag den String traegt.
        self::assertMatchesRegularExpression(
            '~openForEntry\(\s*(editId|parseInt\(pendingEdit,\s*10\))\s*\)~',
            $js,
            'Bei edit=X muss openForEntry den Bestandseintrag im Overlay oeffnen (direkt oder via sessionStorage-Flag)'
        );
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
