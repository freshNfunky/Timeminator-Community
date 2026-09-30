<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Skip / dismiss / opportunistic-check behaviour of the update banner.
 * Does not exercise the network path (Updater::check), only the
 * cache-and-suppress logic layered on top of it.
 */
final class UpdaterBannerTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::resetSettings();
        $_SESSION = [];
        // Neutralize the disable flag, force a manifest URL so
        // opportunisticCheck() would run if it were not throttled.
        Settings::set('update_check_disabled', false);
        $GLOBALS['APP_CONFIG']['update_manifest_url'] = 'https://example.invalid/manifest.json';
    }

    protected function tearDown(): void
    {
        TestSupport::resetSettings();
        $_SESSION = [];
    }

    /**
     * Stage a "newer version available" cache entry — the banner is expected
     * to render unless something explicitly suppresses it.
     */
    private function stageAvailable(string $latest = '9.9.9'): void
    {
        Settings::set('update', [
            'ok'         => true,
            'current'    => '0.5.0',
            'latest'     => $latest,
            'newer'      => true,
            'url'        => 'https://example.invalid/releases/' . $latest,
            'download'   => 'https://example.invalid/' . $latest . '.zip',
            'asset_name' => $latest . '.zip',
            'checked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testBannerShowsWhenNewerVersionAvailable(): void
    {
        $this->stageAvailable('9.9.9');
        $info = Updater::bannerInfo();
        self::assertIsArray($info);
        self::assertSame('9.9.9', $info['latest']);
    }

    public function testBannerHiddenWhenCacheIsAbsent(): void
    {
        self::assertNull(Updater::bannerInfo());
    }

    public function testBannerHiddenWhenNotNewer(): void
    {
        Settings::set('update', [
            'ok' => true, 'current' => '0.5.0', 'latest' => '0.5.0', 'newer' => false,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);
        self::assertNull(Updater::bannerInfo());
    }

    public function testBannerHiddenAfterSessionDismiss(): void
    {
        $this->stageAvailable('9.9.9');
        self::assertNotNull(Updater::bannerInfo());

        Updater::dismissForSession('v9.9.9');
        self::assertNull(Updater::bannerInfo(), 'session dismiss should hide the banner');
    }

    public function testDismissIsScopedToTheDismissedVersion(): void
    {
        $this->stageAvailable('9.9.9');
        Updater::dismissForSession('9.9.9');
        self::assertNull(Updater::bannerInfo());

        // A subsequent release should reappear even in the same session.
        $this->stageAvailable('9.9.10');
        self::assertNotNull(Updater::bannerInfo());
    }

    public function testSkipVersionHidesUntilNewerAppears(): void
    {
        $this->stageAvailable('9.9.9');
        Updater::skipVersion('9.9.9');
        self::assertTrue(Updater::isVersionSkipped('9.9.9'));
        self::assertTrue(Updater::isVersionSkipped('v9.9.9'), 'skip should ignore the v prefix');
        self::assertNull(Updater::bannerInfo(), 'skipped version must not surface');

        // A newer version bypasses the skip.
        $this->stageAvailable('9.9.10');
        self::assertFalse(Updater::isVersionSkipped('9.9.10'));
        self::assertNotNull(Updater::bannerInfo());
    }

    public function testClearSkippedRestoresBanner(): void
    {
        $this->stageAvailable('9.9.9');
        Updater::skipVersion('9.9.9');
        self::assertNull(Updater::bannerInfo());

        Updater::clearSkippedVersions();
        self::assertSame([], Updater::skippedVersions());
        self::assertNotNull(Updater::bannerInfo());
    }

    public function testOpportunisticCheckIsThrottledByCacheAge(): void
    {
        // Cache saying we already checked one minute ago; the throttled call
        // must NOT hit the network. We pass a huge throttle so the guard trips.
        Settings::set('update', [
            'ok' => true, 'current' => '0.5.0', 'latest' => '0.5.0', 'newer' => false,
            'checked_at' => date('Y-m-d H:i:s', time() - 60),
        ]);
        $before = Settings::get('update');
        Updater::opportunisticCheck(3600); // needs to be > 60 to skip
        $after = Settings::get('update');
        self::assertSame($before, $after, 'throttled call must not touch the cache');
    }

    public function testOpportunisticCheckBailsWhenDisabled(): void
    {
        Settings::set('update_check_disabled', true);
        // No cache entry present — if the check ran, it would write one.
        Updater::opportunisticCheck(0);
        self::assertNull(Settings::get('update'), 'disabled flag must short-circuit');
    }

    public function testOpportunisticCheckBailsWithoutManifestUrl(): void
    {
        $GLOBALS['APP_CONFIG']['update_manifest_url'] = '';
        Settings::set('update_manifest_url_override', '');
        Updater::opportunisticCheck(0);
        self::assertNull(Settings::get('update'), 'empty manifest URL must short-circuit');
    }
}
