<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Mail service surface (#107). Focuses on the parts that don't need an actual
 * SMTP peer: config round-trip, validation, isConfigured() gating, and the
 * input-validation branches of send() that short-circuit before any socket is
 * opened. The SMTP wire protocol itself is intentionally not mocked here — the
 * admin "Test-Mail" button is the integration test for that.
 */
final class MailTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::resetSettings();
    }

    protected function tearDown(): void
    {
        TestSupport::resetSettings();
    }

    public function testConfigDefaultsToEmptyAndNotConfigured(): void
    {
        $cfg = Mail::config();
        self::assertSame('', $cfg['from']);
        self::assertSame(0, $cfg['port']);
        self::assertSame('none', $cfg['security']);
        self::assertFalse(Mail::isConfigured());
    }

    public function testSaveRoundTripsAndClampsSecurity(): void
    {
        Mail::save([
            'from'      => 'no-reply@example.com',
            'from_name' => 'App',
            'host'      => 'smtp.example.com',
            'port'      => 587,
            'user'      => 'u',
            'pass'      => 'p',
            'security'  => 'tls',
        ]);
        $cfg = Mail::config();
        self::assertSame('no-reply@example.com', $cfg['from']);
        self::assertSame(587, $cfg['port']);
        self::assertSame('tls', $cfg['security']);
        self::assertTrue(Mail::isConfigured());

        Mail::save(['from' => 'x@y.z', 'security' => 'bogus-value']);
        self::assertSame('none', Mail::config()['security'], 'unknown security clamps to none');
    }

    public function testSendRefusesWhenNotConfigured(): void
    {
        [$ok, $err] = Mail::send('user@example.com', 'subject', 'body');
        self::assertFalse($ok);
        self::assertSame('mail_not_configured', $err);
    }

    public function testSendRefusesInvalidRecipient(): void
    {
        Mail::save(['from' => 'no-reply@example.com']);
        [$ok, $err] = Mail::send('not-an-email', 'subject', 'body');
        self::assertFalse($ok);
        self::assertSame('invalid_recipient', $err);
    }
}
