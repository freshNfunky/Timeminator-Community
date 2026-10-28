<?php
declare(strict_types=1);

/**
 * Log fuer Terminverschiebungen (v1.0 F2, portiert aus Pro #31). Jede
 * Aenderung des `planned_end` eines Projekts wird mit Begruendung geloggt;
 * die Erst-Erfassung (kein altes Datum) nicht.
 *
 * Edit und Delete sind nur fuer den juengsten Eintrag erlaubt — sonst
 * waere projects.planned_end inkonsistent zum Log.
 */
final class DateShiftRepo
{
    public const KIND_PROJECT      = 'project';
    public const KIND_WORK_PACKAGE = 'work_package';

    /** @return 'logged'|'no_change'|'first_set' */
    public static function logIfShifted(
        string $kind,
        int $objectId,
        int $projectId,
        ?string $oldEnd,
        ?string $newEnd,
        ?string $reason
    ): string {
        $oldNorm = self::normDate($oldEnd);
        $newNorm = self::normDate($newEnd);
        if ($oldNorm === $newNorm) {
            return 'no_change';
        }
        if ($oldNorm === null) {
            return 'first_set';
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new RuntimeException(t('date_shift.err_reason_required', 'Begruendung fuer die Terminverschiebung fehlt.'));
        }
        DB::insert(
            'INSERT INTO date_shifts (kind, object_id, project_id, old_end, new_end, reason, user_id, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [$kind, $objectId, $projectId, $oldNorm, $newNorm, $reason, Auth::id(), now()]
        );
        return 'logged';
    }

    public static function updateLatestProjectShift(int $id, int $projectId, string $newEnd, string $reason, string $occurredOn): bool
    {
        if (!self::isLatestProjectShift($id, $projectId)) {
            return false;
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException(t('date_shift.err_reason_required', 'Begruendung fuer die Terminverschiebung fehlt.'));
        }
        $newNorm = self::normDate($newEnd);
        if ($newNorm === null) {
            throw new RuntimeException(t('date_shift.err_invalid_date', 'Ungueltiges Datum.'));
        }
        DB::run(
            'UPDATE date_shifts SET new_end = ?, reason = ?, created_at = ? WHERE id = ? AND project_id = ?',
            [$newNorm, $reason, $occurredOn . ' 00:00:00', $id, $projectId]
        );
        Repo::setProjectPlannedEnd($projectId, $newNorm);
        return true;
    }

    public static function deleteLatestProjectShift(int $id, int $projectId): bool
    {
        if (!self::isLatestProjectShift($id, $projectId)) {
            return false;
        }
        $row = DB::one('SELECT old_end FROM date_shifts WHERE id = ? AND project_id = ?', [$id, $projectId]);
        if (!$row) {
            return false;
        }
        DB::run('DELETE FROM date_shifts WHERE id = ? AND project_id = ?', [$id, $projectId]);
        if ($row['old_end']) {
            Repo::setProjectPlannedEnd($projectId, $row['old_end']);
        }
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        return DB::all(
            'SELECT ds.*, u.username, u.display_name
               FROM date_shifts ds
               LEFT JOIN users u ON u.id = ds.user_id
              WHERE ds.project_id = ?
              ORDER BY ds.created_at DESC, ds.id DESC',
            [$projectId]
        );
    }

    /**
     * Meeting-Uebersicht: aktuell verzoegerte Projekte plus letzte Begruendung
     * und Summe der Verzoegerungstage.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function currentDelays(?string $sinceDate = null): array
    {
        $where = ['ds.kind = ?'];
        $args  = [self::KIND_PROJECT];
        if ($sinceDate) {
            $where[] = 'ds.created_at >= ?';
            $args[]  = $sinceDate;
        }
        $sql = 'SELECT ds.*, p.name AS project_name, c.name AS client_name,
                       u.display_name AS user_display, u.username AS user_name
                  FROM date_shifts ds
                  JOIN projects p ON p.id = ds.project_id
                  JOIN clients  c ON c.id = p.client_id
             LEFT JOIN users    u ON u.id = ds.user_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY p.name, ds.created_at DESC, ds.id DESC';
        $rows = DB::all($sql, $args);

        $byProject = [];
        foreach ($rows as $r) {
            $pid = (int) $r['project_id'];
            $days = self::diffDays($r['old_end'], $r['new_end']);
            if (!isset($byProject[$pid])) {
                $byProject[$pid] = [
                    'project_id'   => $pid,
                    'project_name' => $r['project_name'],
                    'client_name'  => $r['client_name'],
                    'shifts_count' => 0,
                    'total_days'   => 0,
                    'latest'       => null,
                    'entries'      => [],
                ];
            }
            $byProject[$pid]['shifts_count']++;
            $byProject[$pid]['total_days'] += $days;
            if ($byProject[$pid]['latest'] === null) {
                $byProject[$pid]['latest'] = $r;
            }
            $byProject[$pid]['entries'][] = $r + ['days' => $days];
        }
        return array_values($byProject);
    }

    private static function isLatestProjectShift(int $id, int $projectId): bool
    {
        $latestId = DB::scalar(
            'SELECT id FROM date_shifts WHERE project_id = ? AND kind = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$projectId, self::KIND_PROJECT]
        );
        return $latestId !== null && (int) $latestId === $id;
    }

    public static function normDate(?string $s): ?string
    {
        $s = trim((string) ($s ?? ''));
        if ($s === '') return null;
        try {
            return (new DateTimeImmutable($s))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    public static function diffDays(?string $a, ?string $b): int
    {
        if (!$a || !$b) return 0;
        try {
            $da = new DateTimeImmutable($a);
            $db = new DateTimeImmutable($b);
            $sign = $db > $da ? 1 : -1;
            return $sign * (int) $da->diff($db)->days;
        } catch (Throwable) {
            return 0;
        }
    }
}
