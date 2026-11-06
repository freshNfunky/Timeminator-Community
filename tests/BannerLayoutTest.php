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

    public function testAllShippedBannerSvgsUseThePortraitViewBox(): void
    {
        $manifestPath = $this->root() . '/assets/banners/default.json';
        $json = json_decode((string) file_get_contents($manifestPath), true);
        self::assertIsArray($json);
        self::assertNotEmpty($json['items'] ?? [], 'default.json fehlt Items');

        foreach ($json['items'] as $item) {
            $image = (string) ($item['image'] ?? '');
            if ($image === '' || str_starts_with($image, 'http')) {
                continue;
            }
            $svgPath = $this->root() . '/assets/' . $image;
            self::assertFileExists($svgPath, "Banner-SVG fehlt: {$image}");
            $head = (string) file_get_contents($svgPath, false, null, 0, 512);
            self::assertStringContainsString(
                'viewBox="0 0 300 600"',
                $head,
                "Banner-SVG {$image} muss im 300x600-Portrait-Format bleiben, sonst bricht das Sidepanel-Layout"
            );
        }
    }

    public function testSidepanelImageRuleUsesContainNotCoverAndKeepsAspectRatio(): void
    {
        $css = (string) file_get_contents($this->root() . '/assets/app.css');

        // aspect-ratio 1/2 am Container-Element - damit wird sp-item.is-image
        // nicht mehr ueber die gesamte Spaltenhoehe gestreckt.
        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-image\{[^}]*aspect-ratio\s*:\s*1\s*\/\s*2/',
            $css,
            '.sp-item.is-image braucht aspect-ratio 1/2, sonst streckt das Sidepanel die 300x600-Banner'
        );

        // object-fit:contain am Bild (vorher cover) - damit sieht man das
        // komplette Banner, nicht einen gecroppten Ausschnitt.
        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-image img\{[^}]*object-fit\s*:\s*contain/',
            $css,
            '.sp-item.is-image img muss object-fit:contain haben (nicht cover), sonst werden Banner verzerrt gecroppt'
        );

        // Den alten cover-Fehler ausschliessen.
        self::assertDoesNotMatchRegularExpression(
            '/\.sp-item\.is-image img\{[^}]*object-fit\s*:\s*cover/',
            $css,
            '.sp-item.is-image img darf nicht wieder object-fit:cover bekommen - das war der eingangs gemeldete Bug'
        );
    }
}
