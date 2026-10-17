<?php
declare(strict_types=1);

/** Data access for the domain model (clients, projects, tasks, entries, scopes). */
final class Repo
{
    // ---------- Clients ----------

    public static function clients(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM clients';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return DB::all($sql . ' ORDER BY name');
    }

    public static function client(int $id): ?array
    {
        return DB::one('SELECT * FROM clients WHERE id = ?', [$id]);
    }

    public static function saveClient(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run(
                'UPDATE clients SET name=?, code=?, track=?, color=?, active=? WHERE id=?',
                [$d['name'], $d['code'], $d['track'], $d['color'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO clients (name, code, track, color, active, created_at)
             VALUES (?,?,?,?,?,?)',
            [$d['name'], $d['code'], $d['track'], $d['color'], $d['active'], now()]
        );
    }

    // ---------- Projects ----------

    public static function projects(bool $activeOnly = false): array
    {
        $sql = 'SELECT p.*, c.name AS client_name, c.track AS client_track, c.color AS client_color
                  FROM projects p JOIN clients c ON c.id = p.client_id';
        if ($activeOnly) {
            $sql .= ' WHERE p.active = 1';
        }
        return DB::all($sql . ' ORDER BY c.name, p.name');
    }

    public static function project(int $id): ?array
    {
        return DB::one(
            'SELECT p.*, c.name AS client_name, c.track AS client_track
               FROM projects p JOIN clients c ON c.id = p.client_id
              WHERE p.id = ?',
            [$id]
        );
    }

    public static function saveProject(array $d, ?int $id = null): int
    {
        $target      = array_key_exists('target_hours', $d) ? $d['target_hours'] : null;
        $plannedEnd  = array_key_exists('planned_end', $d) ? $d['planned_end'] : null;
        if ($id) {
            DB::run(
                'UPDATE projects
                    SET client_id=?, name=?, code=?, track=?, color=?, active=?, target_hours=?, planned_end=?
                  WHERE id=?',
                [$d['client_id'], $d['name'], $d['code'], $d['track'], $d['color'], $d['active'], $target, $plannedEnd, $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO projects
                (client_id, name, code, track, color, active, target_hours, planned_end, created_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$d['client_id'], $d['name'], $d['code'], $d['track'], $d['color'], $d['active'], $target, $plannedEnd, now()]
        );
    }

    // ---------- Project trackers (#127) ----------

    /**
     * Community ships three PM-logical tracker types; the kaufmännischen ones
     * (Teilrechnung, Zahlungseingang) + the enterprise-y ones (risk rating,
     * approval chain, meeting log) stay Pro and appear as disabled teaser
     * options in the dropdown.
     */
    /**
     * Community-schaltbare Tracker-Typen. Vereinfachung aus dem UX-Review
     * 2026-10-15 (#127): statt vieler Typen (delay / change_request / …)
     * bekommt der `status_report` eine Sub-Status-Combobox (on_plan /
     * delay / extension) + optionales neues Datum. `progress` bleibt
     * eigener Typ wegen der 0..100-Zahl.
     */
    public const TRACKER_TYPES_COMMUNITY = ['status_report', 'progress'];
    public const TRACKER_STATUSES = ['on_plan', 'delay', 'extension'];

    /**
     * @return array<int,array{id:int,project_id:int,type:string,note:string,value:?float,user_id:?int,user_username:?string,created_at:string}>
     */
    public static function projectTrackers(int $projectId, int $limit = 50): array
    {
        $rows = DB::all(
            'SELECT t.id, t.project_id, t.type, t.status, t.date_value,
                    t.note, t.value, t.value_forecast, t.user_id, t.created_at,
                    u.username AS user_username
               FROM project_trackers t
          LEFT JOIN users u ON u.id = t.user_id
              WHERE t.project_id = ?
              ORDER BY t.created_at DESC
              LIMIT ' . (int) $limit,
            [$projectId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r['id'],
                'project_id'     => (int) $r['project_id'],
                'type'           => (string) $r['type'],
                'status'         => $r['status']     !== null ? (string) $r['status']     : null,
                'date_value'     => $r['date_value'] !== null ? (string) $r['date_value'] : null,
                'note'           => (string) $r['note'],
                'value'          => $r['value']          !== null ? (float) $r['value']          : null,
                'value_forecast' => $r['value_forecast'] !== null ? (float) $r['value_forecast'] : null,
                'user_id'        => $r['user_id']        !== null ? (int) $r['user_id']        : null,
                'user_username'  => $r['user_username']  !== null ? (string) $r['user_username'] : null,
                'created_at'     => (string) $r['created_at'],
            ];
        }
        return $out;
    }

    /**
     * @param array{status?:?string,date_value?:?string,value?:?float,value_forecast?:?float} $extra
     *   - `status` only applies to `status_report` entries; one of
     *     TRACKER_STATUSES or null.
     *   - `date_value` ISO date (YYYY-MM-DD), only for status_report with
     *     a delay/extension sub-status.
     *   - `value` + `value_forecast` 0..100 for progress entries; ignored
     *     otherwise. Forecast is optional.
     */
    public static function saveProjectTracker(int $projectId, string $type, string $note, array $extra, ?int $userId): int
    {
        $type = trim($type);
        if (!in_array($type, self::TRACKER_TYPES_COMMUNITY, true)) {
            $type = 'status_report';
        }
        $note = trim($note);
        if ($note === '') {
            throw new RuntimeException('tracker note must not be empty');
        }
        if (mb_strlen($note) > 4000) {
            $note = mb_substr($note, 0, 4000);
        }

        $status    = null;
        $dateValue = null;
        $value     = null;
        $forecast  = null;
        if ($type === 'progress') {
            $v = $extra['value'] ?? null;
            if ($v === null) {
                throw new RuntimeException('progress tracker requires a value (0..100)');
            }
            $value = max(0.0, min(100.0, (float) $v));
            $f = $extra['value_forecast'] ?? null;
            if ($f !== null) {
                $forecast = max(0.0, min(100.0, (float) $f));
            }
        } else {
            $s = $extra['status'] ?? null;
            if ($s !== null && in_array($s, self::TRACKER_STATUSES, true)) {
                $status = $s;
            }
            if (in_array($status, ['delay', 'extension'], true)) {
                $d = $extra['date_value'] ?? null;
                if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $dateValue = $d;
                }
            }
        }

        return DB::insert(
            'INSERT INTO project_trackers
                (project_id, type, status, date_value, note, value, value_forecast, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $type, $status, $dateValue, $note, $value, $forecast, $userId, now()]
        );
    }

    /**
     * Latest "estimated project end" from the tracker log: the most recent
     * date_value on a delay/extension status_report. Returns null if the
     * project has never had a delay/extension tracker.
     */
    public static function projectEstimatedEnd(int $projectId): ?string
    {
        $row = DB::one(
            "SELECT date_value FROM project_trackers
              WHERE project_id = ? AND type = 'status_report'
                AND status IN ('delay', 'extension')
                AND date_value IS NOT NULL
              ORDER BY created_at DESC LIMIT 1",
            [$projectId]
        );
        return $row ? (string) $row['date_value'] : null;
    }

    public static function deleteProjectTracker(int $id): void
    {
        DB::run('DELETE FROM project_trackers WHERE id = ?', [$id]);
    }

    public static function projectTracker(int $id): ?array
    {
        $r = DB::one(
            'SELECT t.*, u.username AS user_username
               FROM project_trackers t
          LEFT JOIN users u ON u.id = t.user_id
              WHERE t.id = ?',
            [$id]
        );
        if (!$r) { return null; }
        return [
            'id'             => (int) $r['id'],
            'project_id'     => (int) $r['project_id'],
            'type'           => (string) $r['type'],
            'status'         => $r['status']     !== null ? (string) $r['status']     : null,
            'date_value'     => $r['date_value'] !== null ? (string) $r['date_value'] : null,
            'note'           => (string) $r['note'],
            'value'          => $r['value']          !== null ? (float) $r['value']          : null,
            'value_forecast' => $r['value_forecast'] !== null ? (float) $r['value_forecast'] : null,
            'user_id'        => $r['user_id']        !== null ? (int) $r['user_id']        : null,
            'user_username'  => $r['user_username']  !== null ? (string) $r['user_username'] : null,
            'created_at'     => (string) $r['created_at'],
        ];
    }

    /**
     * Edit an existing tracker entry. The user may change date, status/
     * date_value/value/value_forecast and note. Type is immutable (changing
     * the type would require re-validating the per-type fields; use delete+
     * create instead). Backlog-fill scenarios: $createdAt lets the user
     * backdate the entry to when it should have been recorded.
     *
     * @param array{status?:?string,date_value?:?string,value?:?float,value_forecast?:?float} $extra
     */
    public static function updateProjectTracker(int $id, string $note, ?string $createdAt, array $extra): void
    {
        $row = self::projectTracker($id);
        if (!$row) {
            throw new RuntimeException('tracker not found');
        }
        $note = trim($note);
        if ($note === '') {
            throw new RuntimeException('tracker note must not be empty');
        }
        if (mb_strlen($note) > 4000) { $note = mb_substr($note, 0, 4000); }

        $status    = $row['status'];
        $dateValue = $row['date_value'];
        $value     = $row['value'];
        $forecast  = $row['value_forecast'];

        if ($row['type'] === 'progress') {
            if (array_key_exists('value', $extra)) {
                $v = $extra['value'];
                if ($v !== null) {
                    $value = max(0.0, min(100.0, (float) $v));
                }
            }
            if (array_key_exists('value_forecast', $extra)) {
                $f = $extra['value_forecast'];
                $forecast = $f !== null ? max(0.0, min(100.0, (float) $f)) : null;
            }
        } else {
            if (array_key_exists('status', $extra)) {
                $s = $extra['status'];
                $status = ($s !== null && in_array($s, self::TRACKER_STATUSES, true)) ? $s : $status;
            }
            if (array_key_exists('date_value', $extra)) {
                $d = $extra['date_value'];
                if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $dateValue = $d;
                } elseif ($d === null || $d === '') {
                    $dateValue = null;
                }
            }
            // When the status switches back to on_plan, the date no longer applies.
            if ($status === 'on_plan') { $dateValue = null; }
        }

        // Allow back-dating the entry; validate ISO datetime shape.
        $ts = $row['created_at'];
        if ($createdAt !== null && $createdAt !== '') {
            // Accept "YYYY-MM-DDTHH:MM" (datetime-local) and plain "YYYY-MM-DD".
            if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$/', $createdAt)) {
                try {
                    $ts = (new DateTimeImmutable(str_replace('T', ' ', $createdAt)))->format('Y-m-d H:i:s');
                } catch (Throwable) { /* keep existing */ }
            }
        }

        DB::run(
            'UPDATE project_trackers
                SET note = ?, status = ?, date_value = ?, value = ?, value_forecast = ?, created_at = ?
              WHERE id = ?',
            [$note, $status, $dateValue, $value, $forecast, $ts, $id]
        );
    }

    /**
     * Burn-up series for a single project: one point per calendar day
     * between the first recorded entry and today, carrying the cumulative
     * minutes charged to that date. Days without an entry copy the
     * previous cumulative value forward so the line never dips. The
     * caller renders this as the X/Y data of a Project-Cockpit chart.
     *
     * @return array<int,array{d:string,cumulative_minutes:int,day_minutes:int}>
     */
    public static function projectBurnup(int $projectId, int $maxDays = 365): array
    {
        $first = DB::one(
            'SELECT MIN(DATE(start_ts)) AS d
               FROM time_entries
              WHERE project_id = ? AND duration_min IS NOT NULL',
            [$projectId]
        );
        if (!$first || empty($first['d'])) {
            return [];
        }
        $start = new DateTimeImmutable((string) $first['d']);
        $end   = new DateTimeImmutable('today');
        // Clip to maxDays so a 3-year-old project doesn't push 1k points.
        $span  = (int) $start->diff($end)->days;
        if ($span > $maxDays) {
            $start = $end->modify('-' . $maxDays . ' days');
        }

        $perDay = DB::all(
            "SELECT DATE(start_ts) AS d, SUM(duration_min) AS mins
               FROM time_entries
              WHERE project_id = ? AND duration_min IS NOT NULL AND DATE(start_ts) >= ?
           GROUP BY DATE(start_ts)",
            [$projectId, $start->format('Y-m-d')]
        );
        $byDay = [];
        foreach ($perDay as $r) { $byDay[(string) $r['d']] = (int) $r['mins']; }

        $out = [];
        $cursor = $start;
        $cumulative = 0;
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $dayMin = $byDay[$key] ?? 0;
            $cumulative += $dayMin;
            $out[] = [
                'd'                  => $key,
                'cumulative_minutes' => $cumulative,
                'day_minutes'        => $dayMin,
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $out;
    }

    /**
     * Dashboard planning tile data: for every active project that has
     * `target_hours` set, return the target + the actual recorded minutes
     * across all users (community is single-tenant so "all" matches the
     * admin's expectation).
     *
     * @return array<int,array{id:int,name:string,client_name:string,target_hours:float,actual_minutes:int}>
     */
    public static function projectPlanningSnapshot(): array
    {
        $rows = DB::all(
            'SELECT p.id, p.name, p.target_hours, c.name AS client_name,
                    COALESCE((SELECT SUM(e.duration_min) FROM time_entries e
                               WHERE e.project_id = p.id AND e.duration_min IS NOT NULL), 0) AS actual_minutes
               FROM projects p
               JOIN clients c ON c.id = p.client_id
              WHERE p.active = 1 AND p.target_hours IS NOT NULL
              ORDER BY c.name, p.name'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r['id'],
                'name'           => (string) $r['name'],
                'client_name'    => (string) $r['client_name'],
                'target_hours'   => (float) $r['target_hours'],
                'actual_minutes' => (int) $r['actual_minutes'],
            ];
        }
        return $out;
    }

    // ---------- Work packages (Arbeitspakete) ----------

    public static function workPackages(bool $activeOnly = false): array
    {
        $sql = 'SELECT w.*, p.name AS project_name, p.client_id, c.name AS client_name
                  FROM work_packages w
                  JOIN projects p ON p.id = w.project_id
                  JOIN clients  c ON c.id = p.client_id';
        if ($activeOnly) {
            $sql .= ' WHERE w.active = 1';
        }
        return DB::all($sql . ' ORDER BY c.name, p.name, w.name');
    }

    public static function workPackagesByProject(int $projectId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM work_packages WHERE project_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        return DB::all($sql . ' ORDER BY name', [$projectId]);
    }

    public static function workPackage(int $id): ?array
    {
        return DB::one('SELECT * FROM work_packages WHERE id = ?', [$id]);
    }

    public static function saveWorkPackage(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run(
                'UPDATE work_packages SET project_id=?, code=?, name=?, active=? WHERE id=?',
                [$d['project_id'], $d['code'], $d['name'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO work_packages (project_id, code, name, active, created_at) VALUES (?,?,?,?,?)',
            [$d['project_id'], $d['code'], $d['name'], $d['active'], now()]
        );
    }

    // ---------- Tasks ----------

    public static function tasks(bool $activeOnly = false): array
    {
        $sql = 'SELECT t.*, p.name AS project_name, p.client_id, c.name AS client_name,
                       w.name AS work_package_name, w.code AS work_package_code
                  FROM tasks t
                  JOIN projects p ON p.id = t.project_id
                  JOIN clients  c ON c.id = p.client_id
             LEFT JOIN work_packages w ON w.id = t.work_package_id';
        if ($activeOnly) {
            $sql .= ' WHERE t.active = 1';
        }
        return DB::all($sql . ' ORDER BY c.name, p.name, w.name, t.name');
    }

    public static function tasksByProject(int $projectId): array
    {
        return DB::all('SELECT * FROM tasks WHERE project_id = ? ORDER BY name', [$projectId]);
    }

    public static function tasksByWorkPackage(int $workPackageId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM tasks WHERE work_package_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        return DB::all($sql . ' ORDER BY name', [$workPackageId]);
    }

    public static function task(int $id): ?array
    {
        return DB::one(
            'SELECT t.*, p.client_id, p.name AS project_name, c.name AS client_name,
                    w.name AS work_package_name, w.code AS work_package_code
               FROM tasks t
               JOIN projects p ON p.id = t.project_id
               JOIN clients  c ON c.id = p.client_id
          LEFT JOIN work_packages w ON w.id = t.work_package_id
              WHERE t.id = ?',
            [$id]
        );
    }

    public static function saveTask(array $d, ?int $id = null): int
    {
        $wpId = isset($d['work_package_id']) && (int) $d['work_package_id'] > 0
            ? (int) $d['work_package_id']
            : null;
        if ($id) {
            DB::run(
                'UPDATE tasks SET project_id=?, work_package_id=?, name=?, kind=?, active=? WHERE id=?',
                [$d['project_id'], $wpId, $d['name'], $d['kind'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO tasks (project_id, work_package_id, name, kind, active, created_at) VALUES (?,?,?,?,?,?)',
            [$d['project_id'], $wpId, $d['name'], $d['kind'], $d['active'], now()]
        );
    }

    // ---------- Time entries ----------

    /** Resolve the denormalized workpackage/project/client ids for a task. */
    private static function taskContext(int $taskId): array
    {
        $t = DB::one(
            'SELECT t.id, t.project_id, t.work_package_id, p.client_id
               FROM tasks t JOIN projects p ON p.id = t.project_id
              WHERE t.id = ?',
            [$taskId]
        );
        if (!$t) {
            throw new RuntimeException('Unknown task');
        }
        return $t;
    }

    public static function createEntry(int $userId, int $taskId, string $start, ?string $end, string $note, string $source = 'manual'): int
    {
        $ctx = self::taskContext($taskId);
        $dur = $end ? self::minutesBetween($start, $end) : null;
        $ts = now();
        return DB::insert(
            'INSERT INTO time_entries
               (user_id, task_id, work_package_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $taskId, $ctx['work_package_id'], $ctx['project_id'], $ctx['client_id'], $start, $end, $dur, $note, $source, $ts, $ts]
        );
    }

    public static function updateEntry(int $id, int $taskId, string $start, ?string $end, string $note): void
    {
        $ctx = self::taskContext($taskId);
        $dur = $end ? self::minutesBetween($start, $end) : null;
        DB::run(
            'UPDATE time_entries
                SET task_id=?, work_package_id=?, project_id=?, client_id=?, start_ts=?, end_ts=?, duration_min=?, note=?, updated_at=?
              WHERE id=?',
            [$taskId, $ctx['work_package_id'], $ctx['project_id'], $ctx['client_id'], $start, $end, $dur, $note, now(), $id]
        );
    }

    public static function deleteEntry(int $id, int $userId, bool $isAdmin = false): void
    {
        if ($isAdmin) {
            DB::run('DELETE FROM time_entries WHERE id = ?', [$id]);
            return;
        }
        DB::run('DELETE FROM time_entries WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function entry(int $id): ?array
    {
        return DB::one(
            'SELECT e.*, t.name AS task_name, p.name AS project_name, c.name AS client_name,
                    w.name AS work_package_name, w.code AS work_package_code
               FROM time_entries e
               JOIN tasks t    ON t.id = e.task_id
               JOIN projects p ON p.id = e.project_id
               JOIN clients  c ON c.id = e.client_id
          LEFT JOIN work_packages w ON w.id = e.work_package_id
              WHERE e.id = ?',
            [$id]
        );
    }

    /** Running timer entry (end_ts IS NULL) for a user, if any. */
    public static function running(int $userId): ?array
    {
        return DB::one(
            'SELECT e.*, t.name AS task_name, p.name AS project_name, c.name AS client_name,
                    w.name AS work_package_name
               FROM time_entries e
               JOIN tasks t    ON t.id = e.task_id
               JOIN projects p ON p.id = e.project_id
               JOIN clients  c ON c.id = e.client_id
          LEFT JOIN work_packages w ON w.id = e.work_package_id
              WHERE e.user_id = ? AND e.end_ts IS NULL
              ORDER BY e.start_ts DESC LIMIT 1',
            [$userId]
        );
    }

    public static function startTimer(int $userId, int $taskId, string $note = ''): int
    {
        self::stopTimer($userId); // only one running timer per user
        $ctx = self::taskContext($taskId);
        $ts = now();
        return DB::insert(
            'INSERT INTO time_entries
               (user_id, task_id, work_package_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $taskId, $ctx['work_package_id'], $ctx['project_id'], $ctx['client_id'], $ts, null, null, $note, 'timer', $ts, $ts]
        );
    }

    public static function stopTimer(int $userId): bool
    {
        $run = self::running($userId);
        if (!$run) {
            return false;
        }
        $end = now();
        $dur = self::minutesBetween((string) $run['start_ts'], $end);
        DB::run(
            'UPDATE time_entries SET end_ts=?, duration_min=?, updated_at=? WHERE id=?',
            [$end, $dur, $end, (int) $run['id']]
        );
        return true;
    }

    /**
     * List entries with optional filters.
     * @param array{from?:string,to?:string,client_id?:int,project_id?:int,work_package_id?:int,user_id?:int,limit?:int} $f
     */
    public static function entries(array $f = []): array
    {
        $where = ['e.end_ts IS NOT NULL'];
        $args = [];
        if (!empty($f['from']))             { $where[] = 'e.start_ts >= ?';        $args[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))               { $where[] = 'e.start_ts <= ?';        $args[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['client_id']))        { $where[] = 'e.client_id = ?';        $args[] = (int) $f['client_id']; }
        if (!empty($f['project_id']))       { $where[] = 'e.project_id = ?';       $args[] = (int) $f['project_id']; }
        if (!empty($f['work_package_id']))  { $where[] = 'e.work_package_id = ?';  $args[] = (int) $f['work_package_id']; }
        if (!empty($f['user_id']))          { $where[] = 'e.user_id = ?';          $args[] = (int) $f['user_id']; }
        $sql = 'SELECT e.*, t.name AS task_name,
                       p.name AS project_name, p.code AS project_code,
                       c.name AS client_name,  c.code AS client_code,
                       c.color AS client_color, p.color AS project_color,
                       p.track AS project_track, c.track AS client_track,
                       w.name AS work_package_name, w.code AS work_package_code
                  FROM time_entries e
                  JOIN tasks t    ON t.id = e.task_id
                  JOIN projects p ON p.id = e.project_id
                  JOIN clients  c ON c.id = e.client_id
             LEFT JOIN work_packages w ON w.id = e.work_package_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY e.start_ts DESC';
        if (!empty($f['limit'])) {
            $sql .= ' LIMIT ' . (int) $f['limit'];
        }
        return DB::all($sql, $args);
    }

    public static function minutesBetween(string $start, string $end): int
    {
        $a = new DateTimeImmutable($start);
        $b = new DateTimeImmutable($end);
        return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 60);
    }

    // ---------- Scopes (saved analysis views) ----------

    public static function scopes(): array
    {
        return DB::all('SELECT * FROM scopes ORDER BY name');
    }

    public static function scope(int $id): ?array
    {
        return DB::one('SELECT * FROM scopes WHERE id = ?', [$id]);
    }

    public static function saveScope(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run('UPDATE scopes SET name=?, description=?, config=? WHERE id=?',
                [$d['name'], $d['description'], $d['config'], $id]);
            return $id;
        }
        return DB::insert('INSERT INTO scopes (name, description, config, created_at) VALUES (?,?,?,?)',
            [$d['name'], $d['description'], $d['config'], now()]);
    }

    public static function deleteScope(int $id): void
    {
        DB::run('DELETE FROM scopes WHERE id = ?', [$id]);
    }

    // ---------- lookups ----------

    public static function distinctTracks(): array
    {
        $rows = DB::all(
            "SELECT track FROM (
                SELECT track FROM projects WHERE track IS NOT NULL AND track <> ''
                UNION
                SELECT track FROM clients WHERE track IS NOT NULL AND track <> ''
             ) x ORDER BY track"
        );
        return array_values(array_filter(array_column($rows, 'track')));
    }
}
