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
        // Felix-Feedback 2026-11-06: nach Einfuehrung der Legend-Toggles heisst
        // die Sammlung allSeries (series ist die gefilterte Teilmenge).
        $matched = preg_match(
            '/(?:allSeries|series)\.push\(\{\s*(?:key:\s*\'progress\',\s*)?label:\s*i18n\.progress[^}]*\}\);/s',
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

        // View setzt fillBetween mit gruen/rot entsprechend Ist vs. Budget.
        // Felix-Feedback 2026-11-06 ("rot nur wenn ueber budget"): der
        // Ampel-Fill vergleicht ausschliesslich gegen target_h (Budget), nicht
        // gegen die Plan-Matrix-Soll-Kurve. Die Plan-Matrix bleibt als eigene
        // Linie (cyan) sichtbar, faerbt aber die Flaeche nicht ein.
        // Toleriert zwischen b: und belowColor zusaetzliche Legend-Toggle-Keys
        // (belowKey/aboveKey), die mit #71 eingefuehrt wurden.
        $this->assertMatchesRegularExpression(
            '/fillBetween\s*=\s*\{\s*a:\s*istSeries,\s*b:\s*sollSeries,[^}]*belowColor:\s*\'#16a34a\'[^}]*aboveColor:\s*\'#ef4444\'/s',
            $view,
            'Flaechen-Integral (fillBetween) muss Ist gegen Budget (target_h) rendern'
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

    public function testSentimentPayloadReturnsMoodTrend(): void
    {
        // Felix-Feedback 2026-11-03/04: "DAS KOMPLETTE SENTIMENT TRACKING WURDE
        // VORHER HAARKLEIN ERWÄHNT UND GEBETEN ES SO UMZUSETZEN WIE IN DER PRO
        // VARIANTE. ES FEHLT IMMERNOCH KOMPLETT!!!"
        // Backend-Payload fuer den Projekt-Sentiment-Trend: Punkte je Progress-
        // Meldung mit project_mood + customer_mood, plus "latest"-Snapshot.
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, created_at) VALUES (?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), '2026-01-01 00:00:00']
        );
        Repo::saveProjectTracker($pid, 'progress', 'Start', [
            'value'          => 10.0,
            'date_value'     => '2026-03-01',
            'project_mood'   => 3,
            'customer_mood'  => 4,
        ], null);
        Repo::saveProjectTracker($pid, 'progress', 'Zwischenstand', [
            'value'          => 50.0,
            'date_value'     => '2026-06-01',
            'project_mood'   => 2,
            'customer_mood'  => 2,
        ], null);
        // Meldung ohne Moods wird NICHT in den Sentiment-Punkten auftauchen.
        Repo::saveProjectTracker($pid, 'progress', 'Ohne Mood', [
            'value'      => 80.0,
            'date_value' => '2026-09-01',
        ], null);

        $p = cockpit_sentiment_payload($pid);
        self::assertTrue($p['ok']);
        self::assertCount(2, $p['points'], 'Nur Fortschrittsmeldungen mit Mood zaehlen');
        self::assertSame('2026-03-01', $p['points'][0]['d']);
        self::assertSame(3, $p['points'][0]['pm']);
        self::assertSame(4, $p['points'][0]['cm']);
        self::assertSame('2026-06-01', $p['points'][1]['d']);
        self::assertSame(2, $p['points'][1]['pm']);
        self::assertSame(2, $p['points'][1]['cm']);
        // latest-Snapshot zeigt den juengsten Datenpunkt.
        self::assertNotNull($p['latest']);
        self::assertSame('2026-06-01', $p['latest']['d']);
    }

    public function testSentimentPayloadUsesAbsoluteTimeAxisAndInterpolatesGaps(): void
    {
        // Felix-Feedback 2026-11-06: der Sentiment-Verlauf darf Meldungen
        // nicht gleichmaessig stapeln. Backend fuellt labels Tag-fuer-Tag
        // von erster bis letzter Meldung + liefert pm_series/cm_series
        // linear interpoliert und markers an echten Meldetagen.
        $cid = DB::insert('INSERT INTO clients (name, code, created_at) VALUES (?,?,?)',
            ['Acme', 'c-' . uniqid(), '2026-01-01 00:00:00']);
        $pid = DB::insert(
            'INSERT INTO projects (client_id, name, code, created_at) VALUES (?,?,?,?)',
            [$cid, 'P', 'p-' . uniqid(), '2026-01-01 00:00:00']
        );
        // 3 Meldungen mit Abstaenden: 10 Tage, dann 20 Tage. Die mittlere
        // wird ausgelassen (hier: keine Meldung in Tag 11..20), damit die
        // Interpolation sichtbar wird.
        Repo::saveProjectTracker($pid, 'progress', 'A', [
            'value' => 10.0, 'date_value' => '2026-05-01',
            'project_mood' => 5, 'customer_mood' => 5,
        ], null);
        Repo::saveProjectTracker($pid, 'progress', 'B', [
            'value' => 20.0, 'date_value' => '2026-05-11',
            'project_mood' => 1, 'customer_mood' => 3,
        ], null);
        Repo::saveProjectTracker($pid, 'progress', 'C', [
            'value' => 30.0, 'date_value' => '2026-05-31',
            'project_mood' => 5, 'customer_mood' => 1,
        ], null);

        $p = cockpit_sentiment_payload($pid);
        self::assertTrue($p['ok']);
        self::assertArrayHasKey('labels',    $p);
        self::assertArrayHasKey('pm_series', $p);
        self::assertArrayHasKey('cm_series', $p);
        self::assertArrayHasKey('markers',   $p);

        // 31 Tage inkl.: 2026-05-01 .. 2026-05-31
        self::assertCount(31, $p['labels']);
        self::assertSame('2026-05-01', $p['labels'][0]);
        self::assertSame('2026-05-31', $p['labels'][30]);
        self::assertCount(31, $p['pm_series']);
        self::assertCount(31, $p['cm_series']);

        // Marker nur an den drei echten Meldetagen, mit x=Index in labels.
        self::assertCount(3, $p['markers']);
        self::assertSame(0,  $p['markers'][0]['x']);
        self::assertSame(10, $p['markers'][1]['x']);
        self::assertSame(30, $p['markers'][2]['x']);

        // pm_series an den Meldeindizes trifft exakt, an einem Tag dazwischen
        // ist linear interpoliert.
        self::assertSame(5.0, $p['pm_series'][0]);
        self::assertSame(1.0, $p['pm_series'][10]);
        self::assertSame(5.0, $p['pm_series'][30]);
        // Tag 5 (Mitte zwischen 0 und 10): pm = 5 + 0.5 * (1 - 5) = 3
        self::assertEqualsWithDelta(3.0, $p['pm_series'][5], 0.01);
        // Tag 20 (Mitte zwischen 10 und 30): pm = 1 + 0.5 * (5 - 1) = 3
        self::assertEqualsWithDelta(3.0, $p['pm_series'][20], 0.01);
    }

    public function testCalendarHasDailyAndWeeklySentimentPickers(): void
    {
        // Felix-Feedback 2026-11-04: Sentiment-Tracking auch im Kalender,
        // umschaltbar zwischen taeglich und woechentlich.
        $cal   = file_get_contents($this->root() . '/views/entries/calendar_week.php');
        $ctrl  = file_get_contents($this->root() . '/src/controllers/sentiment_ctrl.php');
        $index = file_get_contents($this->root() . '/index.php');
        $this->assertIsString($cal);
        $this->assertIsString($ctrl);
        $this->assertIsString($index);

        // Daily-Picker pro Tag (Pro-Style: ein Current-Emoji-Button + Popover mit 5 Optionen).
        $this->assertStringContainsString('class="cal-sent-day', $cal, 'Daily Mood-Picker im Kalender fehlt');
        $this->assertStringContainsString('class="cal-sent-current', $cal, 'Pro-Style Current-Emoji-Button fehlt (statt stacked buttons)');
        $this->assertStringContainsString('class="cal-sent-popover"', $cal, 'Popover mit 5 Optionen fehlt (Pro-Style)');
        $this->assertStringContainsString("data-dim=\"mood\"", $cal, 'Mood-Dimension fehlt im Daily-Picker');
        $this->assertStringContainsString("data-dim=\"flow\"", $cal, 'Flow-Dimension fehlt im Daily-Picker');

        // Default-Granularitaet ist WEEK (Felix-Feedback 2026-11-04: "default
        // habe ich gesagt ist nicht die tägliche meldung").
        $this->assertStringContainsString("sentimentGranularity ?: 'week'", $cal, 'Default-Granularitaet muss "week" sein, nicht "day"');

        // Weekly-Picker: Pro-Style wie Daily - EIN Current-Button pro Dimension
        // + Popover, Prosa-Hinweis als ⓘ-Tooltip (Felix-Feedback 2026-11-04:
        // "dann haben wir prosatexte hier. das muss in tootltips rein").
        $this->assertStringContainsString('class="cal-sent-week"', $cal, 'Weekly Mood-Picker im Kalender fehlt');
        $this->assertMatchesRegularExpression(
            '/cal-sent-week.*?cal-sent-current/s',
            $cal,
            'Weekly-Picker braucht Pro-Style Current-Button (nicht 5 nebeneinander)'
        );
        $this->assertStringContainsString("tip('cal.sent_week_hint')", $cal, 'Prosa "Setzt die Woche …" muss als ⓘ-Tooltip sitzen, nicht als Text');
        $this->assertStringNotContainsString('class="muted cal-sent-hint"', $cal, 'Alter Prosa-Absatz (.cal-sent-hint) muss weg - Pro-Vorgabe: Tooltip');

        // Toggle Daily/Weekly (admin-only).
        $this->assertStringContainsString('class="cal-sent-toggle"', $cal, 'Granularitaets-Toggle Daily/Weekly fehlt');
        $this->assertStringContainsString('data-g="day"',  $cal, 'Toggle-Option "day" fehlt');
        $this->assertStringContainsString('data-g="week"', $cal, 'Toggle-Option "week" fehlt');

        // Controller + Routes.
        $this->assertStringContainsString('ctrl_sentiment_api_save', $ctrl, 'API-Controller fuer Daily-Save fehlt');
        $this->assertStringContainsString('ctrl_sentiment_api_fill_week', $ctrl, 'API-Controller fuer Weekly-Fill fehlt');
        $this->assertStringContainsString('ctrl_sentiment_granularity_api', $ctrl, 'API-Controller fuer Granularitaet-Toggle fehlt');
        $this->assertStringContainsString("'sentiment_api_save'", $index, 'Route sentiment_api_save fehlt');
        $this->assertStringContainsString("'sentiment_api_fill_week'", $index, 'Route sentiment_api_fill_week fehlt');
        $this->assertStringContainsString("'sentiment_granularity_api'", $index, 'Route sentiment_granularity_api fehlt');
    }

    public function testCockpitHasSentimentCardAndRoute(): void
    {
        $view  = file_get_contents($this->root() . '/views/cockpit/project.php');
        $index = file_get_contents($this->root() . '/index.php');
        $this->assertIsString($view);
        $this->assertIsString($index);

        // Chart-Canvas + Fetch-Route in der View.
        $this->assertStringContainsString('id="cockpitSentiment"', $view, 'Sentiment-Chart-Canvas fehlt');
        $this->assertStringContainsString("cockpit_sentiment_data", $view, 'Sentiment-Endpoint-Route in der View nicht gewired');
        // Timeline nutzt Emojis statt nur "4/5" (Pro-Paritaet).
        $this->assertStringContainsString('Sentiment::moodEmoji((int) $t[\'project_mood\'])', $view, 'Timeline muss Projekt-Mood als Emoji rendern');
        $this->assertStringContainsString('Sentiment::moodEmoji((int) $t[\'customer_mood\'])', $view, 'Timeline muss Kunden-Mood als Emoji rendern');

        // Route registriert.
        $this->assertStringContainsString("'cockpit_sentiment_data'", $index, 'Sentiment-Route nicht in index.php registriert');
        $this->assertStringContainsString("ctrl_cockpit_sentiment_data", $index, 'Sentiment-Controller nicht in index.php verdrahtet');
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

    public function testBurnupSollCurveIsConsumedByChartJs(): void
    {
        // Felix-Feedback 2026-11-06: der Burn-Up bekommt eine Soll-Kurve
        // (= Integral ueber die Plan-Matrix). Die darf nicht stillschweigend
        // aus dem Chart-JS verschwinden - sonst sieht man zwar target_h als
        // flache Linie, aber nicht die Woche-fuer-Woche-Planung.
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');

        // Chart-JS liest d.soll_curve + rendert es als eigene gestrichelte Serie.
        $this->assertStringContainsString(
            'd.soll_curve',
            $view,
            'Chart-JS muss d.soll_curve lesen (Soll-Kurve kommt aus der Burn-Up-Payload)'
        );
        // Soll-Kurve-Farbe: cyan (#06b6d4), NICHT orange (#f59e0b / #d97706).
        // Orange ist fuer die Prognose-Strahlen reserviert - gleiche Farbe fuer
        // zwei verschiedene Semantiken verwirrt (Felix-Feedback 2026-11-06).
        $this->assertMatchesRegularExpression(
            '/label:\s*i18n\.soll,\s*color:\s*\'#06b6d4\'.*?dashed:\s*true.*?data:\s*sollCurve/s',
            $view,
            'Soll-Kurve muss cyan (#06b6d4) gestrichelt gerendert werden - orange kollidiert mit der Prognose'
        );
        $this->assertStringNotContainsString(
            "label: i18n.soll, color: '#f59e0b'",
            $view,
            'Soll-Kurve darf nicht orange sein (reserviert fuer Prognose-Strahlen)'
        );
    }

    public function testBurnupLegendEntriesAreClickableTogglesPersistedToLocalStorage(): void
    {
        // Felix-Feedback 2026-11-06: der Burn-Up braucht klickbare Legend-
        // Eintraege, mit denen man einzelne Kurven ein- und ausblenden kann
        // (Pro-Vorbild). Der Zustand muss ueber Reloads hinweg bleiben -
        // Persistierung ueber localStorage.
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');
        $js   = (string) file_get_contents($this->root() . '/assets/charts.js');
        $css  = (string) file_get_contents($this->root() . '/assets/app.css');

        // charts.js macht Legend-Items zu klickbaren Chips mit data-k=<key>.
        $this->assertMatchesRegularExpression(
            '/class="lg-chip[^"]*"\s*data-k="/s',
            $js,
            'Legend-Items muessen klickbare .lg-chip-Buttons mit data-k sein'
        );

        // CSS kennt den is-off-Zustand (opacity + line-through) und erlaubt
        // Hover-Feedback auf dem Chip.
        $this->assertStringContainsString(
            '.chart-legend .lg-chip.is-off',
            $css,
            'CSS muss den ausgeschalteten Legend-Chip visuell darstellen'
        );
        $this->assertMatchesRegularExpression(
            '/\.chart-legend \.lg-chip\.is-off \.lg-lbl\s*\{\s*text-decoration\s*:\s*line-through/',
            $css,
            'Ausgeschaltete Chips brauchen line-through am Label'
        );

        // View persistiert den hidden-Set in localStorage.
        $this->assertStringContainsString(
            "'tm.cockpit.burnup.hidden'",
            $view,
            'Burn-Up-Legend-Toggle muss ueber den localStorage-Key tm.cockpit.burnup.hidden persistieren'
        );
        $this->assertMatchesRegularExpression(
            '/legend\.addEventListener\(\'click\'.*?lg-chip.*?hidden\.(?:has|add|delete)/s',
            $view,
            'View muss Klicks auf .lg-chip auf toggle(hidden) mappen'
        );

        // Jede Haupt-Serie bekommt einen stabilen Toggle-Key.
        foreach (['ist', 'soll', 'budget', 'progress', 'ideal'] as $k) {
            $this->assertMatchesRegularExpression(
                "/key:\\s*'" . preg_quote($k, '/') . "'/",
                $view,
                "Legend-Toggle-Key '$k' fehlt in der View"
            );
        }
        // fillBetween-Chips (within/over) sind eigene Keys.
        $this->assertStringContainsString("belowKey: 'within'", $view, 'fillBetween braucht belowKey=within');
        $this->assertStringContainsString("aboveKey: 'over'",   $view, 'fillBetween braucht aboveKey=over');

        // charts.js respektiert spec.hidden beim Zeichnen der fillBetween-
        // Flaechen (sonst klickt man den Chip, aber die Flaeche bleibt da).
        $this->assertMatchesRegularExpression(
            '/spec\.hidden.*?hiddenHas\(fb\.(?:belowKey|aboveKey)\)/s',
            $js,
            'charts.js fillBetween-Rendering muss hidden-Set per belowKey/aboveKey respektieren'
        );
    }

    public function testBurnupChartReRendersOnContainerResize(): void
    {
        // Felix-Feedback 2026-11-06: der Canvas verankert seine Pixel-
        // Groesse beim ersten setup() am Container; waechst das Layout
        // (Fenster-Resize, Sidebar-Toggle), zeichnet charts.js ohne
        // weiteren Anstoss NICHT neu - die Kurve bleibt in der alten
        // Pixel-Breite haengen. ResizeObserver am .chart-box triggert
        // ein debounced re-render auf Breitenaenderung.
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');

        // Burn-Up: ResizeObserver am chartBox, re-render per setTimeout.
        $this->assertMatchesRegularExpression(
            '/new ResizeObserver\(function\s*\(\).*?setTimeout\(render/s',
            $view,
            'Burn-Up muss einen ResizeObserver am Container registrieren, der bei Breitenaenderung re-render aufruft'
        );
        // Mindestens die Ignore-wenn-Breite-gleich-Guard, damit Resize-Loops
        // beim re-Render nicht in eine Endlosschleife kippen (clientWidth
        // vor setup-Rundung geprueft).
        $this->assertMatchesRegularExpression(
            '/if\s*\(w\s*===\s*lastW\)\s*return/s',
            $view,
            'Resize-Observer muss gleiche Breite ignorieren (sonst re-render-Loop)'
        );
    }

    public function testBurnupXAxisLabelsAreRotated45Degrees(): void
    {
        // Felix-Feedback 2026-11-06: "regressions test fuer achsen labels.
        // waren mal 45 grad muessen immer 45 grad bleiben". Mit horizontalen
        // Datums-Labels ueberlappen sich die Beschriftungen bei langen
        // Projekten; -45 Grad erlaubt dichte Staffelung ohne Kollision.
        $js = (string) file_get_contents($this->root() . '/assets/charts.js');

        // function lines() (= TmCharts.lines) ist der Chart-Typ hinter dem
        // Cockpit-Burn-Up. Der X-Label-Block darin MUSS -Math.PI/4
        // (= -45 Grad) nutzen.
        $this->assertMatchesRegularExpression(
            '/function\s+lines\s*\(.*?ctx\.rotate\(-Math\.PI\s*\/\s*4\)/s',
            $js,
            'TmCharts.lines X-Axis-Labels muessen um -Math.PI/4 (= -45 Grad) rotiert sein'
        );
        // Der Label-Block nutzt save()/translate()/rotate()/fillText()/restore()
        // - Canvas-Rotation braucht den Zustands-Push, sonst rotiert der Rest
        // des Charts mit.
        $this->assertMatchesRegularExpression(
            '/ctx\.save\(\);\s*ctx\.translate\(xAt\(xi\),\s*padT\s*\+\s*plotH[^)]*\);\s*ctx\.rotate\(-Math\.PI\s*\/\s*4\)/s',
            $js,
            'X-Label-Rotation muss korrekt in save/translate/rotate gewrappt sein'
        );
    }

    public function testBurnupSollCurveI18nParity(): void
    {
        // I18nTest prueft Keys allgemein; hier haelt der Test explizit die
        // neuen Burn-Up-Labels fest, damit sie nicht in einem der zwei Files
        // schleichend verschwinden.
        $de = include $this->root() . '/lang/de.php';
        $en = include $this->root() . '/lang/en.php';
        foreach (['cockpit.burnup_soll', 'cockpit.burnup_target', 'cockpit.burnup_within', 'cockpit.burnup_over'] as $k) {
            $this->assertArrayHasKey($k, $de, "DE-Key fehlt: $k");
            $this->assertArrayHasKey($k, $en, "EN-Key fehlt: $k");
            $this->assertNotEmpty($de[$k]);
            $this->assertNotEmpty($en[$k]);
        }
    }

    public function testPlanMatrixScrollsHorizontallyAndMarksCurrentWeek(): void
    {
        // Felix-Feedback 2026-11-06: die Matrix ist breiter als die Card -
        // sie muss horizontal (und begrenzt vertikal) scrollen, der Header
        // mit "KW"-Prefix tragen, und die aktuelle Kalenderwoche senkrecht
        // eingerahmt sein. Der frueher benutzte Inline-Style
        // style="overflow-x:auto" wurde vom CSP (style-src-attr 'none')
        // blockiert, dadurch lief die Matrix sichtbar ueber den Kartenrand.
        $view = (string) file_get_contents($this->root() . '/views/cockpit/project.php');
        $css  = (string) file_get_contents($this->root() . '/assets/app.css');

        // KW-Prefix im Header (nicht nur die nackte Wochennummer).
        $this->assertMatchesRegularExpression(
            '/KW<\?=\s*h\(substr\(\$wk,\s*-2\)\)\s*\?>/',
            $view,
            'Matrix-Header muss "KW" vor die Wochennummer stellen'
        );

        // Wrapper per Klasse, kein inline overflow-x:auto (CSP blockt das).
        $this->assertStringContainsString(
            'class="plan-matrix-scroll"',
            $view,
            'Matrix braucht einen .plan-matrix-scroll-Wrapper (statt inline style="overflow-x:auto")'
        );
        $this->assertStringNotContainsString(
            'style="overflow-x:auto"',
            $view,
            'Inline style="overflow-x:auto" ist tot - CSP style-src-attr \'none\' blockt es'
        );

        // CSS gibt dem Wrapper horizontalen UND vertikalen Scroll + Max-Hoehe
        // (Felix: "vertikal vielleicht auch auf 5 zeilen begrenzen").
        $this->assertMatchesRegularExpression(
            '/\.plan-matrix-scroll\{[^}]*overflow\s*:\s*auto[^}]*max-height\s*:/',
            $css,
            '.plan-matrix-scroll muss overflow:auto + max-height setzen'
        );

        // Aktuelle Kalenderwoche wird per .is-current-week an Header-Zelle
        // UND jeder Body-Zelle markiert, damit die gesamte Spalte visuell
        // zusammenhaengt.
        $this->assertMatchesRegularExpression(
            '/\$isCurrent\s*\?\s*\' is-current-week\'/',
            $view,
            'th in der aktuellen KW muss Klasse is-current-week tragen'
        );
        $this->assertMatchesRegularExpression(
            '/\$tdClass\s*=\s*\'r plan-cell\'.*?\$isCurrent\s*\?\s*\' is-current-week\'/s',
            $view,
            'td in der aktuellen KW muss Klasse is-current-week tragen'
        );

        // CSS zieht vertikale Schienen links+rechts durch die Spalte - inset
        // box-shadow in --brand ueber die gesamte Zelle (Header + Body-TDs
        // erben das durch den gleichen Klassennamen).
        $this->assertMatchesRegularExpression(
            '/#planMatrix \.is-current-week\{[^}]*box-shadow\s*:\s*inset\s+2px\s+0[^}]*var\(--brand\)/',
            $css,
            '.is-current-week braucht inset box-shadow links+rechts in --brand (vertikaler Rahmen)'
        );
    }
}
