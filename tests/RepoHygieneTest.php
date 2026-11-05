<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: keine privaten/internen URLs im oeffentlichen Repository.
 *
 * Felix-Feedback 2026-11-05: "sensible inhalte in der readme. ganz am ende:
 * 'See the Pro feature list and pricing at https://timeminator.felixschaller.com.'
 * das ist meine eigene instanz. raus. domain ist 'timeminator.com'"
 *
 * Die Timeminator-Pro-Produktseite liegt auf timeminator.com; die
 * Subdomain timeminator.felixschaller.com ist Felix' PRIVATE interne
 * Instanz und darf im Community-Code / in der README nicht auftauchen -
 * ebensowenig eine eigenmaechtig verschobene public.felixschaller.com-
 * URL. Lizenz-Server/Asset-Server sind erlaubt (license.felixschaller.com,
 * assets.felixschaller.com, banner.felixschaller.com).
 */
final class RepoHygieneTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    /** @return list<string> */
    private function userFacingTextFiles(): array
    {
        return [
            $this->root() . '/README.md',
            $this->root() . '/config.sample.php',
            $this->root() . '/install.php',
        ];
    }

    public function testPrivateInstanceUrlIsNotMentionedPublicly(): void
    {
        foreach ($this->userFacingTextFiles() as $file) {
            $body = (string) file_get_contents($file);
            self::assertStringNotContainsString(
                'timeminator.felixschaller.com',
                $body,
                "{$file} darf die private Instanz-URL timeminator.felixschaller.com nicht enthalten"
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
