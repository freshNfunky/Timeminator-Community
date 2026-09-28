<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testHEscapesHtml(): void
    {
        self::assertSame('&lt;a&gt;&amp;&quot;', h('<a>&"'));
    }

    public function testCfgReadsFromAppConfig(): void
    {
        $GLOBALS['APP_CONFIG']['probe'] = 'value';
        self::assertSame('value', cfg('probe'));
        self::assertSame('fallback', cfg('missing_key', 'fallback'));
    }

    public function testClientIpHonoursRemoteAddrAsFallback(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        self::assertSame('203.0.113.42', client_ip());
    }

    public function testClientIpIgnoresForwardedForFromUntrustedPeer(): void
    {
        // Without a `trusted_proxies` allow-list, X-Forwarded-For must be ignored
        // so a header-forging client cannot spoof its IP.
        $GLOBALS['APP_CONFIG']['trusted_proxies'] = [];
        $_SERVER['REMOTE_ADDR']              = '203.0.113.99';
        $_SERVER['HTTP_X_FORWARDED_FOR']     = '10.10.10.10';
        self::assertSame('203.0.113.99', client_ip());
    }
}
