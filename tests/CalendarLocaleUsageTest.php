<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Code-Review Fixes #129/#130):
 *
 * CalendarLocale ist die EINE Stelle, an der der Wochenanfang/-ende
 * definiert ist. Reviewer-Finding: der Entries-Controller und
 * ctrl_sentiment_quick haben Wochen-Grenzen via `modify('monday this
 * week')` / `modify('sunday this week')` berechnet - also hardcoded, und
 * damit divergent zur Monatsansicht, die bereits CalendarLocale nutzt.
 * Sobald calendar.week_start_dow auf einen anderen Tag gestellt wird,
 * laufen Controller-Range und View-Grid auseinander (Monats-Fliese ohne
 * Feiertags-/Mood-Daten am Rand).
 *
 * Diese Tests zurren die neue Nutzung fest - rueckwaertsgewandt (keine
 * hardcoded monday/sunday) UND vorwaertsgewandt (CalendarLocale wird
 * aufgerufen).
 */
final class CalendarLocaleUsageTest extends TestCase
{
    public function testEntriesControllerMonthPaddingGoesThroughCalendarLocale(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');

        // Positiv: CalendarLocale wird fuer padStart/padEnd verwendet.
        self::assertStringContainsString(
            'CalendarLocale::weekStart($monthStart)',
            $ctrl,
            'entries_ctrl muss padStart via CalendarLocale::weekStart bauen'
        );
        self::assertStringContainsString(
            'CalendarLocale::weekEnd($monthEnd)',
            $ctrl,
            'entries_ctrl muss padEnd via CalendarLocale::weekEnd bauen'
        );

        // Negativ: der alte hardcoded-Weg darf nicht zurueckkommen.
        self::assertStringNotContainsString(
            "modify('monday this week')",
            $ctrl,
            'Monat-Padding in entries_ctrl darf nicht wieder hart auf Montag/Sonntag fallen'
        );
        self::assertStringNotContainsString(
            "modify('sunday this week')",
            $ctrl,
            'Monat-Padding in entries_ctrl darf nicht wieder hart auf Sonntag fallen'
        );
    }

    public function testSentimentQuickWeekScopeUsesCalendarLocaleWeekEnd(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/sentiment_ctrl.php');

        // Positiv: CalendarLocale::weekEnd baut das Range-Ende fuer scope=week.
        self::assertStringContainsString(
            'CalendarLocale::weekEnd(new DateTimeImmutable($today))',
            $ctrl,
            'ctrl_sentiment_quick scope=week muss das Wochenende via CalendarLocale berechnen'
        );

        // Negativ: der alte hardcoded Sunday-Fallback darf nicht zurueck.
        self::assertStringNotContainsString(
            "modify('sunday this week')",
            $ctrl,
            'sentiment_ctrl darf kein "sunday this week" mehr enthalten - Wochenanfang/-ende kommt aus CalendarLocale'
        );
    }

    public function testCalendarLocaleWeekStartRespectsConfiguredDow(): void
    {
        // Direkt-Pruefung der Helper: weekStart/weekEnd folgen weekStartDow.
        // Default-Konfig bleibt Montag, also ist 2026-11-11 (Mi) -> Mo 09.
        $d = new DateTimeImmutable('2026-11-11'); // Mittwoch
        self::assertSame('2026-11-09', CalendarLocale::weekStart($d)->format('Y-m-d'),
            'weekStart muss Montag der Woche liefern (Default-Konfig)');
        self::assertSame('2026-11-15', CalendarLocale::weekEnd($d)->format('Y-m-d'),
            'weekEnd muss Sonntag der Woche liefern (Default-Konfig)');
    }
}
