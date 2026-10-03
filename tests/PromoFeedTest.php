<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the promo-feed sanitizer and fallback path.
 * No network. Live-fetch behavior is exercised in integration, not here.
 */
final class PromoFeedTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['APP_CONFIG']['promo_feed_url'] = 'https://banner.felixschaller.com/feed.json';
        $GLOBALS['APP_CONFIG']['promo_feed_allowed_hosts'] = ['felixschaller.com', 'xixum.ai', 'af-ax.com'];
    }

    public function testFallbackCarriesBundledItems(): void
    {
        $items = PromoFeed::fallback();
        self::assertNotEmpty($items, 'bundled fallback should seed the carousel');
        foreach ($items as $it) {
            self::assertArrayHasKey('href', $it);
            self::assertMatchesRegularExpression('~^https://~', $it['href']);
        }
    }

    public function testFeedHostIsConfiguredBannerDomain(): void
    {
        self::assertSame('banner.felixschaller.com', PromoFeed::feedHost());
    }

    public function testFallbackDropsHrefOnForeignHost(): void
    {
        // Point the loader at a nonexistent URL so no network call succeeds.
        $GLOBALS['APP_CONFIG']['promo_feed_url'] = '';
        // Build a sanitize test by swapping the fallback via the public API —
        // easier than reflection. Here we verify sanitize via a crafted
        // bundled file is unnecessary; call the private via known path:
        $m = new ReflectionMethod(PromoFeed::class, 'sanitize');
        /** @var array<int, array<string, string>> $out */
        $out = $m->invoke(null, [
            ['href' => 'https://evil.example.com/x', 'title' => 'nope'],
            ['href' => 'https://felixschaller.com/x', 'title' => 'ok',    'body' => 'yes'],
        ]);
        self::assertCount(1, $out);
        self::assertSame('ok', $out[0]['title']);
    }

    public function testSanitizeStripsScriptyPayloads(): void
    {
        $m = new ReflectionMethod(PromoFeed::class, 'sanitize');
        $out = $m->invoke(null, [
            [
                'href' => 'https://felixschaller.com/x',
                'html' => '<p>ok</p><script>alert(1)</script>',
                'title' => 't',
            ],
        ]);
        self::assertCount(1, $out);
        // Scripty html must be rejected (text path still carries the title).
        self::assertArrayNotHasKey('html', $out[0]);
        self::assertSame('t', $out[0]['title']);
    }

    public function testSanitizeKeepsOnlyFeedHostIframeUrls(): void
    {
        $m = new ReflectionMethod(PromoFeed::class, 'sanitize');
        $out = $m->invoke(null, [
            // html_url on the configured feed host → kept
            [
                'href'     => 'https://felixschaller.com/x',
                'html_url' => 'https://banner.felixschaller.com/creative/abc.html',
            ],
            // html_url on felixschaller.com (allowed CTA host but NOT the
            // configured feed host) → stripped so cross-origin iframes stay
            // pinned to the actual banner server
            [
                'href'     => 'https://felixschaller.com/x',
                'html_url' => 'https://felixschaller.com/some.html',
                'title'    => 'fallback title',
            ],
        ]);
        self::assertCount(2, $out);
        self::assertArrayHasKey('html_url', $out[0]);
        self::assertSame('https://banner.felixschaller.com/creative/abc.html', $out[0]['html_url']);
        self::assertArrayNotHasKey('html_url', $out[1]);
        self::assertSame('fallback title', $out[1]['title']);
    }

    public function testSanitizeRequiresHttpsOnCta(): void
    {
        $m = new ReflectionMethod(PromoFeed::class, 'sanitize');
        $out = $m->invoke(null, [
            ['href' => 'http://felixschaller.com/x', 'title' => 'no'],
            ['href' => 'javascript:alert(1)',         'title' => 'nope'],
            ['href' => 'https://felixschaller.com/x', 'title' => 'yes'],
        ]);
        self::assertCount(1, $out);
        self::assertSame('yes', $out[0]['title']);
    }
}
