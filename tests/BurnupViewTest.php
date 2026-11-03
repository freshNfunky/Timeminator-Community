<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstests fuer die visuelle Darstellung des Burn-Up-Charts.
 *
 * Die Logik im Backend ist durch BurnupDataTest abgedeckt - hier geht es rein darum,
 * dass die View/JS/CSS-Seite nicht zurueck auf alte, visuell falsche Varianten rutscht.
 * Historische Regressionen, die hier abgefangen werden:
 *  - Fortschritts-Linie als Stufen ("step: true") statt linearer Verbindung
 *  - Prognose-Ray-Ende als gefuellter Punkt statt hohlem Kreis
 *  - Vertikal gestapelte Chart-Legende statt horizontaler Anordnung
 *  - Blockfarbige/kreisfoermige Legenden-Swatches statt linienfoermiger Swatches
 */
final class BurnupViewTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    public function testProgressSeriesIsLinearNotStepped(): void
    {
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $this->assertIsString($view);

        // Die Fortschritts-Serie muss im Frontend linear zwischen progress_markers
        // verbunden werden - nicht aus dem step-forward-gefuellten progress_line
        // gezeichnet werden (das sah optisch wie Treppen aus).
        $this->assertMatchesRegularExpression(
            '/marks\s*=\s*\(d\.progress_markers\s*\|\|\s*\[\]\)/',
            $view,
            'Fortschritts-Serie muss aus progress_markers (linear interpoliert) aufgebaut werden'
        );

        // Exakt der Push-Aufruf fuer die Fortschritts-Serie darf kein step:true tragen.
        $matched = preg_match(
            '/series\.push\(\{\s*label:\s*i18n\.progress[^}]*\}\);/s',
            $view,
            $match
        );
        $this->assertSame(1, $matched, 'Fortschritts-Series-Push nicht gefunden');
        $this->assertStringNotContainsString('step:', $match[0], 'Fortschritts-Series darf nicht stepped sein (Pro-Vorbild: lineare Verbindung)');

        // Interpolation ist in der View explizit per linearer Gleichung ausgedrueckt -
        // Regressionsschutz gegen Wiedereinfuehrung des step-forward-Arrays.
        $this->assertMatchesRegularExpression(
            '/marks\[k\]\.y\s*\+\s*t\s*\*\s*\(marks\[k\s*\+\s*1\]\.y\s*-\s*marks\[k\]\.y\)/',
            $view,
            'Lineare Interpolation zwischen progress_markers fehlt'
        );
    }

    public function testForecastRayEndpointIsHollowCircle(): void
    {
        $js = file_get_contents($this->root() . '/assets/charts.js');
        $this->assertIsString($js);

        // Den Ray-RENDER-Block grabben (NICHT den rays.forEach weiter oben, der nur
        // y-Werte fuer die Achsen-Skala sammelt). Erkennung an ctx.moveTo/lineTo + arc.
        $matched = preg_match(
            '/rays\.forEach\(function\(r\)\{(?:(?!rays\.forEach).)*?ctx\.moveTo[^}]*?\}\);/s',
            $js,
            $match
        );
        $this->assertSame(1, $matched, 'Ray-Render-Block in charts.js nicht gefunden');
        $block = $match[0];

        $this->assertStringContainsString('ctx.arc(xAt(r.x1)', $block, 'Ray-Endpunkt-Kreis fehlt');
        $this->assertMatchesRegularExpression(
            '/ctx\.arc\(xAt\(r\.x1\)[^;]*;\s*ctx\.stroke\(\)/',
            $block,
            'Ray-Endpunkt muss mit stroke() (hohl) gezeichnet werden'
        );
        // Es darf im Ray-Block KEIN fill() nach dem arc() mehr geben.
        $this->assertDoesNotMatchRegularExpression(
            '/ctx\.arc\(xAt\(r\.x1\)[^;]*;\s*ctx\.fill\(\)/',
            $block,
            'Ray-Endpunkt darf nicht gefuellt (ctx.fill) sein - muss hohler Kreis bleiben'
        );
    }

    public function testChartLegendIsHorizontal(): void
    {
        $css = file_get_contents($this->root() . '/assets/app.css');
        $this->assertIsString($css);

        // Pro-Vorbild-Regel: chart-legend flex-row, nicht chart-legend-grid.
        $this->assertMatchesRegularExpression(
            '/\.chart-legend:not\(\.chart-legend-grid\)\{[^}]*display\s*:\s*flex[^}]*flex-wrap\s*:\s*wrap[^}]*\}/',
            $css,
            'Chart-Legende muss horizontal (display:flex; flex-wrap:wrap) layouten'
        );
    }

    public function testLegendUsesSvgSwatchesWithLineAndMarker(): void
    {
        $js  = file_get_contents($this->root() . '/assets/charts.js');
        $css = file_get_contents($this->root() . '/assets/app.css');
        $this->assertIsString($js);
        $this->assertIsString($css);

        // CSS stellt die SVG-Swatch-Klassen bereit (fuer Linien und Flaechen).
        $this->assertStringContainsString('.lg-svg{', $css, 'CSS-Klasse .lg-svg fehlt');
        $this->assertStringContainsString('.lg-area-rect{', $css, 'CSS-Klasse .lg-area-rect (SVG-Flaechen-Swatch) fehlt');

        // Legenden-Render-Block der lines()-Funktion grabben.
        $matched = preg_match(
            '/function lines\(canvas, spec, legendEl\).*?legendEl\.innerHTML\s*=\s*.*?chart-legend/s',
            $js,
            $match
        );
        $this->assertSame(1, $matched, 'Legenden-Renderblock der lines()-Funktion nicht gefunden');
        $block = $match[0];

        // Swatch wird als inline-SVG erzeugt - mit Linie und optionalem Marker (dot/ring).
        $this->assertStringContainsString('<svg class="lg-svg"', $block, 'Legenden-Swatch muss als SVG (.lg-svg) gerendert werden');
        $this->assertStringContainsString("marker === 'dot'", $block, 'Marker-Support "dot" (gefuellter Punkt fuer Fortschritt) fehlt');
        $this->assertStringContainsString("marker === 'ring'", $block, 'Marker-Support "ring" (hohler Kreis fuer Prognose) fehlt');

        // Alter Zustand: Kreis-Marker im pointLegend via border-radius:50% - darf nicht mehr vorkommen.
        $this->assertStringNotContainsString(
            "border-radius:50%",
            $block,
            'Legenden-Swatches duerfen keine runden CSS-Kreis-Marker mehr sein'
        );
    }

    public function testBurnupHasBudgetAreaFill(): void
    {
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $js   = file_get_contents($this->root() . '/assets/charts.js');
        $this->assertIsString($view);
        $this->assertIsString($js);

        // View setzt fillBetween mit gruen/rot entsprechend Ist vs. Soll.
        $this->assertMatchesRegularExpression(
            '/fillBetween\s*=\s*\{\s*a:\s*istSeries,\s*b:\s*sollSeries,\s*belowColor:\s*\'#16a34a\',\s*aboveColor:\s*\'#ef4444\'/s',
            $view,
            'Flaechen-Integral (fillBetween) fehlt in der Burn-Up-View'
        );

        // Charts-Engine hat fillBetween-Rendering (Pro-Vorbild): quadweise, inkl. Interpolation.
        $this->assertStringContainsString('spec.fillBetween', $js, 'TmCharts.lines kennt fillBetween nicht');
        $this->assertMatchesRegularExpression(
            '/fb\.a.*?fb\.b.*?fb\.aboveColor.*?fb\.belowColor/s',
            $js,
            'fillBetween-Renderblock in charts.js fehlt (aboveColor/belowColor)'
        );
    }

    public function testAreaSwatchIsCspCleanSvgRect(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03): der Flaechen-Swatch
        // war ein <span> mit inline style="background:#..." - style-src-attr 'none'
        // (CSP) blockiert das vollstaendig, der Swatch verschwand im Dark-Mode
        // komplett. Fix: SVG-<rect> mit fill-Attribut und theme-abhaengiger
        // fill-opacity ueber CSS-Klasse.
        $js  = file_get_contents($this->root() . '/assets/charts.js');
        $css = file_get_contents($this->root() . '/assets/app.css');
        $this->assertIsString($js);
        $this->assertIsString($css);

        $matched = preg_match('/var areaSwatch\s*=\s*function\(color\)\{(.*?)\};/s', $js, $match);
        $this->assertSame(1, $matched, 'areaSwatch-Funktion in charts.js nicht gefunden');
        $block = $match[1];

        $this->assertStringContainsString('<svg', $block, 'Flaechen-Swatch muss als SVG gerendert werden (nicht als inline-styled span)');
        $this->assertStringContainsString('<rect', $block, 'Flaechen-Swatch muss ein SVG-<rect> enthalten');
        $this->assertStringContainsString('class="lg-area-rect"', $block, 'SVG-<rect> braucht die CSS-Klasse .lg-area-rect fuer Theme-Opacity');
        $this->assertStringContainsString('fill="\' + color + \'"', $block, 'Farbe muss als SVG-Attribut fill="..." gesetzt werden (nicht als CSS style)');
        $this->assertDoesNotMatchRegularExpression(
            '/style\s*=\s*[\'"]background/',
            $block,
            'Kein inline style="background:..." - sonst blockiert style-src-attr CSP die Farbe'
        );

        // CSS-Regel: fill-opacity theme-abhaengig (Light halbtransparent, Dark voll).
        $this->assertStringContainsString('.lg-area-rect{fill-opacity:.55', $css, 'Light-Mode fill-opacity fuer .lg-area-rect fehlt');
        $this->assertMatchesRegularExpression(
            '/(?:prefers-color-scheme:\s*dark|data-theme="dark"]).*?\.lg-area-rect\{fill-opacity:1\}/s',
            $css,
            'Dark-Mode fill-opacity:1 fuer .lg-area-rect fehlt'
        );
    }

    public function testTrackerEntryCardHasProgressFormAndDateShiftForm(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03: "tracker eingabe
        // erlaubt aktuell nur terminverschiebung. das ist ein downgrade"):
        // der Tracker-Eintrag-Bereich muss Fortschritts-Meldung UND
        // Terminverschiebung anbieten, Pro-Typen als deaktivierte Teaser.
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $this->assertIsString($view);

        // Typ-Switcher vorhanden (Pro-UI-Paritaet).
        $this->assertStringContainsString('id="trackerType"', $view, 'Tracker-Typ-Switcher (#trackerType) fehlt');

        // Fortschritts-Formular mit Pflicht-Progress-Wert, Prognose, Moods, Kommentar.
        // Attribut-Reihenfolge ist egal - pruefen, dass beide Tokens im selben <form> stehen.
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*project_tracker_save[^"]*"[^>]*data-tracker-type="progress"|<form[^>]*data-tracker-type="progress"[^>]*action="[^"]*project_tracker_save/s',
            $view,
            'Fortschritts-Formular (data-tracker-type="progress") fehlt'
        );
        $this->assertStringContainsString('name="value"',           $view, 'Fortschritt-Feld (value) fehlt');
        $this->assertStringContainsString('name="value_forecast"',  $view, 'Prognose-Feld (value_forecast) fehlt');
        $this->assertStringContainsString('name="project_mood"',    $view, 'Projekt-Stimmung-Feld fehlt');
        $this->assertStringContainsString('name="customer_mood"',   $view, 'Kunden-Stimmung-Feld fehlt');

        // Terminverschiebungs-Formular bleibt bestehen (data-tracker-type="date_shift").
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*cockpit_date_shift_save[^"]*"[^>]*data-tracker-type="date_shift"|<form[^>]*data-tracker-type="date_shift"[^>]*action="[^"]*cockpit_date_shift_save/s',
            $view,
            'Terminverschiebungs-Formular (data-tracker-type="date_shift") fehlt'
        );
    }

    public function testTrackerFormHiddenRuleBeatsRowFlex(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03: "terminverschiebung
        // geklickt. die maske veraendert sich nicht"): das Umschalten setzte
        // zwar das [hidden]-Attribut, die .row-Klasse erzwang aber display:flex
        // mit hoeherer Specificity - die versteckten Forms blieben sichtbar.
        // Fix: explizite CSS-Regel mit !important fuer .tracker-form[hidden].
        $css = file_get_contents($this->root() . '/assets/app.css');
        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.tracker-form\[hidden\]\{display\s*:\s*none\s*!important\}/',
            $css,
            'Fehlende CSS-Regel: .tracker-form[hidden] muss display:none !important sein, sonst beisst .row{display:flex} das native hidden'
        );
    }

    public function testTrackerEntryExposesProTypesAsDisabledOptions(): void
    {
        // Kaufmaennische und PM-Typen aus der Pro-Variante muessen im
        // Switcher sichtbar aber deaktiviert sein, damit User wissen, was
        // in Pro verfuegbar ist - ohne die Backend-Pfade ausbauen zu muessen.
        // Felix-Feedback 2026-11-03: Meeting-Protokolle gehoeren NICHT zur
        // Pro-Variante - Kundenmeetings sind eine Community-Grundfunktion.
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $this->assertIsString($view);

        // Die sechs tatsaechlichen Pro-Typen sind im $proTypes-Array.
        foreach (['risk', 'decision', 'cost', 'budget_increase', 'invoice_sent', 'invoice_payment'] as $proType) {
            $this->assertMatchesRegularExpression(
                "/'" . preg_quote($proType, '/') . "'\s*=>\s*t\(/",
                $view,
                "Pro-Typ \"$proType\" fehlt im \$proTypes-Array der Tracker-Entry-Card"
            );
        }
        // Options werden aus dem Array heraus als disabled gerendert.
        $this->assertStringContainsString(
            'disabled data-pro="1"',
            $view,
            'Pro-Typen muessen im Switcher als disabled mit data-pro="1" gerendert werden'
        );
        // Meeting darf NICHT im Pro-Array stehen - es ist ein aktiver Community-Typ.
        $this->assertDoesNotMatchRegularExpression(
            "/'meeting'\s*=>\s*t\(/",
            $view,
            'Meeting-Protokoll darf nicht im Pro-Teaser-Array stehen - es ist Community-Grundfunktion'
        );
    }

    public function testMeetingTrackerIsActiveCommunityType(): void
    {
        // Felix-Feedback 2026-11-03: "kundenmeeting ist nicht pro / fehlt zudem
        // hier in der community variante" - Meeting-Protokoll muss als aktives
        // Formular im Switcher auftauchen und ueber den normalen tracker-save-
        // Endpoint persistieren.
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $src  = file_get_contents($this->root() . '/src/Repo.php');
        $this->assertIsString($view);
        $this->assertIsString($src);

        // Switcher-Option fuer meeting (aktiv, NICHT disabled).
        $this->assertMatchesRegularExpression(
            '/<option\s+value="meeting">/',
            $view,
            'Meeting-Switcher-Option fehlt oder ist nicht aktiv'
        );

        // Aktives Meeting-Formular mit data-tracker-type="meeting".
        $this->assertStringContainsString(
            'data-tracker-type="meeting"',
            $view,
            'Meeting-Formular (data-tracker-type="meeting") fehlt'
        );
        $this->assertStringContainsString(
            'name="type" value="meeting"',
            $view,
            'Meeting-Formular braucht hidden type="meeting"'
        );
        $this->assertStringContainsString('name="meeting_title"',        $view, 'Meeting-Titel-Feld fehlt');
        $this->assertStringContainsString('name="meeting_participants"', $view, 'Meeting-Teilnehmer-Feld fehlt');

        // Repo erlaubt 'meeting' als Community-Tracker-Typ, sonst wuerde es auf status_report fallen.
        $this->assertMatchesRegularExpression(
            "/TRACKER_TYPES_COMMUNITY\s*=\s*\[[^\]]*'meeting'/",
            $src,
            'meeting fehlt in TRACKER_TYPES_COMMUNITY'
        );
    }

    public function testProgressTrackerControllerPersistsMoodsAndAsOf(): void
    {
        // Historische Regression: der Save-Pfad fuer Fortschrittsmeldungen
        // muss project_mood, customer_mood und das date_value (Stand-Datum)
        // bis in die project_trackers-Tabelle durchreichen.
        $src = file_get_contents($this->root() . '/src/controllers/structure_ctrl.php');
        $this->assertIsString($src);

        $this->assertStringContainsString(
            "\$extra['project_mood']",
            $src,
            'ctrl_project_tracker_save reicht project_mood nicht ins $extra-Array durch'
        );
        $this->assertStringContainsString(
            "\$extra['customer_mood']",
            $src,
            'ctrl_project_tracker_save reicht customer_mood nicht durch'
        );
        $this->assertStringContainsString(
            "\$extra['date_value'] = \$asOf",
            $src,
            'ctrl_project_tracker_save reicht date_value (as_of) nicht durch'
        );
    }

    public function testPlanMatrixWeekRangeSpansSinceProjectStart(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03: "muss alle wochen
        // bearbeiten koennen seit beginn"): die Matrix zeigte nur 13 Wochen
        // rueckwaerts - aeltere Wochen waren nicht erreichbar. Fix: der
        // Controller leitet $from aus dem frueheren von Buchung / planned_start /
        // kickoff_at ab (statt fix today-12w), und $to aus max(today+4w,
        // planned_end).
        $src = file_get_contents($this->root() . '/src/controllers/cockpit_ctrl.php');
        $this->assertIsString($src);

        // Fruehestes Startdatum wird aus Buchungen + planned_start + kickoff_at zusammengefuehrt.
        $this->assertMatchesRegularExpression(
            '/\$firstBooking\s*=\s*DB::scalar\([^;]*MIN\(DATE\(start_ts\)\)/s',
            $src,
            'Matrix-Bereich muss das frueheste Buchungsdatum ermitteln'
        );
        $this->assertMatchesRegularExpression(
            '/\$starts\s*=\s*array_filter\(\[\s*\$firstBooking,\s*\$project\[\'planned_start\'\]/s',
            $src,
            'Matrix-Startkandidaten (Buchung + planned_start + kickoff_at) fehlen'
        );
        $this->assertStringContainsString(
            'kickoff_at',
            $src,
            'kickoff_at als Startkandidat fehlt'
        );

        // Der Horizont voraus muss mindestens planned_end erreichen.
        $this->assertMatchesRegularExpression(
            '/if\s*\(!empty\(\$project\[\'planned_end\'\]\).*\$to\s*=\s*\(string\)\s*\$project\[\'planned_end\'\]/s',
            $src,
            'Matrix-Ende muss planned_end abdecken'
        );

        // Der alte Fix-13-Wochen-Rueckblick ist nicht mehr der einzige Startpunkt.
        $this->assertDoesNotMatchRegularExpression(
            '/\$from\s*=\s*\$today->modify\(\'monday this week\'\)->modify\(\'-12 weeks\'\)/',
            $src,
            'Fixer 13-Wochen-Rueckblick darf nicht mehr der einzige Startpunkt der Matrix sein'
        );
    }

    public function testPlanMatrixHasIstSollColorCoding(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03: "keine
        // farbkodierung von soll und ist - siehe provariante"): die Plan-
        // Matrix muss Zellen nach Ist/Soll-Verhaeltnis einfaerben:
        // rot wenn Ist > 1.15*Soll, orange wenn 1.0 < Ratio <= 1.15,
        // gruen wenn Ist <= Soll (bei Soll > 0). Soll=0 und Ist>0 → orange.
        $view = file_get_contents($this->root() . '/views/cockpit/project.php');
        $this->assertIsString($view);

        // Alle drei Ampel-Klassen tauchen im Zell-Rendering auf.
        $this->assertStringContainsString("'cell-red'",    $view, 'cell-red fehlt in der Matrix-Farbkodierung');
        $this->assertStringContainsString("'cell-orange'", $view, 'cell-orange fehlt in der Matrix-Farbkodierung');
        $this->assertStringContainsString("'cell-green'",  $view, 'cell-green fehlt in der Matrix-Farbkodierung');

        // Ratio-Logik ist explizit per Ampel-Schwellen (keine stumme Gleichheit).
        $this->assertMatchesRegularExpression(
            '/\$ratio\s*=\s*\$ist\s*\/\s*\$soll/s',
            $view,
            'Ist/Soll-Ratio wird nicht berechnet'
        );
        $this->assertMatchesRegularExpression(
            '/ratio\s*>\s*1\.15.*?cell-red/s',
            $view,
            'Grenze 1.15 fuer cell-red fehlt'
        );
        $this->assertMatchesRegularExpression(
            '/ratio\s*>\s*1\.0.*?cell-orange/s',
            $view,
            'Grenze 1.0 fuer cell-orange fehlt'
        );

        // Die td-Zelle traegt data-ist + data-soll, damit JS nach einem Save die
        // Farbe live aktualisieren kann (ohne Reload).
        $this->assertStringContainsString(
            'data-ist="<?= (int) $ist ?>"',
            $view,
            'plan-cell braucht data-ist fuer JS-Farbaktualisierung'
        );
        $this->assertStringContainsString(
            'data-soll="<?= (int) $soll ?>"',
            $view,
            'plan-cell braucht data-soll fuer JS-Farbaktualisierung'
        );

        // JS entfernt alte Klassen und setzt die passende nach Save neu.
        $this->assertStringContainsString(
            "classList.remove('cell-green', 'cell-orange', 'cell-red')",
            $view,
            'JS entfernt Ampel-Klassen nicht vor dem Neu-Setzen'
        );
    }

    public function testPlanMatrixSavesCsrfUnderCorrectFieldName(): void
    {
        // Historische Regression (Felix-Feedback 2026-11-03): der Plan-Matrix-JS
        // sendete das Token als "csrf_token", der Server (csrf_check in
        // src/helpers.php) erwartet aber "_csrf" - jeder Save brach mit 419 ab,
        // und die Matrix persistierte nicht.
        $view    = file_get_contents($this->root() . '/views/cockpit/project.php');
        $helpers = file_get_contents($this->root() . '/src/helpers.php');
        $this->assertIsString($view);
        $this->assertIsString($helpers);

        // Server-seitiger Feldname bleibt "_csrf".
        $this->assertStringContainsString(
            "post('_csrf')",
            $helpers,
            'csrf_check darf nur den Feldnamen "_csrf" verlangen'
        );

        // Den Plan-Matrix-Save-Script-Block grabben (planMatrix + fetch).
        $matched = preg_match(
            '/document\.getElementById\(\'planMatrix\'\).*?fetch\(url/s',
            $view,
            $match
        );
        $this->assertSame(1, $matched, 'Plan-Matrix-Script-Block nicht gefunden');
        $block = $match[0];

        $this->assertMatchesRegularExpression(
            "/body\\.set\\('_csrf',\\s*token\\)/",
            $block,
            'Plan-Matrix muss das CSRF-Token unter dem Feldnamen "_csrf" senden'
        );
        $this->assertStringNotContainsString(
            "body.set('csrf_token'",
            $block,
            'Alter Feldname "csrf_token" darf nicht mehr verwendet werden - Server ignoriert ihn'
        );
    }
}
