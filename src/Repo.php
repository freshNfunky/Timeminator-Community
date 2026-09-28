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
        if ($id) {
            DB::run(
                'UPDATE projects SET client_id=?, name=?, code=?, track=?, color=?, active=? WHERE id=?',
                [$d['client_id'], $d['name'], $d['code'], $d['track'], $d['color'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO projects (client_id, name, code, track, color, active, created_at) VALUES (?,?,?,?,?,?,?)',
            [$d['client_id'], $d['name'], $d['code'], $d['track'], $d['color'], $d['active'], now()]
        );
    }

    // ---------- Tasks ----------

    public static function tasks(bool $activeOnly = false): array
    {
        $sql = 'SELECT t.*, p.name AS project_name, p.client_id, c.name AS client_name
                  FROM tasks t
                  JOIN projects p ON p.id = t.project_id
                  JOIN clients  c ON c.id = p.client_id';
        if ($activeOnly) {
            $sql .= ' WHERE t.active = 1';
        }
        return DB::all($sql . ' ORDER BY c.name, p.name, t.name');
    }

    public static function tasksByProject(int $projectId): array
    {
        return DB::all('SELECT * FROM tasks WHERE project_id = ? ORDER BY name', [$projectId]);
    }

    public static function task(int $id): ?array
    {
        return DB::one(
            'SELECT t.*, p.client_id, p.name AS project_name, c.name AS client_name
               FROM tasks t
               JOIN projects p ON p.id = t.project_id
               JOIN clients  c ON c.id = p.client_id
              WHERE t.id = ?',
            [$id]
        );
    }

    public static function saveTask(array $d, ?int $id = null): int
    {
        if ($id) {
            DB::run(
                'UPDATE tasks SET project_id=?, name=?, kind=?, active=? WHERE id=?',
                [$d['project_id'], $d['name'], $d['kind'], $d['active'], $id]
            );
            return $id;
        }
        return DB::insert(
            'INSERT INTO tasks (project_id, name, kind, active, created_at) VALUES (?,?,?,?,?)',
            [$d['project_id'], $d['name'], $d['kind'], $d['active'], now()]
        );
    }

    // ---------- Time entries ----------

    /** Resolve the denormalized project/client ids for a task. */
    private static function taskContext(int $taskId): array
    {
        $t = DB::one(
            'SELECT t.id, t.project_id, p.client_id
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
               (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $taskId, $ctx['project_id'], $ctx['client_id'], $start, $end, $dur, $note, $source, $ts, $ts]
        );
    }

    public static function updateEntry(int $id, int $taskId, string $start, ?string $end, string $note): void
    {
        $ctx = self::taskContext($taskId);
        $dur = $end ? self::minutesBetween($start, $end) : null;
        DB::run(
            'UPDATE time_entries
                SET task_id=?, project_id=?, client_id=?, start_ts=?, end_ts=?, duration_min=?, note=?, updated_at=?
              WHERE id=?',
            [$taskId, $ctx['project_id'], $ctx['client_id'], $start, $end, $dur, $note, now(), $id]
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
            'SELECT e.*, t.name AS task_name, p.name AS project_name, c.name AS client_name
               FROM time_entries e
               JOIN tasks t    ON t.id = e.task_id
               JOIN projects p ON p.id = e.project_id
               JOIN clients  c ON c.id = e.client_id
              WHERE e.id = ?',
            [$id]
        );
    }

    /** Running timer entry (end_ts IS NULL) for a user, if any. */
    public static function running(int $userId): ?array
    {
        return DB::one(
            'SELECT e.*, t.name AS task_name, p.name AS project_name, c.name AS client_name
               FROM time_entries e
               JOIN tasks t    ON t.id = e.task_id
               JOIN projects p ON p.id = e.project_id
               JOIN clients  c ON c.id = e.client_id
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
               (user_id, task_id, project_id, client_id, start_ts, end_ts, duration_min, note, source, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $taskId, $ctx['project_id'], $ctx['client_id'], $ts, null, null, $note, 'timer', $ts, $ts]
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
     * @param array{from?:string,to?:string,client_id?:int,project_id?:int,user_id?:int,limit?:int} $f
     */
    public static function entries(array $f = []): array
    {
        $where = ['e.end_ts IS NOT NULL'];
        $args = [];
        if (!empty($f['from']))       { $where[] = 'e.start_ts >= ?'; $args[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))         { $where[] = 'e.start_ts <= ?'; $args[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['client_id']))  { $where[] = 'e.client_id = ?'; $args[] = (int) $f['client_id']; }
        if (!empty($f['project_id'])) { $where[] = 'e.project_id = ?'; $args[] = (int) $f['project_id']; }
        if (!empty($f['user_id']))    { $where[] = 'e.user_id = ?'; $args[] = (int) $f['user_id']; }
        $sql = 'SELECT e.*, t.name AS task_name,
                       p.name AS project_name, p.code AS project_code,
                       c.name AS client_name,  c.code AS client_code,
                       c.color AS client_color, p.color AS project_color,
                       p.track AS project_track, c.track AS client_track
                  FROM time_entries e
                  JOIN tasks t    ON t.id = e.task_id
                  JOIN projects p ON p.id = e.project_id
                  JOIN clients  c ON c.id = e.client_id
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
