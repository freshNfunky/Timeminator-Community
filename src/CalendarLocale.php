<?php
declare(strict_types=1);

/**
 * Lokale-Helfer fuer Kalender-Layouts.
 *
 * Felix-Feedback 2026-11-08: Der Wochenanfang muss ueberall konsistent sein,
 * damit Features wie die Mood-Spalte in der Monatsansicht links von der
 * richtigen Spalte sitzen - unabhaengig davon, ob der Betrieb Montag,
 * Sonntag oder Samstag als Wochenanfang faehrt.
 *
 * Heute liefert `weekStartDow()` hart ISO-Montag (1); die Methode ist aber
 * der EINE Ort, an dem Views und Controller den Wochenanfang nachfragen.
 * Sobald ein Setting `calendar.week_start_dow` existiert (geplant fuer
 * Milestone 1.1, vgl. Pro-Issue "Wochenanfang konfigurierbar"), wandert
 * die Logik hier rein, und alle Kalender-Views ziehen automatisch mit.
 *
 * Werte:
 *   1 = Montag … 7 = Sonntag (ISO-8601, wie DateTime::format('N'))
 */
final class CalendarLocale
{
    /** Erster Wochentag als ISO-Dow (1..7). Heute hart Montag. */
    public static function weekStartDow(): int
    {
        // Platzhalter fuer spaeteres Setting (vgl. Settings::get('calendar.week_start_dow')).
        // Default Montag, weil DIN 1355-1 + alle bisherigen Views so rechnen.
        $raw = (string) Settings::get('calendar.week_start_dow', '1');
        $n = (int) $raw;
        return ($n >= 1 && $n <= 7) ? $n : 1;
    }

    /**
     * Kurze Wochentag-Labels in der Reihenfolge des konfigurierten
     * Wochenanfangs. Nutzt die bestehenden `cal.day_short.*` i18n-Keys, damit
     * Monat und Woche denselben Satz Labels rendern.
     *
     * @return list<string>
     */
    public static function weekdayShortLabels(): array
    {
        $labels = [
            1 => t('cal.day_short.mon', 'Mo'),
            2 => t('cal.day_short.tue', 'Di'),
            3 => t('cal.day_short.wed', 'Mi'),
            4 => t('cal.day_short.thu', 'Do'),
            5 => t('cal.day_short.fri', 'Fr'),
            6 => t('cal.day_short.sat', 'Sa'),
            7 => t('cal.day_short.sun', 'So'),
        ];
        $start = self::weekStartDow();
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $dow = (($start - 1 + $i) % 7) + 1;
            $out[] = $labels[$dow];
        }
        return $out;
    }

    /**
     * Verschiebt ein Datum auf den jeweiligen Wochenanfang (ISO-Dow aus
     * `weekStartDow`). Ersetzt den bisher verstreuten `modify('monday this
     * week')`-Aufruf und laesst sich leicht gegen jeden Tag der Woche pruefen.
     */
    public static function weekStart(DateTimeImmutable $d): DateTimeImmutable
    {
        $startDow = self::weekStartDow();
        $thisDow  = (int) $d->format('N'); // 1..7
        $diff = ($thisDow - $startDow + 7) % 7;
        return $diff === 0 ? $d : $d->modify('-' . $diff . ' days');
    }

    /** Ist dieser Tag der konfigurierte Wochenanfang? */
    public static function isWeekStart(DateTimeImmutable $d): bool
    {
        return (int) $d->format('N') === self::weekStartDow();
    }

    /**
     * Ende der Woche fuer `$d` entsprechend konfiguriertem Anfang (6 Tage
     * nach weekStart($d)).
     */
    public static function weekEnd(DateTimeImmutable $d): DateTimeImmutable
    {
        return self::weekStart($d)->modify('+6 days');
    }
}
