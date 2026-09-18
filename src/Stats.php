<?php
declare(strict_types=1);

/**
 * Aggregation for the statistics views. Bucketing is done in PHP so the same
 * code runs on MySQL and SQLite. All logic is generic: nothing is hardcoded to
 * a specific project or client. "Tracks" (Gleise), the two-group comparison and
 * the "proof" scope are all driven by data / configuration.
 */
final class Stats
{
    /** Assign an entry to a work-day (Y-m-d) using the configured boundary hour. */
    public static function workDay(string $startTs): string
    {
        $hour = (int) cfg('day_boundary_hour', 4);
        $dt = new DateTimeImmutable($startTs);
        if ($hour > 0) {
            $dt = $dt->sub(new DateInterval('PT' . $hour . 'H'));
        }
        return $dt->format('Y-m-d');
    }

    /** Resolve the effective track of an entry (project track overrides client). */
    public static function entryTrack(array $e): string
    {
        $t = trim((string) ($e['project_track'] ?? ''));
        if ($t === '') {
            $t = trim((string) ($e['client_track'] ?? ''));
        }
        return $t === '' ? '(ohne Gleis)' : $t;
    }

    /**
     * Apply a saved scope (proof view) to a list of entries.
     * config JSON keys (all optional):
     *   include_project_ids: [int]   only these projects (if set)
     *   exclude_project_ids: [int]   drop these projects
     *   include_client_ids:  [int]   only these clients (if set)
     *   exclude_client_ids:  [int]   drop these clients
     *   tracks:              [str]   only entries whose track is in this list
     *   cutover: [{ date:'YYYY-MM-DD', project_id?:int, client_id?:int, to_client_id:int, to_client_name?:str }]
     *            on/after date, reassign matching entries to another client
     */
    public static function applyScope(array $entries, array $config): array
    {
        $incP = array_map('intval', $config['include_project_ids'] ?? []);
        $excP = array_map('intval', $config['exclude_project_ids'] ?? []);
        $incC = array_map('intval', $config['include_client_ids'] ?? []);
        $excC = array_map('intval', $config['exclude_client_ids'] ?? []);
        $tracks = array_map('strval', $config['tracks'] ?? []);
        $cutover = $config['cutover'] ?? [];

        $out = [];
        foreach ($entries as $e) {
            $pid = (int) $e['project_id'];
            $cid = (int) $e['client_id'];
            if ($incP && !in_array($pid, $incP, true)) continue;
            if ($excP && in_array($pid, $excP, true)) continue;
            if ($incC && !in_array($cid, $incC, true)) continue;
            if ($excC && in_array($cid, $excC, true)) continue;
            if ($tracks && !in_array(self::entryTrack($e), $tracks, true)) continue;

            // Cutover reassignment (generic: match by project or client + date).
            foreach ($cutover as $co) {
                $date = (string) ($co['date'] ?? '');
                if ($date === '') continue;
                $matchP = isset($co['project_id']) ? ((int) $co['project_id'] === $pid) : true;
                $matchC = isset($co['client_id']) ? ((int) $co['client_id'] === $cid) : true;
                if (!isset($co['project_id']) && !isset($co['client_id'])) {
                    $matchP = $matchC = true;
                }
                $day = substr((string) $e['start_ts'], 0, 10);
                if ($matchP && $matchC && $day >= $date && !empty($co['to_client_id'])) {
                    $e['client_id'] = (int) $co['to_client_id'];
                    if (!empty($co['to_client_name'])) {
                        $e['client_name'] = (string) $co['to_client_name'];
                    }
                }
            }
            $out[] = $e;
        }
        return $out;
    }

    /** Sum minutes grouped by a key function. @return array<string,int> */
    public static function sumBy(array $entries, callable $keyFn): array
    {
        $agg = [];
        foreach ($entries as $e) {
            $k = (string) $keyFn($e);
            $agg[$k] = ($agg[$k] ?? 0) + (int) $e['duration_min'];
        }
        arsort($agg);
        return $agg;
    }

    public static function byProject(array $entries): array
    {
        return self::sumBy($entries, fn($e) => $e['client_name'] . ' / ' . $e['project_name']);
    }

    public static function byClient(array $entries): array
    {
        return self::sumBy($entries, fn($e) => (string) $e['client_name']);
    }

    public static function byTrack(array $entries): array
    {
        return self::sumBy($entries, fn($e) => self::entryTrack($e));
    }

    /**
     * Time series by day / week / month, over the work-day.
     * @return array{labels:array<int,string>, minutes:array<int,int>}
     */
    public static function series(array $entries, string $granularity = 'day'): array
    {
        $buckets = [];
        foreach ($entries as $e) {
            $wd = self::workDay((string) $e['start_ts']);
            $dt = new DateTimeImmutable($wd);
            $key = match ($granularity) {
                'week'  => $dt->format('o-\WW'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };
            $buckets[$key] = ($buckets[$key] ?? 0) + (int) $e['duration_min'];
        }
        ksort($buckets);
        return [
            'labels'  => array_keys($buckets),
            'minutes' => array_values($buckets),
        ];
    }

    public static function totalMinutes(array $entries): int
    {
        $s = 0;
        foreach ($entries as $e) {
            $s += (int) $e['duration_min'];
        }
        return $s;
    }
}
