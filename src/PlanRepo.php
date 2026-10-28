<?php
declare(strict_types=1);

/**
 * Plan-Allokationen (Soll-Stunden) + Soll/Ist-Aggregation fuer die
 * Projekt-Cockpit-Matrix (v1.0 F2, Epic #146, Issue #160).
 *
 * Portiert aus Pro PlanRepo ohne Preis-/Budget-EUR-Logik — Community zeigt
 * nur Zeitplanung, nicht Rechnungs- oder Kostenziele. Progress und
 * Reviewdaten liegen in `project_trackers` (Repo::projectTrackers) statt
 * in einer separaten progress_updates-Tabelle.
 */
final class PlanRepo
{
    // ---------- Plan allocations (Soll) ----------

    /** @return list<array<string,mixed>> */
    public static function allocations(?int $projectId = null): array
    {
        $sql = 'SELECT a.*, p.name AS project_name, c.name AS client_name, u.username, u.display_name
                  FROM plan_allocations a
                  JOIN projects p ON p.id = a.project_id
                  JOIN clients  c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = a.user_id';
        $args = [];
        if ($projectId) {
            $sql .= ' WHERE a.project_id = ?';
            $args[] = $projectId;
        }
        return DB::all($sql . ' ORDER BY c.name, p.name, a.period_start', $args);
    }

    public static function allocation(int $id): ?array
    {
        return DB::one('SELECT * FROM plan_allocations WHERE id = ?', [$id]);
    }

    public static function saveAllocation(array $d, ?int $id = null): int
    {
        $periodKind  = in_array($d['period_kind'] ?? '', ['total', 'week', 'month'], true) ? $d['period_kind'] : 'total';
        $periodStart = $d['period_start'] ?? null;
        $planned     = (int) ($d['planned_minutes'] ?? 0);
        $note        = isset($d['note']) ? trim((string) $d['note']) : null;
        if ($note === '') { $note = null; }

        if ($id) {
            DB::run(
                'UPDATE plan_allocations SET project_id=?, user_id=?, period_kind=?, period_start=?, planned_minutes=?, note=? WHERE id=?',
                [$d['project_id'], $d['user_id'] ?? null, $periodKind, $periodStart, $planned, $note, $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO plan_allocations (project_id, user_id, period_kind, period_start, planned_minutes, note, created_at) VALUES (?,?,?,?,?,?,?)',
            [$d['project_id'], $d['user_id'] ?? null, $periodKind, $periodStart, $planned, $note, now()]
        );
    }

    /** Scoped auf project_id — projektfremde Eintraege lassen sich nicht per ID loeschen. */
    public static function deleteAllocation(int $id, int $projectId): void
    {
        DB::run('DELETE FROM plan_allocations WHERE id = ? AND project_id = ?', [$id, $projectId]);
    }

    /** Total planned minutes for a project (all allocations). */
    public static function plannedTotal(int $projectId): int
    {
        return (int) DB::scalar(
            'SELECT COALESCE(SUM(planned_minutes),0) FROM plan_allocations WHERE project_id = ?',
            [$projectId]
        );
    }

    // ---------- Report aggregation ----------

    /** ISO week key for a datetime, work-day aware (Stats::workDay honours day_boundary_hour). */
    private static function weekKey(string $ts): string
    {
        return (new DateTimeImmutable(Stats::workDay($ts)))->format('o-\WW');
    }

    /** @return array<int,int> projectId => minutes */
    public static function istByProject(?string $from = null, ?string $to = null): array
    {
        $where = ['end_ts IS NOT NULL', 'duration_min IS NOT NULL'];
        $args  = [];
        if ($from) { $where[] = 'start_ts >= ?'; $args[] = $from . ' 00:00:00'; }
        if ($to)   { $where[] = 'start_ts <= ?'; $args[] = $to   . ' 23:59:59'; }
        $rows = DB::all(
            'SELECT project_id, SUM(duration_min) AS m FROM time_entries WHERE '
            . implode(' AND ', $where) . ' GROUP BY project_id',
            $args
        );
        $out = [];
        foreach ($rows as $r) { $out[(int) $r['project_id']] = (int) $r['m']; }
        return $out;
    }

    /** @return array<int,array<string,int>> projectId => weekKey => minutes */
    public static function istByProjectWeek(string $from, string $to): array
    {
        $rows = DB::all(
            'SELECT project_id, start_ts, duration_min FROM time_entries
              WHERE end_ts IS NOT NULL AND duration_min IS NOT NULL
                AND start_ts >= ? AND start_ts <= ?',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        $out = [];
        foreach ($rows as $r) {
            $wk = self::weekKey((string) $r['start_ts']);
            $pid = (int) $r['project_id'];
            $out[$pid][$wk] = ($out[$pid][$wk] ?? 0) + (int) $r['duration_min'];
        }
        return $out;
    }

    /** @return array<int,array<string,int>> projectId => weekKey => planned minutes (sum of per-user cells) */
    public static function sollByProjectWeek(string $from, string $to): array
    {
        $rows = DB::all(
            "SELECT project_id, period_start, planned_minutes FROM plan_allocations
              WHERE period_kind = 'week' AND period_start >= ? AND period_start <= ?",
            [$from, $to]
        );
        $out = [];
        foreach ($rows as $r) {
            $wk = (new DateTimeImmutable((string) $r['period_start']))->format('o-\WW');
            $pid = (int) $r['project_id'];
            $out[$pid][$wk] = ($out[$pid][$wk] ?? 0) + (int) $r['planned_minutes'];
        }
        return $out;
    }

    /** @return array<int,array<int,array<string,int>>> projectId => userId => weekKey => minutes */
    public static function istByProjectUserWeek(string $from, string $to): array
    {
        $rows = DB::all(
            'SELECT project_id, user_id, start_ts, duration_min FROM time_entries
              WHERE end_ts IS NOT NULL AND duration_min IS NOT NULL
                AND start_ts >= ? AND start_ts <= ?',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        $out = [];
        foreach ($rows as $r) {
            $wk  = self::weekKey((string) $r['start_ts']);
            $pid = (int) $r['project_id'];
            $uid = (int) $r['user_id'];
            $out[$pid][$uid][$wk] = ($out[$pid][$uid][$wk] ?? 0) + (int) $r['duration_min'];
        }
        return $out;
    }

    /** @return array<int,array<int,array<string,int>>> projectId => userId => weekKey => planned minutes */
    public static function sollByProjectUserWeek(string $from, string $to): array
    {
        $rows = DB::all(
            "SELECT project_id, user_id, period_start, planned_minutes FROM plan_allocations
              WHERE period_kind = 'week' AND user_id IS NOT NULL AND period_start >= ? AND period_start <= ?",
            [$from, $to]
        );
        $out = [];
        foreach ($rows as $r) {
            $wk  = (new DateTimeImmutable((string) $r['period_start']))->format('o-\WW');
            $pid = (int) $r['project_id'];
            $uid = (int) $r['user_id'];
            $out[$pid][$uid][$wk] = ($out[$pid][$uid][$wk] ?? 0) + (int) $r['planned_minutes'];
        }
        return $out;
    }

    /**
     * Mitgliederliste eines Projekts fuer die Soll/Ist-Matrix: dynamisch
     * aus Buchungen und Wochen-Planung abgeleitet (keine eigene Zuord-
     * nungstabelle).
     *
     * @return list<array{id:int,display_name:?string,username:string}>
     */
    public static function projectMembers(int $projectId): array
    {
        return DB::all(
            'SELECT DISTINCT u.id, u.display_name, u.username FROM users u
              WHERE u.id IN (SELECT DISTINCT user_id FROM time_entries WHERE project_id = ?)
                 OR u.id IN (SELECT DISTINCT user_id FROM plan_allocations WHERE project_id = ? AND user_id IS NOT NULL)
              ORDER BY COALESCE(NULLIF(u.display_name, ""), u.username)',
            [$projectId, $projectId]
        );
    }

    /** ISO week keys spanning the range (inclusive). @return list<string> */
    public static function weekKeys(string $from, string $to): array
    {
        $start = new DateTimeImmutable($from);
        $end   = new DateTimeImmutable($to);
        $cur   = $start->modify('monday this week');
        $keys  = [];
        while ($cur <= $end) {
            $keys[] = $cur->format('o-\WW');
            $cur = $cur->modify('+1 week');
        }
        return $keys;
    }

    /** Monday of an ISO week key ("2026-W40"). */
    public static function weekMonday(string $weekKey): DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekKey, $m)) {
            throw new InvalidArgumentException('bad week key');
        }
        return (new DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2], 1)->setTime(0, 0);
    }
}
