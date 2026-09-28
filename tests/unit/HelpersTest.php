<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $GLOBALS['APP_CONFIG']['trusted_proxies'] = [];
    }

    public function testCsrfTokenIsStableWithinSession(): void
    {
        $t1 = csrf_token();
        $t2 = csrf_token();
        self::assertSame($t1, $t2, 'csrf_token must be stable within a session');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $t1);
    }

    public function testCsrfCheckAcceptsMatchingToken(): void
    {
        $t = csrf_token();
        $_POST['_csrf'] = $t;
        csrf_check();
        // csrf_check exits on failure. Reaching this line means it accepted.
        self::assertTrue(true);
    }

    public function testClientIpIgnoresForwardedHeaderFromUntrustedPeer(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
        // No trusted proxies configured -> header must be ignored.
        self::assertSame('203.0.113.1', client_ip());
    }

    public function testClientIpHonoursForwardedHeaderFromTrustedPeer(): void
    {
        $GLOBALS['APP_CONFIG']['trusted_proxies'] = ['203.0.113.1'];
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
        self::assertSame('198.51.100.9', client_ip());
    }

    public function testClientIpSkipsTrustedProxiesInChain(): void
    {
        $GLOBALS['APP_CONFIG']['trusted_proxies'] = ['203.0.113.1', '198.51.100.9'];
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99, 198.51.100.9';
        self::assertSame('203.0.113.99', client_ip());
    }

    public function testFmtHoursAndHm(): void
    {
        self::assertSame('1.50 h', fmt_hours(90));
        self::assertSame('0.00 h', fmt_hours(0));
        self::assertSame('1:30', fmt_hm(90));
        self::assertSame('0:00', fmt_hm(null));
        self::assertSame('0:00', fmt_hm(-5), 'negative minutes clamp to zero');
    }
}
