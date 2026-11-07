<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: keine privaten/internen URLs im oeffentlichen Repository.
 *
 * Felix-Feedback 2026-11-05: Erstrunde entfernte timeminator.felixschaller.com
 * (private Instanz). Zweite Runde: "alles was URL hat: *.felixschaller.com
 * raus" - gilt fuer README, Beispiel-Config, Installer, oeffentliche Docs
 * und Tests. Die oeffentliche Pro-Produktseite liegt auf timeminator.com.
 */
final class RepoHygieneTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    /** @return list<string> Dateien, die oeffentlich sichtbar sind oder Installations-Defaults setzen. */
    private function publicFacingFiles(): array
    {
        return [
            $this->root() . '/README.md',
            $this->root() . '/config.sample.php',
            $this->root() . '/install.php',
            $this->root() . '/docs/BANNERS.md',
            $this->root() . '/docs/Umfrage/README.md',
            $this->root() . '/src/Banners.php',
            $this->root() . '/tests/BannersTest.php',
            // Felix-Feedback 2026-11-07: der Footer-/Sidepanel-Link zeigte
            // auf die PRIVATE Instanz; jetzt pro_demo_url default
            // demo.timeminator.com. Das Layout ist eine stark sichtbare
            // Oberflaeche - private URLs duerfen hier nicht mehr rein.
            $this->root() . '/views/layout.php',
        ];
    }

    public function testPrivateInstanceUrlIsNotMentionedPublicly(): void
    {
        foreach ($this->publicFacingFiles() as $file) {
            if (!is_file($file)) {
                continue;
            }
            $body = (string) file_get_contents($file);
            self::assertStringNotContainsString(
                'timeminator.felixschaller.com',
                $body,
                "{$file} darf die private Instanz-URL timeminator.felixschaller.com nicht enthalten"
            );
        }
    }

    // Felix-Entscheidung 2026-11-06: der Banner-Feed-Host assets.felixschaller.com
    // ist wieder shipped Infrastruktur (feed.php, byte-identisch zur frueheren
    // feed.json). Nur die PRIVATE Instanz-URL timeminator.felixschaller.com
    // bleibt verbannt (testPrivateInstanceUrlIsNotMentionedPublicly oben).

    public function testPublicProDomainIsTheOneUsed(): void
    {
        // Sanity: zumindest README nennt die oeffentliche Domain.
        $readme = (string) file_get_contents($this->root() . '/README.md');
        self::assertStringContainsString('timeminator.com', $readme, 'Oeffentliche Pro-Domain timeminator.com fehlt in der README');
    }

    public function testFooterAndSidepanelBrandPointAtDemoInstance(): void
    {
        // Felix-Feedback 2026-11-07: der Footer-Link "mehr Funktionen mit
        // Timeminator Pro" zeigte vorher auf die private Instanz. Neue
        // Config-Variable pro_demo_url mit Default demo.timeminator.com.
        $layout = (string) file_get_contents($this->root() . '/views/layout.php');
        $sample = (string) file_get_contents($this->root() . '/config.sample.php');

        // Beide Links im Layout nutzen pro_demo_url statt pro_url.
        self::assertSame(
            2,
            substr_count($layout, "cfg('pro_demo_url'"),
            'Layout muss pro_demo_url an zwei Stellen nutzen: Footer-Link + Sidepanel-Brand'
        );
        // Default-Fallback ist die oeffentliche Demo-Instanz, niemals die private.
        self::assertStringContainsString(
            "cfg('pro_demo_url', 'https://demo.timeminator.com')",
            $layout,
            'Default-Fallback fuer pro_demo_url muss demo.timeminator.com sein'
        );
        // Config-Sample dokumentiert den Schluessel + Warnhinweis.
        self::assertStringContainsString(
            "'pro_demo_url' => 'https://demo.timeminator.com'",
            $sample,
            'config.sample.php muss pro_demo_url mit demo.timeminator.com dokumentieren'
        );
        // Warnhinweis darf ueber mehrere Kommentar-Zeilen gehen (die durch
        // "\n    // " unterbrochen werden).
        self::assertMatchesRegularExpression(
            '~Niemals(?:[\s/]|//)*auf(?:[\s/]|//)*eine(?:[\s/]|//)*private(?:[\s/]|//)*Instanz(?:[\s/]|//)*setzen~',
            $sample,
            'config.sample.php braucht einen expliziten Warnhinweis gegen private Instanz-URLs'
        );
    }
}
