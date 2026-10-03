<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pure-logic coverage for the Community banner carousel (Issue #22):
 * telemetry composition + opt-out, IP truncation, creative sanitizing, the
 * first-party link/image allow-list, and the bundled offline fallback set.
 * Nothing here touches Settings, the network or $_SERVER.
 */
final class BannersTest extends TestCase
{
    // ---------- Installation id ----------

    public function testUuid4HasCanonicalShapeAndIsRandom(): void
    {
        $a = Banners::uuid4();
        $b = Banners::uuid4();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $a
        );
        self::assertNotSame($a, $b);
    }

    // ---------- Telemetry composition ----------

    public function testTelemetryIncludesEveryFieldByDefault(): void
    {
        $t = Banners::buildTelemetry('iid-123', 'example.com', [], '203.0.113.42');
        self::assertSame('timeminator', $t['app']);
        self::assertArrayHasKey('v', $t);
        self::assertSame('iid-123', $t['iid']);
        self::assertArrayHasKey('hid', $t);
        self::assertSame('203.0.113.0', $t['ip']);
    }

    public function testTelemetryFieldsCanBeDisabledIndividually(): void
    {
        $conf = ['telemetry' => ['install_id' => false, 'ip' => false]];
        $t = Banners::buildTelemetry('iid-123', 'example.com', $conf, '203.0.113.42');
        self::assertArrayNotHasKey('iid', $t);
        self::assertArrayNotHasKey('ip', $t);
        self::assertArrayHasKey('hid', $t);  // still on
        self::assertArrayHasKey('v', $t);    // still on
    }

    public function testHashedIdIsStableAndSaltDependent(): void
    {
        $a = Banners::buildTelemetry('iid-123', 'host.tld', ['hash_salt' => 'pepper'], '');
        $b = Banners::buildTelemetry('iid-123', 'host.tld', ['hash_salt' => 'pepper'], '');
        $c = Banners::buildTelemetry('iid-123', 'host.tld', ['hash_salt' => 'other'], '');
        self::assertSame($a['hid'], $b['hid']);          // stable across IP changes
        self::assertNotSame($a['hid'], $c['hid']);       // salt changes the hash
        self::assertSame(64, strlen($a['hid']));         // sha256 hex
    }

    public function testNeverLeaksRawTimeTrackingShapedKeys(): void
    {
        $t = Banners::buildTelemetry('iid', 'h', [], '1.2.3.4');
        foreach (array_keys($t) as $k) {
            self::assertNotContains($k, ['entries', 'hours', 'clients', 'projects', 'tasks']);
        }
    }

    // ---------- IP truncation ----------

    public function testTruncateIpv4ZeroesLastOctet(): void
    {
        self::assertSame('192.168.1.0', Banners::truncateIp('192.168.1.77'));
    }

    public function testTruncateIpv6KeepsOnlyNetworkPrefix(): void
    {
        self::assertSame('2001:db8:1234::', Banners::truncateIp('2001:db8:1234:5678:9abc:def0:1234:5678'));
    }

    public function testTruncateIpHandlesEmptyAndGarbage(): void
    {
        self::assertSame('', Banners::truncateIp(''));
        self::assertSame('', Banners::truncateIp('not-an-ip'));
    }

    // ---------- Link + image allow-list ----------

    public function testHrefAllowsHttpsFirstPartyBrandsOnly(): void
    {
        self::assertTrue(Banners::isAllowedHref('https://felixschaller.com'));
        self::assertTrue(Banners::isAllowedHref('https://timeminator.felixschaller.com/x'));
        self::assertTrue(Banners::isAllowedHref('https://xixum.ai'));              // XIXUM product
        self::assertTrue(Banners::isAllowedHref('https://app.xixum.ai/assess'));   // subdomain
        self::assertTrue(Banners::isAllowedHref('https://af-ax.com'));             // AF-AX
        self::assertFalse(Banners::isAllowedHref('http://felixschaller.com'));       // not https
        self::assertFalse(Banners::isAllowedHref('https://evil.example.com'));        // wrong host
        self::assertFalse(Banners::isAllowedHref('https://felixschaller.com.evil.com')); // suffix trick
        self::assertFalse(Banners::isAllowedHref('https://notxixum.ai'));             // not a subdomain
        self::assertFalse(Banners::isAllowedHref('javascript:alert(1)'));
    }

    public function testImageAllowsRelativeDataUriAndHttpsFirstParty(): void
    {
        self::assertTrue(Banners::isAllowedImage('banners/xixum-maturity.svg'));
        self::assertTrue(Banners::isAllowedImage('https://cdn.felixschaller.com/a.png'));
        self::assertTrue(Banners::isAllowedImage('https://banner.felixschaller.com/c/x.svg'));
        self::assertTrue(Banners::isAllowedImage('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='));
        self::assertTrue(Banners::isAllowedImage('data:image/png;base64,iVBORw0KGgo='));
        self::assertFalse(Banners::isAllowedImage('data:text/html;base64,PHNjcmlwdD4=')); // non-image data
        self::assertFalse(Banners::isAllowedImage('//evil.example.com/a.png'));  // protocol-relative
        self::assertFalse(Banners::isAllowedImage('http://felixschaller.com/a.png'));
        self::assertFalse(Banners::isAllowedImage('../../etc/passwd'));          // traversal
        self::assertFalse(Banners::isAllowedImage('https://evil.example.com/a.png'));
    }

    // ---------- Sanitizing ----------

    public function testSanitizeItemDropsForeignLinkButKeepsCard(): void
    {
        $clean = Banners::sanitizeItem([
            'title' => 'Hi',
            'text'  => 'Body',
            'href'  => 'https://evil.example.com',
            'image' => '//evil/x.png',
        ]);
        self::assertNotNull($clean);
        self::assertSame('', $clean['href']);   // foreign link stripped
        self::assertSame('', $clean['image']);  // foreign image stripped
        self::assertSame('Hi', $clean['title']);
        self::assertNotSame('', $clean['id']);  // id derived when missing
    }

    public function testSanitizeItemRejectsEmptyCreative(): void
    {
        self::assertNull(Banners::sanitizeItem(['href' => 'https://felixschaller.com']));
    }

    public function testSanitizeItemsSkipsNonArrays(): void
    {
        $out = Banners::sanitizeItems(['nope', 42, ['title' => 'Keep']]);
        self::assertCount(1, $out);
        self::assertSame('Keep', $out[0]['title']);
    }

    // ---------- Bundled offline fallback ----------

    public function testDefaultItemsLoadFromShippedFile(): void
    {
        $items = Banners::defaultItems();
        self::assertNotEmpty($items, 'assets/banners/default.json should ship a fallback set');
        foreach ($items as $it) {
            self::assertArrayHasKey('title', $it);
            self::assertArrayHasKey('href', $it);
            if ($it['href'] !== '') {
                self::assertTrue(Banners::isAllowedHref($it['href']), 'default href must be first-party: ' . $it['href']);
            }
        }
    }

    // ---------- HTML banner iframe ----------

    public function testIframeAllowsHttpsFirstPartyOnly(): void
    {
        self::assertTrue(Banners::isAllowedIframe('https://banner.felixschaller.com/media/x/index.html'));
        self::assertTrue(Banners::isAllowedIframe('https://cdn.xixum.ai/b/index.html'));
        self::assertFalse(Banners::isAllowedIframe('http://banner.felixschaller.com/x.html')); // not https
        self::assertFalse(Banners::isAllowedIframe('https://evil.example.com/x.html'));         // wrong host
        self::assertFalse(Banners::isAllowedIframe('https://felixschaller.com.evil.com/x.html'));
    }

    public function testSanitizeItemKeepsFirstPartyIframeDropsForeign(): void
    {
        $ok = Banners::sanitizeItem(['title' => 'H', 'iframe' => 'https://banner.felixschaller.com/media/x/index.html']);
        self::assertSame('https://banner.felixschaller.com/media/x/index.html', $ok['iframe']);
        $bad = Banners::sanitizeItem(['title' => 'H', 'iframe' => 'https://evil.example.com/x.html']);
        self::assertSame('', $bad['iframe']);
    }

    public function testFrameSrcListsFirstPartyHostsAndWildcards(): void
    {
        $fs = Banners::frameSrc();
        self::assertStringContainsString('https://felixschaller.com', $fs);
        self::assertStringContainsString('https://*.felixschaller.com', $fs);
        self::assertStringContainsString('https://*.xixum.ai', $fs);
    }
}
