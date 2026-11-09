<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#111): Stimmungs-Rhythmus-Umschalter liegt
 * im Benutzerbereich (Profil), nicht neben der Systemverwaltung. Default
 * bleibt woechentlich, Nutzer kann fuer sich auf taeglich stellen.
 */
final class ProfileGranularityTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
        // Felix-Feedback 2026-11-09: resetSettings fehlte - Tests haben
        // data/settings.json von laufendem Dev-Server geerbt (z.B. Felix
        // hat 'day' persistiert) und testDefaultGranularityIsWeekWhenNothingSet
        // kippte dadurch zufaellig auf 'day'. Settings-Cache + File leeren
        // macht die Tests wieder hermetisch.
        TestSupport::resetSettings();
    }

    public function testDefaultGranularityIsWeekWhenNothingSet(): void
    {
        self::assertSame('week', Sentiment::granularity());
    }

    public function testSystemDefaultCanBeOverridden(): void
    {
        Settings::set('sentiment_granularity', 'day');
        self::assertSame('day', Sentiment::granularity());
    }

    public function testUserPreferenceOverridesSystemDefault(): void
    {
        $uid = TestSupport::seedUser('alice');
        Settings::set('sentiment_granularity', 'day');
        Sentiment::setUserGranularity($uid, 'week');
        self::assertSame('week', Sentiment::granularity($uid),
            'User-Preference "week" muss System-Default "day" uebersteuern');
    }

    public function testInvalidGranularityIsRejected(): void
    {
        $uid = TestSupport::seedUser('alice');
        self::assertFalse(Sentiment::setUserGranularity($uid, 'monthly'));
    }

    public function testAdminSystemViewHasNoRhythmCardAnymore(): void
    {
        // Felix-Feedback 2026-11-09 (#121): "Stimmungs-Rhythmus-Karte in der
        // Systemverwaltung geht komplett weg - das muss in den Benutzerbereich."
        $sys = (string) file_get_contents(dirname(__DIR__) . '/views/admin/system.php');
        self::assertStringNotContainsString('system.sentiment_granularity_h2', $sys,
            'Rhythmus-Karte darf nicht mehr in der Admin-System-Seite stehen');
        self::assertStringNotContainsString("route('sentiment_granularity_save')", $sys,
            'Save-Form fuer System-Default-Rhythmus muss aus der Admin-System-Seite raus');
    }

    public function testCalendarViewsNoLongerShowGranularityToggle(): void
    {
        // Felix-Feedback 2026-11-09: "Diese Umschalter, die muessen in den
        // Benutzerbereich. ... Lassen wir aber auch der restlichen UI raus."
        $week  = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        $month = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_month.php');
        self::assertStringNotContainsString('cal-sent-toggle', $week,
            'Granularity-Toggle darf nicht mehr im Kalender-Head der Woche stehen');
        self::assertStringNotContainsString('cal-sent-toggle', $month,
            'Granularity-Toggle darf nicht mehr im Kalender-Head des Monats stehen');
    }

    public function testProfileControllerAndViewWiring(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/profile_ctrl.php');
        self::assertStringContainsString('function ctrl_profile_index', $ctrl);
        self::assertStringContainsString('function ctrl_profile_save', $ctrl);
        self::assertStringContainsString('Sentiment::setUserGranularity', $ctrl);

        $view = (string) file_get_contents(dirname(__DIR__) . '/views/profile/index.php');
        self::assertStringContainsString('name="sentiment_granularity"', $view);
        self::assertStringContainsString('value="week"', $view);
        self::assertStringContainsString('value="day"', $view);
        self::assertStringContainsString("tip('profile.sentiment_tip')", $view);

        $index = (string) file_get_contents(dirname(__DIR__) . '/index.php');
        self::assertStringContainsString("'profile'", $index, 'Route profile fehlt');
        self::assertStringContainsString("'profile_save'", $index, 'Route profile_save fehlt');
        self::assertStringContainsString("ctrl_profile_index", $index);
    }

    public function testWeekPresetCardIsCompletelyRemoved(): void
    {
        // Felix-Feedback 2026-11-09 (#117): "Loesche den Wochenpreset und
        // mach den Eintrag heute dynamisch zu entweder woechentlich oder
        // taeglich." Die redundante zweite Karte ist komplett raus.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        self::assertStringNotContainsString("t('sent.week_preset_h2'", $view,
            'Wochen-Preset-Karte darf nicht mehr gerendert werden');
        self::assertStringNotContainsString('sentiment-week-form', $view,
            'Separate Wochen-Preset-Form (sentiment-week-form) muss raus');
    }

    public function testEntryCardTitleSwitchesBetweenDayAndWeek(): void
    {
        // Felix-Feedback 2026-11-09 (#117): "Dein Eintrag heute" wird je nach
        // Benutzer-Preference entweder "heute" oder "diese Woche" gelabelt.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        self::assertStringContainsString("sent.week_title", $view,
            'Karte muss bei Preset=week einen Woche-Titel zeigen');
        self::assertStringContainsString("sent.today_title", $view,
            'Karte muss bei Preset=day einen Heute-Titel zeigen');
        self::assertStringContainsString("sentiment_save_week", $view,
            'Save-Pfad sentiment_save_week muss im View auftauchen (dynamisch gewaehlt)');

        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/sentiment_ctrl.php');
        self::assertStringContainsString('function ctrl_sentiment_save_week', $ctrl,
            'Neuer Wochen-Save-Controller fehlt');
        self::assertStringContainsString("Sentiment::fillRange", $ctrl);
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #135): Die View darf KEINE eigene
     * page-title-H1 rendern - die Layout-Shell zeigt den Titel bereits aus
     * dem view()-Call. Vorher gab es zwei "Meine Einstellungen"-H1s
     * uebereinander.
     */
    public function testProfileViewDoesNotRenderOwnPageTitleH1(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/profile/index.php');
        self::assertStringNotContainsString('<h1 class="page-title">', $view,
            'profile/index.php darf keine eigene page-title-H1 setzen - Titel kommt aus der Layout-Shell');
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #136): Das Profil-Formular nutzt die
     * neue kompakte Radio-Row mit align-items:center, nicht mehr die
     * form-inline-Spalten-Variante, die Radios und Labels vertikal
     * uebereinander stapelte.
     */
    public function testProfileFormUsesCompactRadioRowLayout(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/profile/index.php');
        self::assertStringContainsString('class="profile-form"', $view,
            'Form-Klasse profile-form fehlt');
        self::assertStringContainsString('class="profile-radio-row"', $view,
            'Radio-Row-Container profile-radio-row fehlt');
        self::assertStringContainsString('class="profile-radio"', $view,
            'Einzelne Radio-Labels muessen profile-radio tragen');
        self::assertStringNotContainsString('class="form-inline"', $view,
            'form-inline darf nicht mehr verwendet werden (Spalten-Stacking war zerfleddert)');

        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
        self::assertMatchesRegularExpression(
            '~\.profile-radio-row\s*\{[^}]*align-items:\s*center~s',
            $css,
            'profile-radio-row muss align-items:center setzen, damit Radios + Label auf einer Baseline sitzen'
        );
        self::assertMatchesRegularExpression(
            '~\.profile-radio\s*\{[^}]*display:\s*inline-flex~s',
            $css,
            'profile-radio muss display:inline-flex nutzen, damit Input + Label horizontal nebeneinander stehen'
        );
    }
}
