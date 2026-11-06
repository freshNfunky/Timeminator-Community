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

    public function testSidepanelRotatorFillsFullHeightAndImageFillsBox(): void
    {
        // Felix-Entscheidung 2026-11-06 Runde 3 ("mach es wie es vorher war.
        // keine diskussion jetzt. es hat vorher die ganze box ausgefüllt."):
        // der Rotator nimmt die gesamte Restspalte via flex:1 1 auto, das
        // sp-item.is-image img fuellt die Box mit object-fit:cover. Dass das
        // 1:2-Portrait-Banner dabei links/rechts beschnitten wird, ist
        // bewusst akzeptiert.
        $css = (string) file_get_contents($this->root() . '/assets/app.css');

        self::assertMatchesRegularExpression(
            '/\.sp-rotator\{[^}]*flex\s*:\s*1\s+1\s+auto/',
            $css,
            '.sp-rotator muss flex:1 1 auto sein - fuellt die gesamte Restspalte des Sidepanels'
        );

        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-image img\{[^}]*width\s*:\s*100%[^}]*height\s*:\s*100%[^}]*object-fit\s*:\s*cover/',
            $css,
            '.sp-item.is-image img muss width/height 100% + object-fit:cover haben, damit das Banner die komplette Box fuellt'
        );
    }
}
