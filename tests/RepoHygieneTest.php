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

    public function testNoFelixschallerDotComUrlInPublicFiles(): void
    {
        // Zweite Runde (Felix-Feedback 2026-11-05): "alles was URL hat:
        // *.felixschaller.com raus". Betrifft Infrastruktur-Subdomains
        // (assets., banner., license., public., cdn., demo.) genauso wie
        // die nackte Domain und die private Timeminator-Instanz.
        foreach ($this->publicFacingFiles() as $file) {
            if (!is_file($file)) {
                continue;
            }
            $body = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression(
                '/[A-Za-z0-9.-]*felixschaller\.com/',
                $body,
                "{$file} darf keine *.felixschaller.com-URL mehr nennen"
            );
        }
    }

    public function testPublicProDomainIsTheOneUsed(): void
    {
        // Sanity: zumindest README nennt die oeffentliche Domain.
        $readme = (string) file_get_contents($this->root() . '/README.md');
        self::assertStringContainsString('timeminator.com', $readme, 'Oeffentliche Pro-Domain timeminator.com fehlt in der README');
    }
}
