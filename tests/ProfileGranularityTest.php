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
}
