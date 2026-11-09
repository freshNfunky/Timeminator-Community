<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: Layout der Sidepanel-Banner (Felix-Feedback 2026-11-06).
 *
 * Die gebundelten SVG-Banner im Sidepanel sind Portrait 300x600 (1:2). Das
 * Sidepanel ist jedoch ~180 px breit und zieht sich ueber die gesamte
 * verfuegbare Vertikale - mit einer naiven object-fit:cover-Regel + height:
 * 100% wurden sie dadurch massiv gestreckt oder beschnitten.
 *
 * Der Guard:
 *   1. Alle default.json-Banner-SVGs haben konsistent viewBox 300x600
 *      (anderes Verhaeltnis bricht das aspect-ratio-Layout).
 *   2. Die .sp-item.is-image-Regel im CSS begrenzt den Container ueber
 *      aspect-ratio 1/2 und nutzt object-fit:contain, nicht cover.
 */
final class BannerLayoutTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    public function testDefaultFallbackListsTheExpectedImageAndTextCards(): void
    {
        // Felix-Rueckkehr 2026-11-06 Runde 5: die Bild-Banner (XIXUM, AF-AX,
        // AF-AX VTOL, Timeminator Pro) BLEIBEN im default-Set - sie waren
        // kurzzeitig geloescht, sind aber gewollt. Jedes Default-Item hat
        // einen Titel, und Image-Items zeigen auf eine SVG-Datei unter
        // assets/banners/ die tatsaechlich existiert.
        $json = json_decode((string) file_get_contents($this->root() . '/assets/banners/default.json'), true);
        self::assertIsArray($json);
        self::assertNotEmpty($json['items'] ?? []);

        $ids = [];
        foreach ($json['items'] as $item) {
            self::assertNotEmpty(
                $item['title'] ?? '',
                "Default-Item '" . ($item['id'] ?? '?') . "' braucht einen Titel"
            );
            $ids[] = $item['id'] ?? '';
            if (!empty($item['image']) && !str_starts_with((string) $item['image'], 'http')) {
                $svgPath = $this->root() . '/assets/' . $item['image'];
                self::assertFileExists($svgPath, "Image-Default verweist auf nicht existierende Datei: {$item['image']}");
            }
        }

        // Die vier Bild-Banner duerfen nie wieder stillschweigend verschwinden.
        foreach (['default-xixum-hallucination', 'default-af-ax', 'default-af-ax-vtol', 'default-timeminator-pro'] as $expected) {
            self::assertContains(
                $expected,
                $ids,
                "Default-Item '{$expected}' fehlt - die Bild-Banner duerfen nicht geloescht werden"
            );
        }
    }

    public function testSidepanelRotatorFillsFullHeightAndImageRuleStillCorrectForFeedBanners(): void
    {
        // Das Rotator-Layout selbst bleibt unveraendert: er fuellt die Rest-
        // spalte (flex:1 1 auto), und wenn der Feed ein Image-Item liefert,
        // fuellt object-fit:cover die Box. Default-Fallbacks sind aber Text.
        $css = (string) file_get_contents($this->root() . '/assets/app.css');

        self::assertMatchesRegularExpression(
            '/\.sp-rotator\{[^}]*flex\s*:\s*1\s+1\s+auto/',
            $css,
            '.sp-rotator muss flex:1 1 auto sein - fuellt die gesamte Restspalte des Sidepanels'
        );

        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-image img\{[^}]*width\s*:\s*100%[^}]*height\s*:\s*100%[^}]*object-fit\s*:\s*cover/',
            $css,
            '.sp-item.is-image img muss width/height 100% + object-fit:cover haben'
        );
    }

    public function testIframeAllowsSameOriginRelativePath(): void
    {
        // Felix-Feedback 2026-11-06: der Feed soll HTML-Banner liefern. Fuer
        // lokale HTML-Fallbacks (same-origin) muss isAllowedIframe auch
        // relative Pfade erlauben - ohne Scheme, beginnend mit "/", kein "//".
        self::assertTrue(Banners::isAllowedIframe('/Media/banners/zeitcheck.html'));
        self::assertTrue(Banners::isAllowedIframe('/assets/banners/foo.html?v=2'));
        // Aber keine Traversals und keine protocol-relative URLs.
        self::assertFalse(Banners::isAllowedIframe('//evil.example.com/x.html'));
        self::assertFalse(Banners::isAllowedIframe('/Media/banners/../../etc/passwd'));
        // HTTPS-Regeln bleiben wie vorher.
        self::assertTrue(Banners::isAllowedIframe('https://cdn.timeminator.com/a.html'));
        self::assertFalse(Banners::isAllowedIframe('http://timeminator.com/a.html'));
        self::assertFalse(Banners::isAllowedIframe('https://evil.example.com/x.html'));
    }

    public function testIframeBannerBoxFillsTheFullRotator(): void
    {
        // Felix-Feedback 2026-11-06 Runde X: der Sidepanel-Feed liefert
        // HTML-Banner (iframe-Items). Damit das Portraet-Creative die gesamte
        // Sidepanel-Box ausfuellt (kein halb-leeres Feld rechts/links), muss
        // .sp-item.is-iframe padding:0 + overflow:hidden haben und das darin
        // liegende <iframe> width/height 100% ohne Border.
        $css = (string) file_get_contents($this->root() . '/assets/app.css');

        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-iframe\{[^}]*padding\s*:\s*0[^}]*overflow\s*:\s*hidden/',
            $css,
            '.sp-item.is-iframe muss padding:0 + overflow:hidden setzen, sonst erscheint der Rahmen um das HTML-Creative'
        );

        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-iframe iframe\{[^}]*width\s*:\s*100%[^}]*height\s*:\s*100%[^}]*border\s*:\s*0/',
            $css,
            '.sp-item.is-iframe iframe muss width/height 100% + border:0 haben - sonst fuellt es die Box nicht aus'
        );
    }

    public function testSidepanelJsRendersIframeItemsAsSandboxedIframe(): void
    {
        // Wenn ein Feed-Item `iframe` traegt, baut app.js buildCard() einen
        // sandboxed <iframe> statt ein <img>/Text-Fallback. Dieser Pfad darf
        // nicht wegrefactored werden - sonst zeigt der Carousel wieder nur
        // das (gebundelte) SVG-Fallback-Set statt des HTML-Feeds.
        $js = (string) file_get_contents($this->root() . '/assets/app.js');

        self::assertStringContainsString(
            "if (it.iframe)",
            $js,
            'buildCard() muss iframe-Items vor image/text-Items behandeln'
        );
        self::assertStringContainsString(
            "sp-item is-iframe",
            $js,
            'iframe-Container muss die Klasse .sp-item.is-iframe tragen (CSS-Vertrag)'
        );
        self::assertStringContainsString(
            "allow-scripts allow-popups allow-popups-to-escape-sandbox",
            $js,
            'Banner-iframe muss sandboxed sein, aber Scripts und Target-Popups erlauben (sonst keine CTA-Klicks)'
        );
    }

    public function testDefaultBannerEndpointUsesFeedPhp(): void
    {
        // Felix-Entscheidung 2026-11-06: der Feed laeuft jetzt ueber feed.php
        // (byte-identisch zur frueheren feed.json, aber server-seitig steuerbar).
        // Weder die Klassen-Default-Konstante noch die Sample-Config duerfen
        // stillschweigend auf .json zurueckkippen.
        $banners = (string) file_get_contents($this->root() . '/src/Banners.php');
        self::assertMatchesRegularExpression(
            "#DEFAULT_ENDPOINT\s*=\s*'https://assets\.felixschaller\.com/feed\.php'#",
            $banners,
            'Banners::DEFAULT_ENDPOINT muss auf assets.felixschaller.com/feed.php zeigen'
        );

        $sample = (string) file_get_contents($this->root() . '/config.sample.php');
        self::assertStringContainsString(
            "'endpoint' => 'https://assets.felixschaller.com/feed.php'",
            $sample,
            'config.sample.php muss banners.endpoint auf feed.php setzen - sonst bekommt jede frische Installation den alten Default'
        );
    }
}
