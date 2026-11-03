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
