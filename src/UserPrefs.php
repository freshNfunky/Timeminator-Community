<?php
declare(strict_types=1);

/**
 * M1.1 #191: persoenliche Benutzer-Praeferenzen (Darstellungs-Schicht).
 *
 * Bisher einziger Bewohner: locale. Weitere Prefs wie theme_preference
 * folgen in #194 als zusaetzliche Spalten in user_prefs. Immer nullable -
 * NULL heisst "nichts gesetzt, Firmen-/System-Default verwenden".
 *
 * Trennung von employee_terms (vertraglich, HR/GL darf) und Admin-System
 * (global, Admin darf). Der User selbst darf hier aendern.
 */
final class UserPrefs
{
    /** Komplette Pref-Zeile; leeres Array wenn nichts gespeichert. */
    public static function forUser(int $userId): array
    {
        $row = DB::one('SELECT * FROM user_prefs WHERE user_id = ?', [$userId]);
        return is_array($row) ? $row : [];
    }

    /** Vom User gewaehlte Locale oder null wenn nichts gesetzt. */
    public static function locale(int $userId): ?string
    {
        $row = DB::one('SELECT locale FROM user_prefs WHERE user_id = ?', [$userId]);
        if (!$row) { return null; }
        $code = (string) ($row['locale'] ?? '');
        return $code === '' ? null : $code;
    }

    /**
     * Persistiert die User-Locale. Null oder leerer String loescht die Pref
     * (dann greift wieder Accept-Language / Default). Nur SUPPORTED codes
     * werden akzeptiert, alles andere wird zu null normalisiert.
     */
    public static function setLocale(int $userId, ?string $code): void
    {
        $sane = $code === null ? null : I18n::sanitize($code);
        self::upsert($userId, ['locale' => $sane]);
    }

    /**
     * Setzt einzelne Felder, laesst andere unberuehrt. Mergt per UPSERT
     * damit wir keine getrennten INSERT/UPDATE-Pfade brauchen.
     *
     * @param array<string,mixed> $fields Nur 'locale' (weitere in #194).
     */
    public static function upsert(int $userId, array $fields): void
    {
        $existing = DB::one('SELECT user_id FROM user_prefs WHERE user_id = ?', [$userId]);
        if ($existing) {
            $sets = [];
            $args = [];
            foreach ($fields as $col => $val) {
                $sets[] = "$col = ?";
                $args[] = $val;
            }
            if ($sets === []) { return; }
            $args[] = $userId;
            DB::run('UPDATE user_prefs SET ' . implode(', ', $sets) . ' WHERE user_id = ?', $args);
            return;
        }
        $cols = array_keys($fields);
        $vals = array_values($fields);
        $colSql = implode(',', array_merge(['user_id'], $cols));
        $placeholders = implode(',', array_fill(0, count($cols) + 1, '?'));
        DB::run("INSERT INTO user_prefs ($colSql) VALUES ($placeholders)", array_merge([$userId], $vals));
    }
}
