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

    public function testDefaultFallbackHasNoCroppedImageBanners(): void
    {
        // Felix-Entscheidung 2026-11-06 Runde 4: der Banner-Feed liefert HTML,
        // die lokalen Default-Fallbacks sind reine Text-Cards. Damit croppt
        // nie wieder ein 300x600-SVG an einer 1:4-Sidebar - die Text-Cards
        // fuellen den Container mit Titel/Text/CTA, kein Bildformat-Konflikt.
        $json = json_decode((string) file_get_contents($this->root() . '/assets/banners/default.json'), true);
        self::assertIsArray($json);
        self::assertNotEmpty($json['items'] ?? []);

        foreach ($json['items'] as $item) {
            self::assertArrayNotHasKey(
                'image',
                $item,
                "Default-Item '" . ($item['id'] ?? '?') . "' darf keinen 'image'-Pfad mehr tragen - Bilder croppen in der 1:4-Sidebar"
            );
            self::assertNotEmpty(
                $item['title'] ?? '',
                "Default-Item '" . ($item['id'] ?? '?') . "' braucht einen Titel als Text-Card"
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
}
