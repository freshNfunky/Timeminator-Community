<?php
declare(strict_types=1);

/**
 * Interne Prozesse (Issue #270): Angebotserstellung/Akquise/Weiterbildung
 * u.ae. - Zeitbuchung ohne Projektbezug. Bewusst getrennt von
 * time_entries/projects (eigenes Datenmodell, siehe Migrator::
 * m0032ProcessCategories), analog zu AbsenceRepo, aber uhrzeitgranular
 * (start_ts/end_ts/duration_min) statt tagesgranular, weil diese Zeiten -
 * anders als Urlaub/Krankheit - wie normale Arbeitszeit gebucht werden.
 * Darf in Reports/Statistik/Rechnungs-Aggregation nie als abrechenbare
 * Projektzeit auftauchen.
 */
final class ProcessRepo
{
    /** @return list<array<string,mixed>> */
    public static function categories(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM process_categories';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return DB::all($sql . ' ORDER BY position, label');
    }

    public static function category(int $id): ?array
    {
        return DB::one('SELECT * FROM process_categories WHERE id = ?', [$id]);
    }

    public static function saveCategory(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run(
                'UPDATE process_categories SET label=?, position=?, active=? WHERE id=?',
                [$d['label'], $d['position'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO process_categories (ckey, label, position, active, created_at) VALUES (?,?,?,?,?)',
            [$d['ckey'], $d['label'], $d['position'], $d['active'], now()]
        );
    }

    public static function deleteCategory(int $id): void
    {
        DB::run('DELETE FROM process_categories WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> eigene Prozess-Buchungen, neueste zuerst */
    public static function forUser(int $userId, ?string $from = null, ?string $to = null): array
    {
        $where = ['p.user_id = ?'];
        $args = [$userId];
        if ($from !== null) { $where[] = 'p.start_ts >= ?'; $args[] = $from; }
        if ($to !== null) { $where[] = 'p.start_ts <= ?'; $args[] = $to; }
        return DB::all(
            'SELECT p.*, c.label AS category_label, c.ckey AS category_ckey
               FROM process_entries p JOIN process_categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY p.start_ts DESC',
            $args
        );
    }

    public static function entry(int $id): ?array
    {
        return DB::one(
            'SELECT p.*, c.label AS category_label FROM process_entries p
               JOIN process_categories c ON c.id = p.category_id WHERE p.id = ?',
            [$id]
        );
    }

    public static function save(array $d, ?int $id = null): int
    {
        if ($d['end_ts'] <= $d['start_ts']) {
            throw new RuntimeException('Ende muss nach dem Beginn liegen.');
        }
        // Overlap-Check: ein Prozess darf nicht in eine andere Buchung ragen
        // (Pro-PR #444). Beim Bearbeiten den eigenen Eintrag ausnehmen.
        $msg = Repo::bookingConflict((int) $d['user_id'], (string) $d['start_ts'], (string) $d['end_ts'], null, $id);
        if ($msg !== null) {
            throw new RuntimeException($msg);
        }
        $duration = Repo::minutesBetween($d['start_ts'], $d['end_ts']);
        if ($id) {
            DB::run(
                'UPDATE process_entries SET category_id=?, start_ts=?, end_ts=?, duration_min=?, note=?, updated_at=? WHERE id=?',
                [$d['category_id'], $d['start_ts'], $d['end_ts'], $duration, $d['note'], now(), $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, note, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [$d['user_id'], $d['category_id'], $d['start_ts'], $d['end_ts'], $duration, $d['note'], now(), now()]
        );
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM process_entries WHERE id = ?', [$id]);
    }

    /**
     * Felix-Feedback 2026-11-09 (Fix #186): Timer fuer Interne Prozesse.
     * Analog zu Repo::running / startTimer / stopTimer, aber auf
     * process_entries. end_ts + duration_min bleiben NULL solange der
     * Timer laeuft (Migration 2026-11-09-05 macht sie nullable).
     */
    public static function running(int $userId): ?array
    {
        return DB::one(
            'SELECT p.*, c.label AS category_label, c.ckey AS category_ckey
               FROM process_entries p JOIN process_categories c ON c.id = p.category_id
              WHERE p.user_id = ? AND p.end_ts IS NULL
              ORDER BY p.start_ts DESC LIMIT 1',
            [$userId]
        );
    }

    public static function startTimer(int $userId, int $categoryId, string $note = ''): int
    {
        return DB::insert(
            'INSERT INTO process_entries (user_id, category_id, start_ts, end_ts, duration_min, note, created_at, updated_at)
             VALUES (?,?,?,NULL,NULL,?,?,?)',
            [$userId, $categoryId, now(), $note !== '' ? $note : null, now(), now()]
        );
    }

    public static function stopTimer(int $userId): void
    {
        $row = self::running($userId);
        if (!$row) { return; }
        $endTs = now();
        $duration = Repo::minutesBetween((string) $row['start_ts'], $endTs);
        DB::run(
            'UPDATE process_entries SET end_ts=?, duration_min=?, updated_at=? WHERE id=?',
            [$endTs, $duration, $endTs, (int) $row['id']]
        );
    }
}
