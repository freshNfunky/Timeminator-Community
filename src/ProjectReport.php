<?php
declare(strict_types=1);

/**
 * Projekt-Report: Ist vs Plan (Pro-Sync B.3, #112).
 *
 * Builds on B.2's `projects.target_hours`: lists all projects that have a
 * target set, computes the actual recorded minutes (optionally restricted
 * to a date range), and classifies the progress as ok / warn / over. The
 * table and the inline-SVG bar chart on the view both consume the same
 * rows, and the CSV export is a one-to-one mirror.
 *
 * Budget columns in euros, delay reports, PDF export and the heatmap
 * stay in Pro; this report is intentionally a single flat table.
 */
final class ProjectReport
{
    public const STATUS_OK   = 'ok';     // < 80 % used
    public const STATUS_WARN = 'warn';   // 80..100 %
    public const STATUS_OVER = 'over';   // > 100 %

    /**
     * @param ?int    $clientFilter null = all clients
     * @param ?string $fromYmd      ISO date; null = unbounded
     * @param ?string $toYmd        ISO date; null = unbounded
     *
     * @return array<int,array{
     *   id:int,name:string,client_id:int,client_name:string,
     *   target_hours:float,actual_minutes:int,
     *   diff_minutes:int,percent:int,status:string
     * }>
     */
    public static function rows(?int $clientFilter = null, ?string $fromYmd = null, ?string $toYmd = null): array
    {
        $where = ['p.active = 1', 'p.target_hours IS NOT NULL'];
        $args  = [];
        if ($clientFilter !== null && $clientFilter > 0) {
            $where[] = 'p.client_id = ?';
            $args[]  = $clientFilter;
        }
        $sql = 'SELECT p.id, p.name, p.target_hours, p.client_id, c.name AS client_name
                  FROM projects p
                  JOIN clients c ON c.id = p.client_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.name, p.name';
        $projects = DB::all($sql, $args);

        $out = [];
        foreach ($projects as $p) {
            $actual = self::actualMinutesForProject((int) $p['id'], $fromYmd, $toYmd);
            $targetMin = (int) round(((float) $p['target_hours']) * 60);
            $diff = $actual - $targetMin;
            $pct  = $targetMin > 0 ? (int) round(($actual / $targetMin) * 100) : 0;
            $out[] = [
                'id'             => (int) $p['id'],
                'name'           => (string) $p['name'],
                'client_id'      => (int) $p['client_id'],
                'client_name'    => (string) $p['client_name'],
                'target_hours'   => (float) $p['target_hours'],
                'actual_minutes' => $actual,
                'diff_minutes'   => $diff,
                'percent'        => $pct,
                'status'         => self::classify($pct),
            ];
        }
        return $out;
    }

    public static function classify(int $percent): string
    {
        if ($percent > 100) { return self::STATUS_OVER; }
        if ($percent >= 80) { return self::STATUS_WARN; }
        return self::STATUS_OK;
    }

    private static function actualMinutesForProject(int $projectId, ?string $fromYmd, ?string $toYmd): int
    {
        $sql = 'SELECT COALESCE(SUM(duration_min), 0) AS n
                  FROM time_entries
                 WHERE project_id = ? AND duration_min IS NOT NULL';
        $args = [$projectId];
        if ($fromYmd !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd)) {
            $sql .= ' AND start_ts >= ?';
            $args[] = $fromYmd . ' 00:00:00';
        }
        if ($toYmd !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)) {
            $sql .= ' AND start_ts <= ?';
            $args[] = $toYmd . ' 23:59:59';
        }
        return (int) DB::scalar($sql, $args);
    }
}
