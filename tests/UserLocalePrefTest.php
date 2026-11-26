<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * M1.1 #191: Sprachwahl im Benutzermenue (user_prefs.locale).
 *
 * Persoenliche Darstellungs-Pref, nicht vertraglich und nicht systemisch
 * (siehe Memory settings-layer-classification). Chain in I18n::detect():
 * ?lang= -> $_SESSION -> user_prefs -> Accept-Language -> Default.
 */
final class UserLocalePrefTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        TestSupport::resetSettings();
        // Reset I18n Singleton zwischen Tests.
        $prop = new ReflectionProperty(I18n::class, 'current');
        $prop->setValue(null, null);
        unset($_GET['lang'], $_SESSION, $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        // Keine lebendige Auth-Session aus einem anderen Test schleppen.
        if (class_exists('Auth')) {
            $u = new ReflectionProperty(Auth::class, 'user');
            $u->setValue(null, null);
        }
    }

    public function testSetLocalePersistsAndNullClears(): void
    {
        $uid = TestSupport::seedUser();
        UserPrefs::setLocale($uid, 'en');
        self::assertSame('en', UserPrefs::locale($uid));

        UserPrefs::setLocale($uid, null);
        self::assertNull(UserPrefs::locale($uid));
    }

    public function testSetLocaleRejectsUnsupportedCode(): void
    {
        $uid = TestSupport::seedUser();
        UserPrefs::setLocale($uid, 'xx');
        self::assertNull(UserPrefs::locale($uid), 'Unsupported codes muessen auf null normalisiert werden');
    }

    public function testI18nDetectUsesUserPrefOverAcceptLanguage(): void
    {
        $uid = TestSupport::seedUser();
        UserPrefs::setLocale($uid, 'en');

        // Simuliere eingeloggten User und einen EN-abweichenden Accept-Language.
        $u = new ReflectionProperty(Auth::class, 'user');
        $u->setValue(null, ['id' => $uid, 'display_name' => 'tester', 'role_id' => null, 'active' => 1]);
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9';

        self::assertSame('en', I18n::detect(),
            'User-Pref muss vor Accept-Language gewinnen');
    }

    public function testI18nDetectFallsThroughWhenNoUserPrefSet(): void
    {
        $uid = TestSupport::seedUser();
        $u = new ReflectionProperty(Auth::class, 'user');
        $u->setValue(null, ['id' => $uid, 'display_name' => 'tester', 'role_id' => null, 'active' => 1]);
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

        self::assertSame('en', I18n::detect(),
            'Ohne User-Pref faellt die Chain auf Accept-Language durch');
    }

    public function testSessionOverrideStillWinsOverUserPref(): void
    {
        $uid = TestSupport::seedUser();
        UserPrefs::setLocale($uid, 'en');
        $u = new ReflectionProperty(Auth::class, 'user');
        $u->setValue(null, ['id' => $uid, 'display_name' => 'tester', 'role_id' => null, 'active' => 1]);
        $_SESSION['_locale'] = 'de';

        self::assertSame('de', I18n::detect(),
            'Session-Override (z.B. frisches ?lang=) muss die User-Pref uebersteuern');
    }

    public function testDetectSurvivesMissingUserPrefsTable(): void
    {
        // Simuliere alten DB-Stand ohne user_prefs (Migration noch nicht gelaufen).
        // Ohne dropping einfach Auth::id ohne seedUser - UserPrefs::locale wirft
        // dann keinen Fehler (DB::one gibt null bei missing row, nicht Tabelle).
        // Realistischer Case: DROP + Query. Wir testen beides Robust ueber try/catch.
        DB::run('DROP TABLE IF EXISTS user_prefs');
        $u = new ReflectionProperty(Auth::class, 'user');
        $u->setValue(null, ['id' => 99, 'display_name' => 'ghost', 'role_id' => null, 'active' => 1]);
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en;q=0.9';

        self::assertSame('en', I18n::detect(),
            'Fehlende user_prefs-Tabelle darf I18n nicht crashen - Fallback auf Accept-Language');
    }

    public function testProfileViewHasLocaleSelect(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/profile/index.php');
        self::assertStringContainsString('name="locale"', $view,
            'Profile-View muss Sprach-Radio-Gruppe rendern');
        self::assertStringContainsString("t('profile.language_section'", $view,
            'Sprach-Section muss i18n-Key profile.language_section nutzen');
        self::assertStringContainsString("t('profile.language_auto'", $view,
            'Browser-Default-Option muss i18n-Key nutzen');
        self::assertStringContainsString('I18n::available()', $view,
            'Sprach-Optionen aus I18n::available() ziehen (nicht hartcodiert)');
    }

    public function testProfileControllerPersistsLocale(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/profile_ctrl.php');
        self::assertStringContainsString('UserPrefs::setLocale', $ctrl,
            'ctrl_profile_save muss UserPrefs::setLocale aufrufen');
        self::assertStringContainsString('UserPrefs::locale', $ctrl,
            'ctrl_profile_index muss UserPrefs::locale lesen und an die View geben');
    }

    public function testAdminSystemViewStillCarriesCompanyLocaleDefault(): void
    {
        // Sanity: die System-Pref (globaler Default fuer neue User) bleibt
        // unberuehrt - 3-Schichten-Trennung, Admin setzt den Firmen-Default,
        // der User seine persoenliche Pref.
        $sys = (string) file_get_contents(dirname(__DIR__) . '/views/admin/system.php');
        self::assertStringContainsString('name="locale"', $sys,
            'Admin-System darf den Firmen-Default weiter setzen');
    }
}
