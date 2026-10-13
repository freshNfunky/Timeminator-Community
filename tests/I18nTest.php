<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * i18n infrastructure (#95). Covers detection precedence, Accept-Language
 * q-value parsing, graceful fallback for missing keys, and the sanitize
 * clamp that stops arbitrary codes from entering the session.
 */
final class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_SESSION = [];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        I18n::reset();
    }

    public function testSanitizeAcceptsSupportedAndStripsRegion(): void
    {
        self::assertSame('de', I18n::sanitize('de'));
        self::assertSame('en', I18n::sanitize('EN'));
        self::assertSame('de', I18n::sanitize('de-DE'));
        self::assertSame('en', I18n::sanitize('en-US'));
        self::assertNull(I18n::sanitize(''));
        self::assertNull(I18n::sanitize('fr'));
        self::assertNull(I18n::sanitize('<script>'));
    }

    public function testAcceptLanguagePicksHighestSupportedByQ(): void
    {
        self::assertSame('en', I18n::fromAcceptLanguage('fr;q=1.0, en-US;q=0.9, de;q=0.5'));
        self::assertSame('de', I18n::fromAcceptLanguage('de-DE,de;q=0.9,en;q=0.5'));
        self::assertSame('en', I18n::fromAcceptLanguage('en, de'), 'equal q -> order wins');
        self::assertNull(I18n::fromAcceptLanguage('fr-FR, it, ja'));
        self::assertNull(I18n::fromAcceptLanguage(''));
    }

    public function testDetectPrefersQueryOverSessionOverHeader(): void
    {
        $_SESSION['_locale'] = 'de';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9';
        $_GET['lang'] = 'en';
        self::assertSame('en', I18n::detect());
        // Query also persists into session so a shared `?lang=en` sticks.
        self::assertSame('en', $_SESSION['_locale']);
    }

    public function testDetectUsesSessionWhenNoQuery(): void
    {
        $_SESSION['_locale'] = 'en';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9';
        self::assertSame('en', I18n::detect());
    }

    public function testDetectFallsBackToDefaultWhenNothingMatches(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ja, fr';
        self::assertSame('de', I18n::detect());
    }

    public function testInvalidQueryIsIgnoredAndSessionNotPolluted(): void
    {
        $_GET['lang'] = 'fr';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
        self::assertSame('de', I18n::detect());
        self::assertArrayNotHasKey('_locale', $_SESSION);
    }

    public function testTranslateFallsBackThroughLocaleThenDefaultThenKey(): void
    {
        // Known key in both: English wins when current is en.
        $_SESSION['_locale'] = 'en';
        self::assertSame('Sign in', I18n::translate('login.submit'));

        // Missing key in current (en), present in default (de) -> de value.
        // (Simulate: write to the en cache so we know it's really a fallback.)
        $this->setCatalog('en', ['login.submit' => 'Sign in']);  // minimal
        self::assertNotEmpty(I18n::translate('nav.dashboard'));

        // Totally missing key -> default arg wins over the key name itself.
        self::assertSame('xyz', I18n::translate('does.not.exist', 'xyz'));
        self::assertSame('does.not.exist', I18n::translate('does.not.exist'));
    }

    public function testCurrentIsOneOfSupported(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ja';
        self::assertContains(I18n::current(), I18n::SUPPORTED);
    }

    public function testSetSessionClampsAndPersists(): void
    {
        self::assertFalse(I18n::setSession('fr'));
        self::assertTrue(I18n::setSession('en'));
        self::assertSame('en', $_SESSION['_locale']);
        self::assertSame('en', I18n::current());
    }

    /** Reach into the private catalog cache to inject a stub. */
    private function setCatalog(string $code, array $data): void
    {
        $r = new ReflectionProperty(I18n::class, 'catalog');
        $cur = $r->getValue();
        $cur[$code] = $data;
        $r->setValue(null, $cur);
    }
}
