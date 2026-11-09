<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #150): Pro-Parity-Vertrag im Overlay-
 * Dialog festzurren.
 *
 * 1) Formular hat die komplette Hierarchie: Typ / Projektart / Kunde /
 *    Projekt / Arbeitspaket / Aufgabe / Notiz - plus Start/Ende fuer
 *    Projekt+Prozess bzw. Von/Bis fuer Abwesenheit.
 * 2) Kunde-Feld verhaelt sich projektart-abhaengig:
 *    - Internes Projekt: entfaellt komplett (hidden)
 *    - Foerderprojekt: Label wird zu "Projekttraeger" (client_label-
 *      Override aus project_categories)
 *    - Kundenprojekt: Default-Label "Kunde"
 * 3) Hierarchie wird strikt durchgesetzt - Child-Selects sind disabled,
 *    bis der Parent gewaehlt ist (flache Hierarchie verhindert).
 * 4) Prosa-Hinweis "Waehle Kunde und Projekt oben" entfaellt in
 *    tp-new-task - Enforce-Logik erzwingt die Reihenfolge ohnehin.
 */
final class EntryDialogHierarchyTest extends TestCase
{
    private function dialog(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/entries/index.php');
    }

    private function picker(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/views/partials/task_picker.php');
    }

    private function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
    }

    private function appjs(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.js');
    }

    public function testDialogContainsAllRequiredFieldsInRequiredOrder(): void
    {
        $view = $this->dialog();
        // Reihenfolge-Pruefung: Typ -> calTaskWrap (Projektart + Kunde +
        // Projekt + Arbeitspaket + Aufgabe) -> Zeit/Datum -> Notiz.
        $posType = strpos($view, 'id="calType"');
        $posTask = strpos($view, 'id="calTaskWrap"');
        $posNote = strpos($view, 'id="calNote"');
        self::assertNotFalse($posType, 'Typ-Select fehlt');
        self::assertNotFalse($posTask, 'calTaskWrap fehlt');
        self::assertNotFalse($posNote, 'Notiz-Feld fehlt');
        self::assertLessThan($posTask, $posType, 'Typ muss vor dem Task-Wrap stehen');
        self::assertLessThan($posNote, $posTask, 'Task-Wrap muss vor Notiz stehen');

        // Projektart-Dropdown im Dialog.
        self::assertStringContainsString('class="tp-cat"', $view,
            'Projektart-Select fehlt im Dialog');
        // Task-Picker-Partial mit allen vier Kaskaden-Selects.
        $picker = $this->picker();
        self::assertStringContainsString('class="tp-client"',  $picker);
        self::assertStringContainsString('class="tp-project"', $picker);
        self::assertStringContainsString('class="tp-wp"',      $picker);
        self::assertStringContainsString('class="tp-task"',    $picker);
    }

    public function testDialogDataExposesCategoryConfigForClientLabelSwitching(): void
    {
        $view = $this->dialog();
        // projectCats muss needs_client + client_label + ckey pro id liefern.
        self::assertStringContainsString("'needs_client'", $view,
            'projectCats muss needs_client durchreichen');
        self::assertStringContainsString("'client_label'", $view,
            'projectCats muss client_label durchreichen (fuer Foerderprojekt-Label)');
    }

    public function testJsHidesClientForInternalProjectsAndSwapsLabelForFoerder(): void
    {
        $js = $this->js();
        // Fix #157: Logik ist jetzt HARDCODED nach ckey. Die 3 Default-
        // Kategorien tragen feste Semantik; andere Kategorien werden wie
        // "kunde" behandelt. Pro-Issue 478 (M1.4) parkt die dynamische
        // Config.
        self::assertStringContainsString('HARDCODED_CAT_CONFIG', $js,
            'Hardcode-Config fuer Projektart-Logik fehlt (Fix #157)');
        self::assertMatchesRegularExpression(
            "~'intern':\\s*\\{needsClient:\\s*false~",
            $js,
            'intern muss needsClient:false hardcoded sein'
        );
        self::assertMatchesRegularExpression(
            "~'foerderung':\\s*\\{needsClient:\\s*true,\\s*clientLabel:\\s*'Projekttraeger'~",
            $js,
            'foerderung muss clientLabel:Projekttraeger hardcoded sein'
        );
        self::assertMatchesRegularExpression(
            "~'kunde':\\s*\\{needsClient:\\s*true~",
            $js,
            'kunde (Kundenprojekt) muss als Default-Variante im hardcoded config stehen'
        );
        self::assertStringContainsString('clientLabel.hidden = !needsClient', $js,
            'Internes Projekt muss das Kunde-Label komplett verstecken');
        // Partial trainiert den Dialog durch data-base-label-Attribut:
        $picker = $this->picker();
        self::assertStringContainsString('tp-client-label', $picker,
            'Marker-Klasse fuer das umschaltbare Kunde-Label fehlt');
        self::assertStringContainsString('data-base-label', $picker,
            'data-base-label fuer Reset auf "Kunde" fehlt');
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #157): Admin-eigene Projektarten
     * (alle ckeys ausser kunde/intern/foerderung) werden wie Kundenprojekt
     * behandelt - Kunde sichtbar, Label "Kunde". Pro-Issue #478 (M1.4)
     * macht die Logik dynamisch, Community bleibt bis dahin hardcoded.
     */
    public function testUnknownCategoryCkeysFallBackToKundeSemantics(): void
    {
        $js = $this->js();
        self::assertStringContainsString("HARDCODED_CAT_CONFIG[ckey] || HARDCODED_CAT_CONFIG['kunde']", $js,
            'catConfig muss unbekannte ckeys auf kunde-Default mappen');
    }

    public function testHierarchyEnforceAllowsProjectWhenClientFieldIsHidden(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/app.js');
        // Fix #156: wenn Kunde-Feld durch Projektart (needs_client=false)
        // komplett ausgeblendet ist, darf der Hierarchie-Enforce das
        // Projekt-Dropdown NICHT mit "Zuerst Kunde waehlen" sperren.
        self::assertStringContainsString('clientSkipped', $js,
            'enforceHierarchy braucht einen clientSkipped-Flag');
        self::assertMatchesRegularExpression(
            '~!clientSkipped\s*&&\s*!client\.value~',
            $js,
            'Projekt-Lock soll nur greifen wenn Kunde-Feld sichtbar und leer ist'
        );
    }

    public function testTaskPickerEnforcesHierarchyThroughDisabledChildren(): void
    {
        $js = $this->appjs();
        self::assertStringContainsString('function enforceHierarchy', $js,
            'enforceHierarchy-Funktion fehlt im Task-Picker-JS');
        self::assertStringContainsString("'Zuerst Kunde waehlen'", $js,
            'Placeholder "Zuerst Kunde waehlen" fehlt');
        self::assertStringContainsString("'Zuerst Projekt waehlen'", $js,
            'Placeholder "Zuerst Projekt waehlen" fehlt');
        self::assertStringContainsString('sel.disabled = lock', $js,
            'Lock-State muss Child-Select wirklich disabled setzen');
    }

    public function testNewTaskHintProseIsRemoved(): void
    {
        $picker = $this->picker();
        self::assertStringNotContainsString(
            "t('tp.new_task_hint'",
            $picker,
            'Prosa-Hinweis "Waehle Kunde und Projekt oben" muss raus - Enforce-Logik erzwingt die Reihenfolge ohnehin'
        );
        self::assertStringNotContainsString(
            'class="tp-new-task-hint',
            $picker,
            'Markup-Element fuer den Prosa-Hinweis darf nicht mehr gerendert werden'
        );
    }

    /**
     * Felix-Feedback 2026-11-09 (#151): Bei Prozess UND Abwesenheit soll
     * der komplette Projekt-Hierarchie-Teil (Projektart/Kunde/Projekt/
     * Arbeitspaket/Aufgabe) ausgeblendet werden. Bei Abwesenheit entfaellt
     * zusaetzlich Start/Ende (Datetime), bei Projekt + Prozess entfaellt
     * Von/Bis (Date-only).
     *
     * Der JS setzt [hidden], aber mein neu eingefuehrtes
     * .cal-dialog-task-picker{display:flex} ueberschreibt das Default-
     * [hidden]-Styling mit gleicher Spezifitaet. Deshalb muss der
     * Hidden-Zustand im CSS explizit wiederhergestellt werden.
     */
    public function testHiddenAttributeOnDialogSectionsForcesDisplayNone(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertStringContainsString('#calTaskWrap[hidden]{display:none}', $css,
            'calTaskWrap[hidden] muss explizit display:none bekommen - der Projekt-Hierarchie-Teil darf bei Prozess/Abwesenheit nicht sichtbar bleiben');
        self::assertStringContainsString('#calHoursWrap[hidden]', $css,
            'calHoursWrap[hidden] muss explizit display:none bekommen - Stunden-Feld nur bei Ueberstundenabbau');
        self::assertStringContainsString('#calTimeRow[hidden]', $css,
            'calTimeRow[hidden] (Start/Ende) muss bei Abwesenheit komplett verschwinden');
        self::assertStringContainsString('#calDateRow[hidden]', $css,
            'calDateRow[hidden] (Von/Bis) muss bei Projekt + Prozess verschwinden');
        // Fix #156: Kunde-Label muss bei Internem Projekt per hidden=true
        // wirklich verschwinden - .cal-dialog-task-picker > label{display:
        // flex} ueberschreibt sonst das Default [hidden]{display:none}.
        self::assertMatchesRegularExpression(
            '~\.task-picker\s+\.tp-client-label\[hidden\]\s*\{\s*display:\s*none|\.cal-dialog-task-picker\s*>\s*label\[hidden\][^{]*\{\s*display:\s*none~s',
            $css,
            'Kunden-Label muss bei hidden=true via CSS wirklich verschwinden (Fix #156)'
        );
    }

    public function testJsHideLogicCoversProcessAndAbsence(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
        // taskWrap wird nur bei Projekt-Zeit angezeigt (Prozess + Abwesenheit
        // kriegen hidden=true).
        self::assertStringContainsString("taskWrap.hidden = (k !== 'project')", $js,
            'Task-Picker muss bei Prozess + Abwesenheit hidden sein');
        // Stunden-Feld nur bei Abwesenheit + overtime-Konto.
        self::assertStringContainsString("k === 'absence' && absenceAccountOf(v) === 'overtime'", $js,
            'Stunden-Feld nur bei Ueberstundenabbau');
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #158): Projektart-Filter greift nicht,
     * weil applyFilters() in app.js den o.hidden-State der Projekte
     * ueberschreibt. Jetzt wird wantCkey am picker-Root als data-cat-filter
     * persistiert und applyFilters liest + filtert zusaetzlich.
     */
    public function testProjectDropdownRespectsCategoryFilterAcrossCascadeUpdates(): void
    {
        $js = $this->js();
        self::assertStringContainsString("taskPicker.setAttribute('data-cat-filter'", $js,
            'calendar.js muss den wantCkey am Picker-Root persistieren');
        $appJs = (string) file_get_contents(dirname(__DIR__) . '/assets/app.js');
        self::assertStringContainsString("root.getAttribute('data-cat-filter')", $appJs,
            'applyFilters muss data-cat-filter vom Root einlesen');
        self::assertMatchesRegularExpression(
            "~catFilterCkey\\s*!==\\s*''\\s*&&\\s*o\\.getAttribute\\('data-cat-ckey'\\)\\s*!==\\s*catFilterCkey~",
            $appJs,
            'Projekt-Filter muss cat-ckey beruecksichtigen, sonst werden alle Projekte sichtbar'
        );
    }
}
