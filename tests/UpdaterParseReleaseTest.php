<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression cover for Updater::parseRelease() — the GitHub-Releases payload
 * parsing that the network path (Updater::check) wraps. UpdaterBannerTest
 * deliberately skips the network/parse path; this test exercises it offline
 * against a captured real `releases/latest` fixture, so a GitHub shape change
 * or a refactor that breaks tag/asset/checksum extraction fails loudly.
 */
final class UpdaterParseReleaseTest extends TestCase
{
    /** @return array<string,mixed> the frozen real release payload, decoded */
    private function fixture(): array
    {
        $raw = file_get_contents(__DIR__ . '/fixtures/github_release_latest.json');
        self::assertIsString($raw, 'Fixture github_release_latest.json fehlt oder ist nicht lesbar.');
        $data = json_decode($raw, true);
        self::assertIsArray($data, 'Fixture ist kein gueltiges JSON.');
        return $data;
    }

    public function testParsesRealReleaseFixture(): void
    {
        $r = Updater::parseRelease($this->fixture(), '0.9.1');
        self::assertTrue($r['ok']);
        self::assertSame('1.0.0', $r['latest']);
        self::assertSame('0.9.1', $r['current']);
        self::assertSame('timeminator-community-v1.0.0.zip', $r['asset_name']);
        self::assertSame(
            'https://github.com/freshNfunky/Timeminator-Community/releases/tag/v1.0.0',
            $r['url']
        );
        // The real .zip asset wins over the GitHub zipball_url.
        self::assertSame(
            'https://github.com/freshNfunky/Timeminator-Community/releases/download/v1.0.0/timeminator-community-v1.0.0.zip',
            $r['download']
        );
    }

    /** A running 0.9.1 install must be told 1.0.0 is newer — the core promise. */
    public function testNewerWhenCurrentIsOlder(): void
    {
        self::assertTrue(Updater::parseRelease($this->fixture(), '0.9.1')['newer']);
        // Leading "v" on the running version must not defeat the comparison.
        self::assertTrue(Updater::parseRelease($this->fixture(), 'v0.9.1')['newer']);
    }

    public function testNotNewerWhenCurrentIsEqual(): void
    {
        self::assertFalse(Updater::parseRelease($this->fixture(), '1.0.0')['newer']);
    }

    public function testNotNewerWhenCurrentIsAhead(): void
    {
        self::assertFalse(Updater::parseRelease($this->fixture(), '1.1.0')['newer']);
    }

    public function testEmptyPayloadReturnsError(): void
    {
        $r = Updater::parseRelease([], '1.0.0');
        self::assertFalse($r['ok']);
        self::assertArrayHasKey('error', $r);
    }

    public function testMissingTagNameReturnsError(): void
    {
        $r = Updater::parseRelease(['assets' => [], 'html_url' => 'https://x/y'], '1.0.0');
        self::assertFalse($r['ok']);
    }

    /** No .zip asset: fall back to the GitHub zipball, with an empty asset name. */
    public function testZipballFallbackWhenNoZipAsset(): void
    {
        $r = Updater::parseRelease([
            'tag_name'    => 'v1.2.0',
            'zipball_url' => 'https://api.github.com/zipball/v1.2.0',
            'assets'      => [
                ['name' => 'notes.txt', 'browser_download_url' => 'https://x/notes.txt'],
            ],
        ], '1.0.0');
        self::assertSame('1.2.0', $r['latest']);
        self::assertSame('', $r['asset_name']);
        self::assertSame('https://api.github.com/zipball/v1.2.0', $r['download']);
    }

    /** A sibling `<zip>.sha256` is preferred as the checksum source. */
    public function testChecksumSiblingPreferred(): void
    {
        $r = Updater::parseRelease([
            'tag_name' => 'v1.2.0',
            'assets'   => [
                ['name' => 'app-v1.2.0.zip', 'browser_download_url' => 'https://x/app.zip'],
                ['name' => 'app-v1.2.0.zip.sha256', 'browser_download_url' => 'https://x/app.zip.sha256'],
                ['name' => 'SHA256SUMS', 'browser_download_url' => 'https://x/SHA256SUMS'],
            ],
        ], '1.0.0');
        self::assertSame('app-v1.2.0.zip', $r['asset_name']);
        self::assertSame('https://x/app.zip.sha256', $r['checksum_url']);
    }

    /** With no sibling, a SHA256SUMS asset is the fallback checksum source. */
    public function testChecksumSumsFallback(): void
    {
        $r = Updater::parseRelease([
            'tag_name' => 'v1.2.0',
            'assets'   => [
                ['name' => 'app-v1.2.0.zip', 'browser_download_url' => 'https://x/app.zip'],
                ['name' => 'SHA256SUMS', 'browser_download_url' => 'https://x/SHA256SUMS'],
            ],
        ], '1.0.0');
        self::assertSame('https://x/SHA256SUMS', $r['checksum_url']);
    }

    /** The real fixture ships no checksum asset — checksum_url must be empty. */
    public function testNoChecksumAssetYieldsEmptyChecksumUrl(): void
    {
        self::assertSame('', Updater::parseRelease($this->fixture(), '0.9.1')['checksum_url']);
    }
}
