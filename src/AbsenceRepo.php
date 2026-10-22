<?php
declare(strict_types=1);

/**
 * Abwesenheiten (Issue #160): Urlaub/Ausfall wegen Krankheit/Ueberstunden-
 * buchung. Bewusst getrennt von time_entries/projects (eigenes Datenmodell,
 * siehe Migrator::m0017Absences) - speist spaeter die Mitarbeiterkonten aus
 * Issue #60, hat nichts mit Projektbudgets zu tun und darf in Reports/
 * Statistik/Rechnungs-Aggregation nie als abrechenbare Projektzeit auftauchen.
 */
final class AbsenceRepo
{
    /**
     * Technical account keys an absence category can book onto. Labels
     * come from `t('absence_cat.account_<key>')` so views can show
     * translated text; the keys themselves travel through the DB.
     */
    public const ACCOUNTS = [
        'vacation' => 'vacation',
        'overtime' => 'overtime',
        'sick'     => 'sick',
    ];

    public static function accountLabel(string $key): string
    {
        return match ($key) {
            'vacation' => t('absence_cat.account_vacation', 'Urlaubskonto (Tage)'),
            'overtime' => t('absence_cat.account_overtime', 'Ueberstundenkonto (Ueberstundenabbau)'),
            'sick'     => t('absence_cat.account_sick',     'Krankheitskonto (nur Statistik)'),
            default    => $key,
        };
    }

    /** @return list<array<string,mixed>> */
    public static function categories(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM absence_categories';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return DB::all($sql . ' ORDER BY position, label');
    }

    public static function category(int $id): ?array
    {
        return DB::one('SELECT * FROM absence_categories WHERE id = ?', [$id]);
    }

    public static function saveCategory(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run(
                'UPDATE absence_categories SET label=?, position=?, active=?, account=? WHERE id=?',
                [$d['label'], $d['position'], $d['active'], $d['account'] ?? null, $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO absence_categories (ckey, label, position, active, account, created_at) VALUES (?,?,?,?,?,?)',
            [$d['ckey'], $d['label'], $d['position'], $d['active'], $d['account'] ?? null, now()]
        );
    }

    public static function deleteCategory(int $id): void
    {
        DB::run('DELETE FROM absence_categories WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> eigene Abwesenheiten, neueste zuerst */
    public static function forUser(int $userId, ?string $from = null, ?string $to = null): array
    {
        $where = ['a.user_id = ?'];
        $args = [$userId];
        if ($from !== null) { $where[] = 'a.end_date >= ?'; $args[] = $from; }
        if ($to !== null) { $where[] = 'a.start_date <= ?'; $args[] = $to; }
        return DB::all(
            'SELECT a.*, c.label AS category_label, c.ckey AS category_ckey, c.account AS category_account
               FROM absences a JOIN absence_categories c ON c.id = a.category_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.start_date DESC',
            $args
        );
    }

    public static function absence(int $id): ?array
    {
        return DB::one(
            'SELECT a.*, c.label AS category_label, c.account AS category_account FROM absences a
               JOIN absence_categories c ON c.id = a.category_id WHERE a.id = ?',
            [$id]
        );
    }

    /**
     * Abwesenheiten aller Nutzer in einem Datumsbereich (fuer die Kalender-
     * Tagesbadges) - je Tag die Liste der an diesem Tag abwesenden Nutzer.
     * @return array<string,list<array{user_id:int,category_label:string}>> Datum => Liste
     */
    public static function inRange(string $from, string $to, ?int $userId = null): array
    {
        $where = ['a.start_date <= ?', 'a.end_date >= ?'];
        $args = [$to, $from];
        if ($userId !== null) { $where[] = 'a.user_id = ?'; $args[] = $userId; }
        $rows = DB::all(
            'SELECT a.user_id, a.start_date, a.end_date, c.label AS category_label
               FROM absences a JOIN absence_categories c ON c.id = a.category_id
              WHERE ' . implode(' AND ', $where),
            $args
        );
        $out = [];
        foreach ($rows as $r) {
            $d = max($r['start_date'], $from);
            $end = min($r['end_date'], $to);
            while ($d <= $end) {
                $out[$d][] = ['user_id' => (int) $r['user_id'], 'category_label' => (string) $r['category_label']];
                $d = (new DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');
            }
        }
        return $out;
    }

    /**
     * Stunden je Tag aus einer Eingabe: leer = ganzer Tag (null), sonst 0,25 bis 24 h. Nur fuer Abwesenheitsarten
     * mit Konto 'overtime' sinnvoll (Ueberstundenabbau stundenweise), andere werden auf ganze Tage zurueckgesetzt.
     */
    public static function parseHours(mixed $raw, ?array $category): ?float
    {
        $raw = trim(str_replace(',', '.', (string) $raw));
        if ($raw === '' || !$category || ($category['account'] ?? null) !== 'overtime') {
            return null;
        }
        $h = (float) $raw;
        if ($h < 0.25 || $h > 24) {
            throw new RuntimeException('Stunden je Tag muessen zwischen 0,25 und 24 liegen.');
        }
        return round($h * 4) / 4;
    }

    public static function save(array $d, ?int $id = null): int
    {
        if ($d['end_date'] < $d['start_date']) {
            throw new RuntimeException('Bis-Datum darf nicht vor dem Von-Datum liegen.');
        }
        if ($id) {
            DB::run(
                'UPDATE absences SET category_id=?, start_date=?, end_date=?, note=?, hours=?, updated_at=? WHERE id=?',
                [$d['category_id'], $d['start_date'], $d['end_date'], $d['note'], $d['hours'] ?? null, now(), $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO absences (user_id, category_id, start_date, end_date, note, hours, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [$d['user_id'], $d['category_id'], $d['start_date'], $d['end_date'], $d['note'], $d['hours'] ?? null, now(), now()]
        );
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM absences WHERE id = ?', [$id]);
    }
}
