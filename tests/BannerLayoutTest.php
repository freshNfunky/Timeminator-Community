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

    public function testSidepanelRotatorKeepsBannerAspectRatioAndFillsFully(): void
    {
        $css = (string) file_get_contents($this->root() . '/assets/app.css');

        // aspect-ratio 1/2 am Rotator-Container (Felix-Feedback 2026-11-06,
        // Runde 2): dadurch ist der Container immer 180 px breit x 360 px
        // hoch und das 300x600-Banner darin fuellt die Box zu 100 % ohne
        // Overlap/Crop. Vorher an sp-item.is-image → nur halbe Box genutzt.
        self::assertMatchesRegularExpression(
            '/\.sp-rotator\{[^}]*aspect-ratio\s*:\s*1\s*\/\s*2/',
            $css,
            '.sp-rotator braucht aspect-ratio 1/2, sonst streckt das Sidepanel die 300x600-Banner oder laesst die Box halb leer'
        );

        // sp-rotator darf nicht wieder die volle Restspalte via flex:1 1 auto
        // einnehmen (alter Zustand, der den Verzerrungsbug ausloeste).
        self::assertDoesNotMatchRegularExpression(
            '/\.sp-rotator\{[^}]*flex\s*:\s*1\s+1\s+auto/',
            $css,
            '.sp-rotator darf nicht flex:1 1 auto sein - das waere der alte Zustand, in dem Banner ueber die ganze Spalte gestreckt wurden'
        );

        // sp-item.is-image fuellt den (jetzt proportional korrekten) Rotator
        // zu 100 % mit object-fit:cover - bei identischen Verhaeltnissen
        // ohne Crop, aber Rundungsspalten bleiben zu.
        self::assertMatchesRegularExpression(
            '/\.sp-item\.is-image img\{[^}]*width\s*:\s*100%[^}]*height\s*:\s*100%[^}]*object-fit\s*:\s*cover/',
            $css,
            '.sp-item.is-image img muss 100% x 100% mit object-fit:cover sein, damit es die Box ohne Overlap fuellt'
        );
    }
}
