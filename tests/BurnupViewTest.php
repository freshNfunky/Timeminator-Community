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

    public function testLegendUsesLineSwatchesNotBlocksOrCircles(): void
    {
        $js  = file_get_contents($this->root() . '/assets/charts.js');
        $css = file_get_contents($this->root() . '/assets/app.css');
        $this->assertIsString($js);
        $this->assertIsString($css);

        // CSS stellt Linien-Swatch-Klassen bereit.
        $this->assertStringContainsString('.lg-line{', $css, 'CSS-Klasse .lg-line (Linien-Swatch) fehlt');
        $this->assertStringContainsString('.lg-line-dashed{', $css, 'CSS-Klasse .lg-line-dashed fehlt');

        // JS verwendet die Linien-Swatch-Klasse in der Legende der Linien-Charts.
        // Wir checken den Legenden-Render-Block innerhalb der lines()-Funktion.
        $matched = preg_match(
            '/function lines\(canvas, spec, legendEl\).*?legendEl\.innerHTML\s*=\s*.*?chart-legend/s',
            $js,
            $match
        );
        $this->assertSame(1, $matched, 'Legenden-Renderblock der lines()-Funktion nicht gefunden');
        $this->assertStringContainsString('lg-line', $match[0], 'Linien-Chart-Legende muss .lg-line-Swatches nutzen (Pro-Vorbild)');

        // Alter Zustand: Kreis-Marker im pointLegend via border-radius:50% - darf nicht mehr vorkommen.
        $this->assertStringNotContainsString(
            "border-radius:50%",
            $match[0],
            'Legenden-Swatches duerfen keine runden Kreis-Marker mehr sein (Pro-Vorbild: Linien-Swatches)'
        );
    }
}
